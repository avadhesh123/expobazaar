<?php

namespace App\Services;

use App\Models\{VendorRateCard, VendorMonthlyCharge, Vendor, Grn, GrnItem, Inventory, Order, OrderItem, ActivityLog};
use Illuminate\Support\Facades\DB;

class WarehouseChargesService
{
    /**
     * Run monthly charges for a specific vendor (or all vendors)
     * Returns array of created/skipped counts
     */
    public function runMonthlyCharges(int $month, int $year, ?int $vendorId = null, int $userId = 0, bool $dryRun = false): array
    {
        $vendors = $vendorId
            ? Vendor::where('id', $vendorId)->get()
            : Vendor::active()->get();

        $results = ['created' => 0, 'skipped' => 0, 'errors' => [], 'details' => []];
        $periodEnd = now()->create(null, $month, 1)->endOfMonth()->toDateString();
        $periodStart = now()->create(null, $month, 1)->startOfMonth()->toDateString();
        foreach ($vendors as $vendor) {
            $rateCard = VendorRateCard::getActive($vendor->id, $periodEnd);


            if (!$rateCard) {
                $results['skipped']++;
                $results['errors'][] = "{$vendor->company_name}: No approved rate card";
                continue;
            }

            $currency = $vendor->company_code === '2200' ? 'EUR' : 'USD';

            // Get all GRNs for this vendor (via products)
            $vendorProductIds = $vendor->products()->pluck('id');
            $grns = Grn::whereHas('items', fn($q) => $q->whereIn('product_id', $vendorProductIds))
                ->with(['items' => fn($q) => $q->whereIn('product_id', $vendorProductIds)])
                ->get();

            file_put_contents('storage/logs/warehouse_charges_errors.log', "Results1: " . json_encode($results) . "\n", FILE_APPEND);

            //  print_r($results);
            // print_r($grns->toArray());
            // print_r($vendorProductIds->toArray());
            //die("Debug: grns to process: " . $grns->toArray());

            if ($grns->isEmpty()) {
                $results['skipped']++;

                $results['errors'][] = "No GRNs with vendor's products  for {$vendor->company_name}";

                return $results;
                // continue;
            }
            file_put_contents('storage/logs/warehouse_charges_errors.log', "Results2: " . json_encode($grns) . "\n", FILE_APPEND);

            foreach ($grns as $grn) {
                // Check if already calculated
                $existing = VendorMonthlyCharge::where('vendor_id', $vendor->id)
                    ->where('grn_id', $grn->id)
                    ->byMonth($month, $year)
                    ->first();

                if ($existing) {
                    $results['skipped']++;
                    // continue;
                }

                file_put_contents('storage/logs/warehouse_charges_errors.log', "Results3: " . json_encode($existing) . "\n", FILE_APPEND);

                try {
                    $charges = $this->calculateGrnCharges($vendor, $grn, $rateCard, $month, $year, $periodStart, $periodEnd);

                    if (!$dryRun) {
                        DB::beginTransaction();
                        $record = VendorMonthlyCharge::create([
                            'vendor_id'      => $vendor->id,
                            'grn_id'         => $grn->id,
                            'warehouse_id'   => $grn->warehouse_id,
                            'company_code'   => $vendor->company_code,
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

                        // Mark GRN as inward-charged if not already
                        if ($charges['inward_charge'] > 0 && !$grn->inward_charged) {
                            $grn->update(['inward_charged' => true]);
                        }

                        DB::commit();
                    }


                    $results['created']++;
                    $results['details'][] = [
                        'vendor'   => $vendor->company_name,
                        'grn'      => $grn->grn_number,
                        'total'    => $charges['total_charges'],
                        'currency' => $currency,
                    ];

                    file_put_contents('storage/logs/warehouse_charges_errors.log', "Results4: " . json_encode($results) . "\n", FILE_APPEND);
                } catch (\Exception $e) {
                    file_put_contents(storage_path('logs/warehouse_charges_errors.log'), "Error processing Vendor ID {$vendor->id} / GRN ID {$grn->id}: {$e->getMessage()}\n", FILE_APPEND);
                    if (!$dryRun) DB::rollBack();
                    $results['errors'][] = "{$vendor->company_name} / {$grn->grn_number}: {$e->getMessage()}";
                }
            }
        }

        // if (!$dryRun) {
        //     ActivityLog::log('calculated', 'vendor_monthly_charges', Vendor, null, [
        //         'month' => $month, 'year' => $year,
        //         'created' => $results['created'], 'skipped' => $results['skipped'],
        //     ], "Monthly charges run: {$results['created']} created, {$results['skipped']} skipped");
        // }

        return $results;
    }

    /**
     * Calculate all 5 charge heads for a vendor + GRN combination
     */
    private function calculateGrnCharges(Vendor $vendor, Grn $grn, VendorRateCard $rc, int $month, int $year, string $periodStart, string $periodEnd): array
    {
        $vendorProductIds = $vendor->products()->pluck('id')->toArray();
        $grnItems = $grn->items->whereIn('product_id', $vendorProductIds);
        $sym = $rc->getCurrencySymbol();

        // ── 1. INWARD HANDLING ───────────────────────────────────────
        $inwardCartons = 0;
        $inwardCharge = 0;
        if (!$grn->inward_charged) {
            // Actual received cartons (not expected)
            $inwardCartons = $grnItems->sum('received_quantity');
            // If consignment has carton count, use that instead
            if ($grn->shipment && $grn->shipment->consignments) {
                $totalCartonsFromConsignment = $grn->shipment->consignments
                    ->sum(fn($c) => $c->liveSheet?->items?->sum(
                        fn($i) =>
                        in_array($i->product_id, $vendorProductIds)
                            ? ceil(floatval($i->quantity) / max(1, floatval($i->product_details['qty_master_pack'] ?? 1)))
                            : 0
                    ) ?? 0);
                if ($totalCartonsFromConsignment > 0) {
                    $inwardCartons = $totalCartonsFromConsignment;
                }
            }
            $inwardCharge = round($inwardCartons * floatval($rc->inward_rate_per_carton), 2);
        }

        // ── 2. STORAGE ───────────────────────────────────────────────
        $storageQty = 0;
        $storageCft = 0;
        $storageCharge = 0;

        foreach ($grnItems as $grnItem) {
            $product = $grnItem->product;
            //    echo '<pre>'; print_r($grnItem->toArray());
            //      print_r($product->toArray());exit;
            //      echo '</pre>';
            if (!$product) continue;

            $grnQty = floatval($grnItem->received_quantity);

            // Cumulative sold up to end of previous month
            $prevMonthEnd = now()->create(null, $month, 1)->subDay()->toDateString();
            $soldQty = OrderItem::where('product_id', $product->id)
                ->whereHas('order', fn($q) => $q->where('order_date', '<=', $prevMonthEnd)
                    ->whereNotIn('status', ['cancelled']))
                ->sum('quantity');

            // Returned qty (added back)
            $returnedQty = 0; // TODO: Pull from returns module when implemented

            $remaining = max(0, $grnQty + $returnedQty - $soldQty);
            // $cftPerUnit = floatval($product->cft_per_unit ?? 0);
            $cftPerUnit = floatval((($product->length * $product->width * $product->height) / 61024) * 35.3147 ?? 0);
            $skuCft = $remaining * $cftPerUnit;

            $storageQty += $remaining;
            $storageCft += $skuCft;
            
file_put_contents('storage/logs/warehouse_charges.log', "sku: {$product->sku}, remaining: {$remaining}, cftPerUnit: {$cftPerUnit}\n", FILE_APPEND);

        }

        $storageCharge = round($storageCft * floatval($rc->storage_rate_per_cft), 2);
file_put_contents('storage/logs/warehouse_charges.log', "Storage - Qty: {$storageQty}, CFT: {$storageCft}, Charge: {$storageCharge}\n", FILE_APPEND);
        // ── 3. FULFILLMENT ───────────────────────────────────────────
        $fulfillSmall = 0;
        $fulfillLarge = 0;
        $fulfillCharge = 0;
        $threshold = max(1, intval($rc->fulfillment_qty_threshold));

        // Orders in this month containing this vendor's products
        $vendorOrders = Order::where('order_date', '>=', $periodStart)
            ->where('order_date', '<=', $periodEnd)
            ->whereNotIn('status', ['cancelled'])
            ->whereHas('items', fn($q) => $q->whereIn('product_id', $vendorProductIds))
            ->with(['items' => fn($q) => $q->whereIn('product_id', $vendorProductIds)])
            ->get();

        foreach ($vendorOrders as $order) {
            $vendorQtyInOrder = $order->items->sum('quantity');
            if ($vendorQtyInOrder <= $threshold) {
                $fulfillSmall++;
            } else {
                $fulfillLarge++;
            }
        }

        $fulfillCharge = round(
            ($fulfillSmall * floatval($rc->fulfillment_rate_small)) +
                ($fulfillLarge * floatval($rc->fulfillment_rate_large)),
            2
        );

        // ── 4. PICK & PACK ───────────────────────────────────────────
        $pickPackUnits = $vendorOrders->sum(fn($o) => $o->items->sum('quantity'));
        $pickPackCharge = round($pickPackUnits * floatval($rc->pick_pack_rate_per_unit), 2);

        // ── 5. MATERIAL COST ─────────────────────────────────────────
        $materialCost = 0;
        foreach ($vendorOrders as $order) {
            foreach ($order->items as $item) {
                $materialCost += floatval($item->material_cost ?? 0);
            }
        }
        $materialCost = round($materialCost, 2);

        // ── TOTAL ────────────────────────────────────────────────────
        $total = $inwardCharge + $storageCharge + $fulfillCharge + $pickPackCharge + $materialCost;

        return [
            'inward_cartons'           => $inwardCartons,
            'inward_charge'            => $inwardCharge,
            'storage_remaining_qty'    => $storageQty,
            'storage_cft'              => round($storageCft, 4),
            'storage_charge'           => $storageCharge,
            'fulfillment_orders_small' => $fulfillSmall,
            'fulfillment_orders_large' => $fulfillLarge,
            'fulfillment_charge'       => $fulfillCharge,
            'pick_pack_units'          => $pickPackUnits,
            'pick_pack_charge'         => $pickPackCharge,
            'material_cost'            => $materialCost,
            'total_charges'            => round($total, 2),
            'snapshot' => [
                'rate_card_version' => $rc->version,
                'rates' => [
                    'inward' => $rc->inward_rate_per_carton,
                    'storage' => $rc->storage_rate_per_cft,
                    'fulfill_small' => $rc->fulfillment_rate_small,
                    'fulfill_large' => $rc->fulfillment_rate_large,
                    'threshold' => $rc->fulfillment_qty_threshold,
                    'pick_pack' => $rc->pick_pack_rate_per_unit,
                ],
                'calculated_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Generate vendor monthly statement
     */
    public function getVendorStatement(int $vendorId, int $month, int $year): array
    {
        $vendor = Vendor::findOrFail($vendorId);
        $charges = VendorMonthlyCharge::where('vendor_id', $vendorId)
            ->byMonth($month, $year)
            ->with('grn', 'warehouse', 'rateCard')
            ->get();

        $grossPayout = \App\Models\VendorPayout::where('vendor_id', $vendorId)
            ->where('payout_month', $month)
            ->where('payout_year', $year)
            ->sum('total_sales');

        $totalCharges = $charges->sum('total_charges');

        return [
            'vendor'         => $vendor,
            'period'         => date('M', mktime(0, 0, 0, $month, 1)) . ' ' . $year,
            'charges'        => $charges,
            'totals'         => [
                'inward'      => $charges->sum('inward_charge'),
                'storage'     => $charges->sum('storage_charge'),
                'fulfillment' => $charges->sum('fulfillment_charge'),
                'pick_pack'   => $charges->sum('pick_pack_charge'),
                'material'    => $charges->sum('material_cost'),
                'total'       => $totalCharges,
            ],
            'gross_payout'   => floatval($grossPayout),
            'net_payout'     => floatval($grossPayout) - $totalCharges,
            'is_negative'    => (floatval($grossPayout) - $totalCharges) < 0,
            'currency'       => $vendor->company_code === '2200' ? 'EUR' : 'USD',
        ];
    }
}
