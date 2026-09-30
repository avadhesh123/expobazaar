<?php

namespace App\Services;

use App\Models\{VendorPayout, Vendor, Order, OrderItem, LiveSheet, Chargeback, ActivityLog};
use Illuminate\Support\Facades\DB;

class VendorPayoutService
{
    /**
     * Calculate and save vendor payout for a given period
     * Uses shipped_qty, FIFO WSP, and FIFO commission
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
                    'line_items'       => $data['line_items'],
                    'warehouse_charges' => $data['warehouse_charges_raw'],
                    'chargebacks'      => $data['chargebacks_raw'],
                    'summary'          => $data['summary'],
                    'calculated_at'    => now()->toISOString(),
                    'calculated_by'    => auth()->id(),
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
     * All calculations based on shipped_qty from order_items
     */
    public function buildPayoutData(int $vendorId, string $companyCode, int $month, int $year): array
    {
        $logFile = storage_path('logs/vendor_payout_'.now()->format('Y-m-d').'.log');

        $periodStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd   = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();

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
        // $fifoQueue = $this->buildFifoQueue($vendorId, $companyCode);
        // In buildPayoutData(), change:
        $fifoQueue = $this->buildFifoQueue($vendorId, $companyCode, $periodEnd->toDateString());
       //  file_put_contents($logFile, "V {$vendorId}  : periodStart {$periodStart} fifoQueue " . json_encode($fifoQueue) . "\n", FILE_APPEND);

        // ── 3. Deduct prior months' shipped qty from FIFO queue ──
        $priorShipped = OrderItem::withoutGlobalScopes()
            ->where('vendor_id', $vendorId)
            ->where('shipped_qty', '>', 0)
            ->whereHas('order', fn ($q) => $q->withoutGlobalScopes()
                ->where('company_code', $companyCode)
                //->where('order_date', '<', $periodStart)
                 ->whereBetween('order_date', [$periodStart, $periodEnd])
                ->whereIn('status', ['shipped', 'delivered']))
            ->select('product_id', DB::raw('SUM(shipped_qty) as shipped'))
            ->groupBy('product_id')
            ->pluck('shipped', 'product_id');


        // Print SQL
        // dd($query->toSql(), $query->getBindings());

        // // Execute
        // $priorShipped = $query->pluck('shipped', 'product_id');

         file_put_contents($logFile, "ORDERS PID periodStart:{$periodStart} periodEnd:{$periodEnd} " . json_encode($priorShipped) . "  \n", FILE_APPEND);

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
            foreach ($order->items as $item) {
                $product = $item->product;
                if (!$product) {
                    continue;
                }

                $pid = $product->id;
                
                file_put_contents($logFile, "PID {$pid}  \n", FILE_APPEND);

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

                    file_put_contents($logFile, "PID {$pid}  \n", FILE_APPEND);

                    foreach ($fifoQueue[$pid] as &$batch) {
                        if ($qtyToAllocate <= 0) {
                            break;
                        }
                        if ($batch['remaining_qty'] <= 0) {
                            continue;
                        }

                        $allocate = min($qtyToAllocate, $batch['remaining_qty']);
                        $batchSale = round($batch['vendor_wsp'] * $allocate, 2);
                        $batchComm = round(($batch['commission'] / 100) * $batchSale, 2);
                        $batchPayout = round($batchSale - $batchComm, 2);

                        $itemSaleAmount += $batchSale;
                        $itemCommission += $batchComm;
                        $itemPayout += $batchPayout;

                        $details[] = "{$allocate}u × {$batch['vendor_wsp']} @ {$batch['commission']}%";

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
        $warehouseCharges = \App\Models\VendorMonthlyCharge::withoutGlobalScopes()
            ->where('vendor_id', $vendorId)
            ->where('company_code', $companyCode)
            ->where('charge_month', $month)
            ->where('charge_year', $year)
            ->with('warehouse')
            ->get();

        $totalWarehouseCharges = $warehouseCharges->sum(fn ($c) => floatval($c->total_charge ?? $c->calculated_amount ?? 0));

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

        // ── 7. Net payout ──
        $netPayout = round($totalPayout - $totalWarehouseCharges - $totalChargebacks, 2);

        return [
            'line_items' => $lineItems,
            'orders' => $orders,
            'warehouse_charges' => $warehouseCharges,
            'warehouse_charges_raw' => $warehouseCharges->map(fn ($c) => [
                'warehouse' => $c->warehouse->name ?? '—',
                'amount' => floatval($c->total_charge ?? $c->calculated_amount ?? 0),
            ])->toArray(),
            'chargebacks' => $chargebacks,
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
                'net_payout'              => $netPayout,
            ],
        ];
    }
    private function buildFifoQueue(int $vendorId, string $companyCode, ?string $asOfDate = null): array
    {
        $logFile = storage_path('logs/vendor_payout_'.now()->format('Y-m-d').'.log');

        $asOfDate = $asOfDate ?? now()->toDateString();

        $liveSheets = LiveSheet::withoutGlobalScopes()
            ->where('vendor_id', $vendorId)
            ->where('company_code', $companyCode)
          //  ->where('status', 'locked')
            ->orderBy('approved_at', 'asc')
            ->with(['items' => fn ($q) => $q->select('id', 'live_sheet_id', 'product_id', 'quantity', 'unit_price', 'product_details')])
            ->get();

        $lsIds = $liveSheets->pluck('id');

        // Get commission active AS OF the payout period, not today
        $activeRevisions = \App\Models\CommissionRevision::whereIn('live_sheet_id', $lsIds)
            ->where('effective_from', '<=', $asOfDate)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $asOfDate))
            ->orderByDesc('effective_from')
            ->get()
            ->unique('live_sheet_id')
            ->keyBy('live_sheet_id');

        $fifoQueue = [];

        foreach ($liveSheets as $ls) {
            $commPercent = isset($activeRevisions[$ls->id])
                ? floatval($activeRevisions[$ls->id]->commission_percentage)
                : floatval($ls->commission_percentage ?? 0);

            \Log::channel('daily')->info("FIFO: LS {$ls->live_sheet_number} commission={$commPercent}% (as of {$asOfDate})");
file_put_contents($logFile, "FIFO: LS {$ls->live_sheet_number} commission={$commPercent}% (as of {$asOfDate})   "  . "\n", FILE_APPEND);
            foreach ($ls->items as $lsItem) {
                $pid = $lsItem->product_id;
                $d = $lsItem->product_details ?? [];
                $batchWsp = floatval($d['wsp'] ?? $d['vendor_wsp'] ?? $lsItem->unit_price ?? 0);

file_put_contents($logFile, "FIFO LS: PID {$pid}   "  . "\n", FILE_APPEND);

                if (!isset($fifoQueue[$pid])) {
                    $fifoQueue[$pid] = [];
                }
                $fifoQueue[$pid][] = [
                    'live_sheet_id'     => $ls->id,
                    'live_sheet_number' => $ls->live_sheet_number ?? '',
                    'vendor_wsp'        => $batchWsp,
                    'commission'        => $commPercent,
                    'remaining_qty'     => intval($lsItem->quantity),
                ];
            }
        }

        return $fifoQueue;
    }
    /**
     * Build FIFO queue from approved/locked live sheets
     */
    private function buildFifoQueueBAK(int $vendorId, string $companyCode): array
    {
        $logFile = storage_path('logs/vendor_payout_'.now()->format('Y-m-d').'.log');

        $liveSheets = LiveSheet::withoutGlobalScopes()
            ->where('vendor_id', $vendorId)
            ->where('company_code', $companyCode)
            ->where('status', 'locked')
            ->orderBy('approved_at', 'asc')
            ->with(['items' => fn ($q) => $q->select('id', 'live_sheet_id', 'product_id', 'quantity', 'unit_price', 'product_details')])
            ->get();

        $fifoQueue = [];

        $lsIds = $liveSheets->pluck('id');
        $activeRevisions = \App\Models\CommissionRevision::whereIn('live_sheet_id', $lsIds)
            ->where('effective_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
            ->orderByDesc('effective_from')
            ->get()
            ->unique('live_sheet_id')  // one per live sheet (latest active)
            ->keyBy('live_sheet_id');


        foreach ($liveSheets as $ls) {
            //  $commPercent = floatval($ls->commission_percentage ?? 0);
            $commPercent = isset($activeRevisions[$ls->id])
                    ? floatval($activeRevisions[$ls->id]->commission_percentage)
                    : floatval($ls->commission_percentage ?? 0);


            file_put_contents($logFile, "LS {$ls->live_sheet_number}  {$ls->id}:  commission_percentage {$commPercent}\n", FILE_APPEND);

            foreach ($ls->items as $lsItem) {
                $pid = $lsItem->product_id;
                $d = $lsItem->product_details ?? [];
                $batchWsp = floatval($d['wsp'] ?? $d['vendor_wsp'] ?? $lsItem->unit_price ?? 0);

                if (!isset($fifoQueue[$pid])) {
                    $fifoQueue[$pid] = [];
                }
                $fifoQueue[$pid][] = [
                    'live_sheet_id' => $ls->id,
                    'live_sheet_number' => $ls->live_sheet_number ?? '',
                    'vendor_wsp'    => $batchWsp,
                    'commission'    => $commPercent,
                    'remaining_qty' => intval($lsItem->quantity),
                ];
            }
        }

        return $fifoQueue;
    }
}
