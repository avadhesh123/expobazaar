<?php

namespace App\Services;

use App\Models\{VendorPayout, Vendor, Order, OrderItem, LiveSheet, Chargeback, ActivityLog};
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VendorPayoutService
{
    private $wspRevisions = null;
    private $commissionRevisions = null;

    /**
     * Calculate and save vendor payout for a given period
     */
    public function calculateAndSave(int $vendorId, string $companyCode, int $month, int $year): array
    {
        $vendor = Vendor::findOrFail($vendorId);
        $data = $this->buildPayoutData($vendorId, $companyCode, $month, $year);

        if (empty($data['line_items'])) {
            return ['success' => false, 'error' => "No shipped orders found for {$vendor->company_name} in {$month}/{$year}."];
        }

        $payout = VendorPayout::updateOrCreate(
            ['vendor_id' => $vendorId, 'company_code' => $companyCode, 'payout_month' => $month, 'payout_year' => $year],
            [
                'total_sales'              => $data['summary']['total_sales'],
                'total_commission'         => $data['summary']['total_commission'],
                'gross_payout'             => $data['summary']['total_payout'],
                'total_warehouse_charges'  => $data['summary']['total_warehouse_charges'],
                'total_chargebacks'        => $data['summary']['total_chargebacks'],
                'net_payout'               => $data['summary']['net_payout'],
                'total_shipped_qty'        => $data['summary']['total_qty'],
                'status'                   => 'calculated',
                'calculation_snapshot'      => [
                    'line_items'        => $data['line_items'],
                    'warehouse_charges' => $data['warehouse_charges_raw'],
                    'chargebacks'       => $data['chargebacks_raw'],
                    'returns_raw'       => $data['returns_raw'],
                    'summary'           => $data['summary'],
                    'calculated_at'     => now()->toISOString(),
                    'calculated_by'     => auth()->id(),
                ],
            ]
        );

        ActivityLog::log('calculated', 'vendor_payout', $payout, null, [
            'vendor' => $vendor->company_name,
            'month' => $month, 'year' => $year,
            'net_payout' => $data['summary']['net_payout'],
            'shipped_qty' => $data['summary']['total_qty'],
        ], "Payout calculated: {$vendor->company_name} {$month}/{$year} = {$data['summary']['net_payout']}");

        return ['success' => true, 'payout' => $payout, 'data' => $data];
    }

    /**
     * Build complete payout data (used by both calculate and show)
     */
    public function buildPayoutData(int $vendorId, string $companyCode, int $month, int $year): array
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd   = Carbon::create($year, $month, 1)->endOfMonth();

        // ── 1. Get shipped orders for this vendor in this period ──
        $orders = Order::withoutGlobalScopes()
            ->whereHas('items', fn ($q) => $q->where('vendor_id', $vendorId)->where('shipped_qty', '>', 0))
            ->where('company_code', $companyCode)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->whereIn('status', ['shipped', 'delivered'])
            ->with([
                'salesChannel',
                'items' => fn ($q) => $q->where('vendor_id', $vendorId)
                    ->where('shipped_qty', '>', 0)
                    ->with(['product' => fn ($pq) => $pq->withoutGlobalScopes()])
            ])
            ->get();

        // ── 2. Build FIFO queue from live sheets ──
        $fifoQueue = $this->buildFifoQueue($vendorId, $companyCode, $periodEnd->toDateString());

        // ── 3. Deduct prior months' shipped qty from FIFO queue ──
        $priorShipped = OrderItem::withoutGlobalScopes()
            ->where('vendor_id', $vendorId)
            ->where('shipped_qty', '>', 0)
            ->whereHas('order', fn ($q) => $q->withoutGlobalScopes()
                ->where('company_code', $companyCode)
                ->where('order_date', '<', $periodStart)
                ->whereIn('status', ['shipped', 'delivered']))
            ->select('product_id', DB::raw('SUM(shipped_qty) as shipped'))
            ->groupBy('product_id')
            ->pluck('shipped', 'product_id');

        foreach ($priorShipped as $pid => $shippedQty) {
            if (!isset($fifoQueue[$pid])) {
                continue;
            }
            $remaining = intval($shippedQty);
            foreach ($fifoQueue[$pid] as &$batch) {
                if ($remaining <= 0) {
                    break;
                }
                $deduct = min($remaining, $batch['remaining_qty']);
                $batch['remaining_qty'] -= $deduct;
                $remaining -= $deduct;
            }
            unset($batch);
        }

        // ── 4. Build line items using FIFO allocation ──
        $lineItems = [];
        $totalSales = 0;
        $totalCommission = 0;
        $totalPayout = 0;
        $totalQty = 0;

        foreach ($orders as $order) {
            $orderDate = $order->order_date?->format('Y-m-d') ?? now()->toDateString();

            foreach ($order->items as $item) {
                $product = $item->product;
                if (!$product) {
                    continue;
                }

                $pid = $product->id;
                $shippedQty = intval($item->shipped_qty);
                if ($shippedQty <= 0) {
                    continue;
                }

                $qtyToAllocate = $shippedQty;
                $itemSaleAmount = 0;
                $itemCommission = 0;
                $itemPayout = 0;
                $details = [];

                // FIFO allocation
                if (isset($fifoQueue[$pid])) {
                    foreach ($fifoQueue[$pid] as &$batch) {
                        if ($qtyToAllocate <= 0) {
                            break;
                        }
                        if ($batch['remaining_qty'] <= 0) {
                            continue;
                        }

                        // WSP for this order's date
                        $batchWsp = $this->getWspForDate(
                            $batch['live_sheet_id'],
                            $pid,
                            $orderDate,
                            $batch['vendor_wsp']
                        );

                        // Commission for this order's date
                        $batchCommPercent = $this->getCommissionForDate(
                            $orderDate,
                            $batch['base_commission']
                        );

                        $allocate = min($qtyToAllocate, $batch['remaining_qty']);
                        $batchSale = round($batchWsp * $allocate, 2);
                        $batchCommAmt = round(($batchCommPercent / 100) * $batchSale, 2);
                        $batchPayout = round($batchSale - $batchCommAmt, 2);

                        $itemSaleAmount += $batchSale;
                        $itemCommission += $batchCommAmt;
                        $itemPayout += $batchPayout;

                        $details[] = "{$allocate}u × {$batchWsp} @ {$batchCommPercent}%";

                        $batch['remaining_qty'] -= $allocate;
                        $qtyToAllocate -= $allocate;
                    }
                    unset($batch);
                }

                // Unallocated qty — fallback
                if ($qtyToAllocate > 0) {
                    $fallbackWsp = floatval($product->vendor_wsp ?? $product->fob_price ?? 0);
                    $fallbackSale = round($fallbackWsp * $qtyToAllocate, 2);
                    $itemSaleAmount += $fallbackSale;
                    $itemPayout += $fallbackSale;
                    $details[] = "{$qtyToAllocate}u × {$fallbackWsp} @ 0%";
                }

                $avgWsp = $shippedQty > 0 ? round($itemSaleAmount / $shippedQty, 2) : 0;

                $lineItems[] = [
                    'order_id'      => $order->id,
                    'order_number'  => $order->order_number,
                    'order_date'    => $orderDate,
                    'sku'           => $item->sku ?? $product->sku ?? '—',
                    'channel'       => $order->salesChannel->name ?? '—',
                    'vendor_wsp'    => $avgWsp,
                    'qty'           => $shippedQty,
                    'sale_amount'   => round($itemSaleAmount, 2),
                    'commission'    => round($itemCommission, 2),
                    'net_payout'    => round($itemPayout, 2),
                    'fifo_detail'   => implode(' + ', $details),
                ];

                $totalSales += $itemSaleAmount;
                $totalCommission += $itemCommission;
                $totalPayout += $itemPayout;
                $totalQty += $shippedQty;
            }
        }

        // ── 5. Warehouse charges ──
        // $warehouseCharges = \App\Models\VendorMonthlyCharge::withoutGlobalScopes()
        //     ->where('vendor_id', $vendorId)
        //     ->where('company_code', $companyCode)
        //     ->where('charge_month', $month)
        //     ->where('charge_year', $year)
        //     ->where('status', 'approved')
        //     ->with('warehouse')
        //     ->get();

        $warehouseCharges = \App\Models\VendorMonthlyCharge::withoutGlobalScopes()
            ->where('vendor_id', $vendorId)
            ->where('company_code', $companyCode)
            ->where('charge_month', $month)
            ->where('charge_year', $year)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->where('charge_status', 'active')
                  ->orWhereNull('charge_status');
            })
            ->with('warehouse')
            ->get();

        $totalWarehouseCharges = $warehouseCharges->sum(fn ($c) => floatval($c->total_charges ?? $c->calculated_amount ?? 0));
        // ── 6. Chargebacks ──
        $chargebacks = Chargeback::withoutGlobalScopes()
            ->where('vendor_id', $vendorId)
            ->whereHas('order', fn ($q) => $q->withoutGlobalScopes()->where('company_code', $companyCode))
            ->where('status', 'confirmed')
            ->whereMonth('confirmed_at', $month)
            ->whereYear('confirmed_at', $year)
            ->with(['order' => fn ($q) => $q->withoutGlobalScopes()])
            ->get();


        $totalChargebacks = $chargebacks->sum('amount');

        // ── 6b. Return Orders ──
        /* $returnOrders = Order::withoutGlobalScopes()
             ->where('company_code', $companyCode)
             ->where('status', 'returned')
             ->whereMonth('order_date', $month)
             ->whereYear('order_date', $year)
             ->whereHas('items', fn ($q) => $q->where('vendor_id', $vendorId))
             ->with([
                 'salesChannel',
                 'items' => fn ($q) => $q->where('vendor_id', $vendorId)
                     ->with(['product' => fn ($pq) => $pq->withoutGlobalScopes()])
             ])
             ->get();

         $totalReturns = 0;
         $returnsRaw = [];

         foreach ($returnOrders as $ro) {
             foreach ($ro->items as $item) {
                 $qty = intval($item->shipped_qty > 0 ? $item->shipped_qty : $item->quantity);
                 $wsp = floatval($item->unit_price ?? 0);
                 $amount = round($wsp * $qty, 2);
                 $totalReturns += $amount;

                 $returnsRaw[] = [
                     'order'   => $ro->order_number,
                     'sku'     => $item->sku ?? $item->product->sku ?? '—',
                     'product' => $item->product->name ?? '—',
                     'qty'     => $qty,
                     'wsp'     => $wsp,
                     'amount'  => $amount,
                     'channel' => $ro->salesChannel->name ?? '—',
                     'date'    => $ro->order_date?->format('Y-m-d'),
                     'reason'  => $ro->return_reason ?? $item->return_reason ?? '—',
                 ];
             }
         }

         $totalReturns = round($totalReturns, 2);

*/

        // ── 6b. Return Orders (WSP from revisions, same as orders) ──
        $returnOrders = Order::withoutGlobalScopes()
            ->where('company_code', $companyCode)
            ->whereIn('status', ['returned', 'partially_returned'])
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $year)
            ->whereHas('items', fn ($q) => $q->where('vendor_id', $vendorId))
            ->with([
                'salesChannel',
                'items' => fn ($q) => $q->where('vendor_id', $vendorId)
                    ->with(['product' => fn ($pq) => $pq->withoutGlobalScopes()])
            ])
            ->get();

        $totalReturns = 0;
        $returnsRaw = [];

        foreach ($returnOrders as $ro) {
            $orderDate = $ro->order_date?->format('Y-m-d') ?? now()->toDateString();

            foreach ($ro->items as $item) {
                $product = $item->product;
                if (!$product) {
                    continue;
                }

                $pid = $product->id;
                $returnQty = intval($item->returned_qty ?? $item->shipped_qty ?? $item->quantity);
                if ($returnQty <= 0) {
                    continue;
                }

                $qtyToAllocate = $returnQty;
                $itemReturnAmount = 0;
                $details = [];

                // FIFO WSP allocation — same as order calculation
                if (isset($fifoQueue[$pid])) {
                    foreach ($fifoQueue[$pid] as &$batch) {
                        if ($qtyToAllocate <= 0) {
                            break;
                        }

                        // WSP for this order's date
                        $batchWsp = $this->getWspForDate(
                            $batch['live_sheet_id'],
                            $pid,
                            $orderDate,
                            $batch['vendor_wsp']
                        );

                        // Commission for this order's date
                        $batchCommPercent = $this->getCommissionForDate(
                            $orderDate,
                            $batch['base_commission']
                        );

                        $allocate = min($qtyToAllocate, abs($batch['remaining_qty']));
                        if ($allocate <= 0) {
                            // No remaining in FIFO — use WSP directly without deducting
                            $allocate = $qtyToAllocate;
                            $batchSale = round($batchWsp * $allocate, 2);
                            $batchCommAmt = round(($batchCommPercent / 100) * $batchSale, 2);
                            $itemReturnAmount += round($batchSale - $batchCommAmt, 2);
                            $details[] = "{$allocate}u × {$batchWsp} @ {$batchCommPercent}%";
                            $qtyToAllocate = 0;
                            break;
                        }

                        $batchSale = round($batchWsp * $allocate, 2);
                        $batchCommAmt = round(($batchCommPercent / 100) * $batchSale, 2);
                        $itemReturnAmount += round($batchSale - $batchCommAmt, 2);
                        $details[] = "{$allocate}u × {$batchWsp} @ {$batchCommPercent}%";

                        $qtyToAllocate -= $allocate;
                    }
                    unset($batch);
                }

                // Unallocated — fallback WSP
                if ($qtyToAllocate > 0) {
                    $fallbackWsp = floatval($product->vendor_wsp ?? $product->fob_price ?? $item->unit_price ?? 0);
                    $itemReturnAmount += round($fallbackWsp * $qtyToAllocate, 2);
                    $details[] = "{$qtyToAllocate}u × {$fallbackWsp} @ 0% (fallback)";
                }

                $totalReturns += $itemReturnAmount;

                $returnsRaw[] = [
                    'order'       => $ro->order_number,
                    'order_date'  => $orderDate,
                    'sku'         => $item->sku ?? $product->sku ?? '—',
                    'product'     => $product->name ?? '—',
                    'qty'         => $returnQty,
                    'amount'      => round($itemReturnAmount, 2),
                    'channel'     => $ro->salesChannel->name ?? '—',
                    'reason'      => $ro->return_reason ?? $item->return_reason ?? '—',
                    'fifo_detail' => implode(' + ', $details),
                ];
            }
        }

        $totalReturns = round($totalReturns, 2);

        // ── 7. Net payout ──
        //        $netPayout = round($totalPayout - $totalWarehouseCharges - $totalChargebacks, 2);

        $netPayout = round($totalPayout - $totalWarehouseCharges - $totalChargebacks - $totalReturns, 2);

        return [
            'line_items' => $lineItems,
            'orders' => $orders,
            'warehouse_charges' => $warehouseCharges,
            'warehouse_charges_raw' => $warehouseCharges->map(fn ($c) => [
                'warehouse' => $c->warehouse->name ?? '—',
                'amount' => floatval($c->total_charge ?? $c->calculated_amount ?? 0),
            ])->toArray(),
            'chargebacks' => $chargebacks,
            'return_orders'     => $returnOrders,
            'returns_raw'       => $returnsRaw,
            'chargebacks_raw' => $chargebacks->map(fn ($c) => [
                'order' => $c->order->order_number ?? '—',
                'amount' => floatval($c->amount),
                'reason' => $c->reason ?? '—',
            ])->toArray(),
            'summary' => [
                'total_qty'               => $totalQty,
                'total_sales'             => round($totalSales, 2),
                'total_commission'        => round($totalCommission, 2),
                'total_payout'            => round($totalPayout, 2),
                'total_warehouse_charges' => round($totalWarehouseCharges, 2),
                'total_chargebacks'       => round($totalChargebacks, 2),
                'total_returns'           => $totalReturns,
                'net_payout'              => $netPayout,
            ],
        ];
    }

    /**
     * Build FIFO queue from live sheets
     * WSP and Commission are resolved per order date, not here
     */
    private function buildFifoQueue(int $vendorId, string $companyCode, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $liveSheets = LiveSheet::withoutGlobalScopes()
            ->where('vendor_id', $vendorId)
            ->where('company_code', $companyCode)
            ->orderBy('approved_at', 'asc')
            ->with(['items' => fn ($q) => $q->select('id', 'live_sheet_id', 'product_id', 'quantity', 'unit_price', 'product_details')])
            ->get();

        $lsIds = $liveSheets->pluck('id');

        // Preload ALL WSP revisions (not filtered by date — matched per order date later)
        $this->wspRevisions = \App\Models\WspRevision::whereIn('live_sheet_id', $lsIds)
            ->orderByDesc('effective_from')
            ->get();

        // Preload ALL commission revisions for this vendor (date-based, no live sheet dependency)
        $this->commissionRevisions = \App\Models\CommissionRevision::where('vendor_id', $vendorId)
            ->where('company_code', $companyCode)
            ->orderByDesc('effective_from')
            ->get();

        \Log::channel('daily')->info("[Payout] FIFO: Loaded {$this->wspRevisions->count()} WSP revisions, {$this->commissionRevisions->count()} commission revisions for vendor {$vendorId}");

        $fifoQueue = [];

        foreach ($liveSheets as $ls) {
            // Base commission from live sheet (fallback if no revision matches)
            $baseCommission = floatval($ls->commission_percentage ?? 0);

            foreach ($ls->items as $lsItem) {
                $pid = $lsItem->product_id;
                $d = $lsItem->product_details ?? [];
                $baseWsp = floatval($d['wsp'] ?? $d['vendor_wsp'] ?? $lsItem->unit_price ?? 0);

                if (!isset($fifoQueue[$pid])) {
                    $fifoQueue[$pid] = [];
                }
                $fifoQueue[$pid][] = [
                    'live_sheet_id'     => $ls->id,
                    'live_sheet_number' => $ls->live_sheet_number ?? '',
                    'vendor_wsp'        => $baseWsp,        // fallback WSP
                    'base_commission'   => $baseCommission,  // fallback commission
                    'remaining_qty'     => intval($lsItem->quantity),
                ];
            }
        }

        return $fifoQueue;
    }

    /**
     * Get WSP for a specific live sheet + product on a specific order date
     * Matches revision where: effective_from <= orderDate AND (effective_to >= orderDate OR effective_to IS NULL)
     */
    private function getWspForDate(int $liveSheetId, int $productId, string $orderDate, float $fallbackWsp): float
    {
        if (!$this->wspRevisions || $this->wspRevisions->isEmpty()) {
            return $fallbackWsp;
        }

        $orderDateCarbon = Carbon::parse($orderDate);
        $match = null;

        // Priority 1: Product-specific on THIS live sheet
        foreach ($this->wspRevisions as $r) {
            if ($r->live_sheet_id != $liveSheetId) {
                continue;
            }
            if ($r->product_id != $productId) {
                continue;
            }

            $from = Carbon::parse($r->effective_from);
            if ($from->gt($orderDateCarbon)) {
                continue;
            }

            if ($r->effective_to) {
                $to = Carbon::parse($r->effective_to);
                if ($orderDateCarbon->gt($to)) {
                    continue;
                }
            }

            if (!$match || Carbon::parse($r->effective_from)->gt(Carbon::parse($match->effective_from))) {
                $match = $r;
            }
        }

        if ($match) {
            \Log::channel('daily')->info("[Payout] WSP: pid={$productId} date={$orderDate} → {$match->vendor_wsp} [product revision #{$match->id}]");
            return floatval($match->vendor_wsp);
        }

        // Priority 2: Product-specific on ANY live sheet
        foreach ($this->wspRevisions as $r) {
            if ($r->product_id != $productId) {
                continue;
            }

            $from = Carbon::parse($r->effective_from);
            if ($from->gt($orderDateCarbon)) {
                continue;
            }

            if ($r->effective_to) {
                $to = Carbon::parse($r->effective_to);
                if ($orderDateCarbon->gt($to)) {
                    continue;
                }
            }

            if (!$match || Carbon::parse($r->effective_from)->gt(Carbon::parse($match->effective_from))) {
                $match = $r;
            }
        }

        if ($match) {
            \Log::channel('daily')->info("[Payout] WSP: pid={$productId} date={$orderDate} → {$match->vendor_wsp} [any-ls revision #{$match->id}]");
            return floatval($match->vendor_wsp);
        }

        // Priority 3: Sheet-level on THIS live sheet
        foreach ($this->wspRevisions as $r) {
            if ($r->live_sheet_id != $liveSheetId) {
                continue;
            }
            if ($r->product_id !== null) {
                continue;
            }

            $from = Carbon::parse($r->effective_from);
            if ($from->gt($orderDateCarbon)) {
                continue;
            }

            if ($r->effective_to) {
                $to = Carbon::parse($r->effective_to);
                if ($orderDateCarbon->gt($to)) {
                    continue;
                }
            }

            if (!$match || Carbon::parse($r->effective_from)->gt(Carbon::parse($match->effective_from))) {
                $match = $r;
            }
        }

        if ($match) {
            \Log::channel('daily')->info("[Payout] WSP: pid={$productId} date={$orderDate} → {$match->vendor_wsp} [sheet revision #{$match->id}]");
            return floatval($match->vendor_wsp);
        }

        return $fallbackWsp;
    }

    /**
     * Get commission for a vendor on a specific order date
     * Pure date-range match — no live sheet or product dependency
     * Matches revision where: effective_from <= orderDate AND (effective_to >= orderDate OR effective_to IS NULL)
     */
    private function getCommissionForDate(string $orderDate, float $fallbackCommission): float
    {
        if (!$this->commissionRevisions || $this->commissionRevisions->isEmpty()) {
            return $fallbackCommission;
        }

        $orderDateCarbon = Carbon::parse($orderDate);
        $match = null;

        foreach ($this->commissionRevisions as $r) {
            $from = Carbon::parse($r->effective_from);
            if ($from->gt($orderDateCarbon)) {
                continue;
            }

            if ($r->effective_to) {
                $to = Carbon::parse($r->effective_to);
                if ($orderDateCarbon->gt($to)) {
                    continue;
                }
            }

            if (!$match || Carbon::parse($r->effective_from)->gt(Carbon::parse($match->effective_from))) {
                $match = $r;
            }
        }

        if ($match) {
            \Log::channel('daily')->info("[Payout] Commission: date={$orderDate} → {$match->commission_percentage}% [revision #{$match->id}]");
            return floatval($match->commission_percentage);
        }

        return $fallbackCommission;
    }
}
