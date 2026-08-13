<?php

namespace App\Services;

use App\Models\{VendorRateCard, VendorMonthlyCharge, Vendor, Grn, GrnItem, Inventory, Order, OrderItem, ActivityLog};
use Illuminate\Support\Facades\DB;

class VendorChargesService
{
    /**
     * Run monthly charges for a specific vendor (or all vendors)
     */
    public function runMonthlyCharges(int $month, int $year, ?int $vendorId = null, int $userId = 0, bool $dryRun = false): array
    {
        $vendors = $vendorId
            ? Vendor::where('id', $vendorId)->get()
            : Vendor::active()->get();

        $activeCompany = session('active_company');

        $results = ['created' => 0, 'skipped' => 0, 'errors' => [], 'details' => []];
        // $periodStart = now()->create(null, $month, 1)->startOfMonth()->toDateString();
        // $periodEnd = now()->create(null, $month, 1)->endOfMonth()->toDateString();

        $periodStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $periodEnd = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->toDateString();



        foreach ($vendors as $vendor) {
            $rateCard = VendorRateCard::where('company_code', $activeCompany)->getActive($vendor->id, $periodEnd);

            if (!$rateCard) {
                $results['skipped']++;
                $results['errors'][] = "{$vendor->company_name}: No approved rate card";
                continue;
            }

            $currency = match ($activeCompany ?: $vendor->company_code) {
                '2000' => 'INR',
                '2100' => 'EUR',
                '2200' => 'USD',
                '2400' => 'GBP',
                default => 'USD',
            };

            $vendorProductIds = $vendor->products()
                ->when($activeCompany, fn ($q) => $q->where('company_code', $activeCompany))
                ->pluck('id');

            // Get ALL GRNs with this vendor's products (for storage calculation)
            // $allGrns = Grn::whereHas('items', fn($q) => $q->whereIn('product_id', $vendorProductIds))
            //     ->with([
            //         'items' => fn($q) => $q->whereIn('product_id', $vendorProductIds)->with('product'),
            //         'shipment.consignments.liveSheet.items'
            //     ])
            //     ->get();

            $allGrns = Grn::withoutGlobalScopes()
                ->whereHas('items', fn ($q) => $q->withoutGlobalScopes()->whereIn('product_id', $vendorProductIds))
                ->with([
                    'items' => fn ($q) => $q->withoutGlobalScopes()
                        ->whereIn('product_id', $vendorProductIds)
                        ->with(['product' => fn ($pq) => $pq->withoutGlobalScopes()]),
                    'shipment.consignments.liveSheet.items'
                ])
                ->get();

            //             print_r($vendorProductIds);

            // // === PRINT SQL QUERY ===
            // $sql = $allGrns->toSql();
            // $bindings = $allGrns->getBindings();

            // dd([
            //     'sql'      => $sql,
            //     'bindings' => $bindings,
            //     'full_query' => vsprintf(str_replace('?', '%s', $sql), array_map(fn ($b) => "'$b'", $bindings))
            // ]);

            //           //  print_r($allGrns->toArray());
            //             exit;

            // Get GRNs received THIS month that haven't been inward-charged (for inward calculation)
            //             $newGrns = $allGrns->filter(function ($grn) use ($month, $year) {
            //                 if ($grn->inward_charged) return false;
            //                 if (!$grn->receipt_date) return false;
            //                 return $grn->receipt_date->month === $month && $grn->receipt_date->year === $year;
            //             });



            //  print_r($newGrns->toArray());
            //             exit;

            // $this->log("Vendor: {$vendor->company_name} | All GRNs: {$allGrns->count()} | New GRNs (inward): {$newGrns->count()}");

            // if ($allGrns->isEmpty()) {
            //     $results['skipped']++;
            //     $results['errors'][] = "No GRNs with vendor's products for {$vendor->company_name}";
            //     continue;
            // }

            foreach ($allGrns as $grn) {
                // Check if already calculated for this GRN + month
                $existing = VendorMonthlyCharge::where('vendor_id', $vendor->id)
                    ->where('grn_id', $grn->id)
                    ->byMonth($month, $year)
                    ->first();

                // if ($existing) {
                //     $results['skipped']++;
                //     continue;
                // }

                try {
                    //  $isNewGrn = $newGrns->contains('id', $grn->id);

                    $isNewGrn = $grn->receipt_date
                        && $grn->receipt_date->month === $month
                        && $grn->receipt_date->year === $year;

                    $charges = $this->calculateGrnCharges($vendor, $grn, $rateCard, $month, $year, $periodStart, $periodEnd, $vendorProductIds->toArray(), $isNewGrn, $activeCompany ?: $vendor->company_code);

                    // Skip if zero charges
                    if ($charges['total_charges'] <= 0) {
                        continue;
                    }

                    if (!$dryRun) {
                        DB::beginTransaction();
                        VendorMonthlyCharge::create([
                            'vendor_id'      => $vendor->id,
                            'grn_id'         => $grn->id,
                            'warehouse_id'   => $grn->warehouse_id,
                            'company_code'   => $activeCompany ?: $vendor->company_code,
                            'currency'       => $currency,
                            'charge_month'   => $month,
                            'charge_year'    => $year,
                            'rate_card_id'   => $rateCard->id,
                            'inward_cartons'           => $charges['inward_cartons'],
                            'inward_charge'            => $charges['inward_charge'],
                            'storage_remaining_qty'    => $charges['storage_remaining_qty'],
                            'storage_cft'              => $charges['storage_cft'],
                            'storage_charge'           => $charges['storage_charge'],
                            'fulfillment_orders_small' => $charges['fulfillment_orders_small'],
                            'fulfillment_orders_large' => $charges['fulfillment_orders_large'],
                            'fulfillment_charge'       => $charges['fulfillment_charge'],
                            'pick_pack_units'          => $charges['pick_pack_units'],
                            'pick_pack_charge'         => $charges['pick_pack_charge'],
                            'material_cost'            => $charges['material_cost'],
                            'total_charges'            => $charges['total_charges'],
                            'status'                   => 'calculated',
                            'created_by'               => $userId,
                            'calculation_snapshot'      => $charges['snapshot'],
                        ]);

                        // Mark GRN as inward-charged
                        if ($charges['inward_charge'] > 0 && !$grn->inward_charged) {
                            $grn->update(['inward_charged' => true]);
                        }

                        DB::commit();
                    }

                    $results['created']++;
                    $results['details'][] = [
                        'vendor'  => $vendor->company_name,
                        'grn'     => $grn->grn_number,
                        'inward'  => $charges['inward_charge'],
                        'storage' => $charges['storage_charge'],
                        'total'   => $charges['total_charges'],
                        'currency' => $currency,
                    ];
                } catch (\Exception $e) {
                    if (!$dryRun) {
                        DB::rollBack();
                    }
                    $this->log("ERROR: Vendor {$vendor->id} / GRN {$grn->id}: {$e->getMessage()}");
                    $results['errors'][] = "{$vendor->company_name} / {$grn->grn_number}: {$e->getMessage()}";
                }
            }
        }

        if (!$dryRun && $results['created'] > 0) {
            ActivityLog::log('calculated', 'vendor_monthly_charges', $vendor ?? new Vendor(), null, [
                'month' => $month,
                'year' => $year,
                'created' => $results['created'],
                'skipped' => $results['skipped'],
            ], "Vendor charges: {$results['created']} created, {$results['skipped']} skipped");
        }

        return $results;
    }

    /**
     * Calculate all charge heads for a vendor + GRN
     */
    private function calculateGrnCharges(
        Vendor $vendor,
        Grn $grn,
        VendorRateCard $rc,
        int $month,
        int $year,
        string $periodStart,
        string $periodEnd,
        array $vendorProductIds,
        bool $isNewGrn,
        string $companyCode = ''
    ): array {
        $grnItems = $grn->items->whereIn('product_id', $vendorProductIds);

        // ── 1. INWARD HANDLING (only for GRNs received THIS month) ──
        $inwardCartons = 0;
        $inwardCharge = 0;

        if ($isNewGrn) {
            $inwardCartons = $grnItems->sum('received_quantity');

            // Try to get carton count from live sheet (more accurate)
            if ($grn->shipment && $grn->shipment->consignments) {
                $liveSheetCartons = 0;
                foreach ($grn->shipment->consignments as $con) {
                    if (!$con->liveSheet) {
                        continue;
                    }
                    foreach ($con->liveSheet->items as $lsItem) {
                        if (!in_array($lsItem->product_id, $vendorProductIds)) {
                            continue;
                        }
                        $d = $lsItem->product_details ?? [];
                        $qtyPerCarton = floatval($d['qty_master_pack'] ?? $d['qty_per_carton'] ?? 0);
                        if ($qtyPerCarton > 0) {
                            $liveSheetCartons += ceil(floatval($lsItem->quantity) / $qtyPerCarton);
                        }
                    }
                }
                if ($liveSheetCartons > 0) {
                    $inwardCartons = $liveSheetCartons;
                }
            }

            $inwardCharge = round($inwardCartons * floatval($rc->inward_rate_per_carton ?? 0), 2);
            $this->log("INWARD GRN#{$grn->id}: {$inwardCartons} cartons x {$rc->inward_rate_per_carton} = {$inwardCharge}");
        }

        // ── 2. STORAGE (CFT based on opening inventory per GRN date rules) ──
        // Case 1: GRN before current month → remaining qty as of 1st of month
        // Case 2: GRN during current month → full received qty (treated as opening)
        $storageQty = 0;
        $storageCft = 0;
        $monthStart = \Carbon\Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $monthEnd = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        $activeCompany = session('active_company') ?? $vendor->company_code ?? $companyCode ?? '';

        // Preload live sheet item details for dimensions
        $liveSheetDetails = \App\Models\LiveSheetItem::whereIn('product_id', $vendorProductIds)
            ->whereHas('liveSheet', fn($q) => $q->withoutGlobalScopes()->where('company_code', $activeCompany))
            ->with(['product' => fn($q) => $q->withoutGlobalScopes()])
            ->latest()
            ->get()
            ->keyBy('product_id');

        $storageBreakdown = [];

        foreach ($grnItems as $grnItem) {
            $product = $grnItem->product;
            if (!$product) continue;

            $grnQty = floatval($grnItem->received_quantity);
            $grnDate = $grn->receipt_date ? $grn->receipt_date->format('Y-m-d') : null;

            // Determine opening qty based on GRN timing
            if (!$grnDate || $grnDate < $monthStart) {
                // CASE 1: GRN completed BEFORE current month
                // Storage = remaining qty as of 1st of the month
                $soldBeforeMonth = OrderItem::withoutGlobalScopes()
                    ->where('product_id', $product->id)
                    ->where('shipped_qty', '>', 0)
                    ->whereHas('order', fn($q) => $q->withoutGlobalScopes()
                        ->where('company_code', $activeCompany)
                        ->where('order_date', '<', $monthStart)
                        ->whereIn('status', ['shipped', 'delivered']))
                    ->sum('shipped_qty');

                $openingQty = max(0, $grnQty - $soldBeforeMonth);
                $storageCase = 'before_month';

            } elseif ($grnDate >= $monthStart && $grnDate <= $monthEnd) {
                // CASE 2: GRN completed DURING current month
                // Full received qty treated as opening CFT
                $openingQty = $grnQty;
                $storageCase = 'during_month';

            } else {
                // GRN is in a future month — no storage charge
                continue;
            }

            if ($openingQty <= 0) continue;

            // Get CFT from live sheet item's cbm_per_unit
            $lsItem = $liveSheetDetails[$product->id] ?? null;
            $cbmPerUnit = floatval($lsItem->cbm_per_unit ?? 0);
            $cftPerUnit = $cbmPerUnit * 35.3147;

            $itemCft = round($openingQty * $cftPerUnit, 4);
            $storageQty += $openingQty;
            $storageCft += $itemCft;

            $storageBreakdown[] = [
                'sku'         => $product->sku,
                'grn_date'    => $grnDate,
                'case'        => $storageCase,
                'grn_qty'     => intval($grnQty),
                'opening_qty' => intval($openingQty),
                'cft_per_unit'=> round($cftPerUnit, 6),
                'total_cft'   => $itemCft,
                 
            ];

            $this->log("Storage: SKU {$product->sku} | GRN: {$grnDate} | " .
                ($storageCase === 'before_month' ? 'Case 1 (before month)' : 'Case 2 (during month)') .
                " | GRN qty: {$grnQty} | Opening: {$openingQty} | CFT/unit: {$cftPerUnit} | Total CFT: {$itemCft}");
        }

        $storageCharge = round($storageCft * floatval($rc->storage_rate_per_cft ?? 0), 2);

        $this->log("Storage total: {$storageQty} units, {$storageCft} CFT × {$rc->storage_rate_per_cft} = {$storageCharge}");


        // ── 3. FULFILLMENT (threshold-based per order) ──
        $threshold = max(1, intval($rc->fulfillment_qty_threshold ?? 10));
        $vendorOrders = Order::where('order_date', '>=', $periodStart)
            ->where('order_date', '<=', $periodEnd)
            ->whereNotIn('status', ['cancelled'])
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', $vendorProductIds))
            ->with(['items' => fn ($q) => $q->whereIn('product_id', $vendorProductIds)])
            ->get();
        $fulfillSmall = 0;
        $fulfillLarge = 0;
        foreach ($vendorOrders as $order) {
            $vendorQty = $order->items->sum('shipped_qty') ?: $order->items->sum('quantity');
            if ($vendorQty <= $threshold) {
                $fulfillSmall++;
            } else {
                $fulfillLarge++;
            }
        }

        $fulfillCharge = round(
            ($fulfillSmall * floatval($rc->fulfillment_rate_small ?? 0)) +
                ($fulfillLarge * floatval($rc->fulfillment_rate_large ?? 0)),
            2
        );


        // ── 4. PICK & PACK (per unit shipped) ──
        $pickPackUnits = $vendorOrders->sum(fn ($o) => $o->items->sum('shipped_qty') ?: $o->items->sum('quantity'));
        $pickPackCharge = round($pickPackUnits * floatval($rc->pick_pack_rate_per_unit ?? 0), 2);

        // ── 5. MATERIAL COST ──
        $materialCost = round($vendorOrders->sum(fn ($o) => $o->items->sum('material_cost')), 2);


        // ── TOTAL ──
        $total = round($inwardCharge + $storageCharge + $fulfillCharge + $pickPackCharge + $materialCost, 2);



        $this->log("GRN#{$grn->id}: Inward={$inwardCharge}, Storage={$storageCharge} ({$storageCft} CFT), Fulfill={$fulfillCharge}, P&P={$pickPackCharge}, Material={$materialCost}, Total={$total}");

        return [
            'inward_cartons'           => $inwardCartons,
            'inward_charge'            => $inwardCharge,
            'storage_remaining_qty'    => $storageQty,
            'storage_cft'              => round($storageCft, 4),
            'storage_charge'           => $storageCharge,
            'storage_breakdown'        => $storageBreakdown ?? [],
            'fulfillment_orders_small' => $fulfillSmall,
            'fulfillment_orders_large' => $fulfillLarge,
            'fulfillment_charge'       => $fulfillCharge,
            'pick_pack_units'          => $pickPackUnits,
            'pick_pack_charge'         => $pickPackCharge,
            'material_cost'            => $materialCost,
            'total_charges'            => $total,
            'snapshot' => [
                'rate_card_version' => $rc->version ?? 1,
                'is_new_grn'       => $isNewGrn,
                'rates' => [
                    'inward'        => floatval($rc->inward_rate_per_carton ?? 0),
                    'storage'       => floatval($rc->storage_rate_per_cft ?? 0),
                    'fulfill_small' => floatval($rc->fulfillment_rate_small ?? 0),
                    'fulfill_large' => floatval($rc->fulfillment_rate_large ?? 0),
                    'threshold'     => $threshold,
                    'pick_pack'     => floatval($rc->pick_pack_rate_per_unit ?? 0),
                ],
                'calculated_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Generate vendor monthly statement
     */
    public function getVendorStatement(int $vendorId, int $month, int $year, string $companyCode): array
    {
        $vendor = Vendor::findOrFail($vendorId);
        $charges = VendorMonthlyCharge::where('vendor_id', $vendorId)
            ->byMonth($month, $year)
            ->with('grn', 'warehouse', 'rateCard')
            ->when($companyCode, fn ($q) => $q->where('company_code', $companyCode))
            ->where('status','approved')
            ->get();

        $grossPayout = \App\Models\VendorPayout::where('vendor_id', $vendorId)
            ->when($companyCode, fn ($q) => $q->where('company_code', $companyCode))
            ->where('payout_month', $month)
            ->where('payout_year', $year)
            ->sum('total_sales');

        $totalCharges = $charges->sum('total_charges');

        return [
            'vendor'       => $vendor,
            'period'       => date('M', mktime(0, 0, 0, $month, 1)) . ' ' . $year,
            'charges'      => $charges,
            'totals'       => [
                'inward'      => $charges->sum('inward_charge'),
                'storage'     => $charges->sum('storage_charge'),
                'fulfillment' => $charges->sum('fulfillment_charge'),
                'pick_pack'   => $charges->sum('pick_pack_charge'),
                'material'    => $charges->sum('material_cost'),
                'total'       => $totalCharges,
            ],
            'gross_payout' => floatval($grossPayout),
            'net_payout'   => floatval($grossPayout) - $totalCharges,
            'is_negative'  => (floatval($grossPayout) - $totalCharges) < 0,
            'currency'     => match ($companyCode) {
                '2000' => 'INR',
                '2100' => 'EUR',
                '2400' => 'GBP',
                default => 'USD'
            },
        ];
    }

    private function log(string $msg): void
    {
        \Log::channel('daily')->info("[VendorCharges] {$msg}");
    }
}