<?php

namespace App\Services;

use App\Models\{WarehouseRateCard, WarehouseMonthlyCharge, WarehouseChargeGrnDetail, Warehouse, Grn, GrnItem, Inventory, Order, OrderItem, Product, ActivityLog};
use Illuminate\Support\Facades\DB;

class WarehouseChargeCalculationService
{
    public function calculateMonthlyCharges(int $warehouseId, int $month, int $year, int $userId, bool $dryRun = false): array
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $rateCard = WarehouseRateCard::getActive($warehouseId);
        //  $currency = $warehouse->company_code === '2200' ? 'EUR' : 'USD';
        $currency = match ($warehouse->company_code) {
            '2000' => 'INR',
            '2100' => 'EUR',
            '2200' => 'USD',
            default => 'USD'   // fallback
        };

        // $dryRun = true;
        if (!$rateCard) {
            return ['success' => false, 'error' => 'No approved rate card for this warehouse.'];
        }

        // Check if already calculated
        $existing = WarehouseMonthlyCharge::where('warehouse_id', $warehouseId)->byMonth($month, $year)->first();
        if ($existing && !$dryRun) {
            return ['success' => false, 'error' => "Charges already calculated for {$existing->period}. Status: {$existing->status}"];
        }

        $periodStart = now()->create(null, $month, 1)->startOfMonth()->toDateString();
        $periodEnd   = now()->create(null, $month, 1)->endOfMonth()->toDateString();
        $prevMonthEnd = now()->create(null, $month, 1)->subDay()->toDateString();

        // Get all GRNs for this warehouse
        $grns = Grn::with('items.product.vendor')
            ->where('warehouse_id', $warehouseId)
            ->get();

        $totals = ['inward' => 0, 'storage' => 0, 'fulfillment' => 0, 'pick_pack' => 0];
        $grnDetails = [];

        foreach ($grns as $grn) {
            $detail = $this->calculateGrnCharges($grn, $rateCard, $month, $year, $periodStart, $periodEnd, $prevMonthEnd);
            $grnDetails[] = $detail;
            $totals['inward']      += $detail['inward_charge'];
            $totals['storage']     += $detail['storage_charge'];
            $totals['fulfillment'] += $detail['fulfillment_charge'];
            $totals['pick_pack']   += $detail['pick_pack_charge'];
        }

        $expectedTotal = round($totals['inward'] + $totals['storage'] + $totals['fulfillment'] + $totals['pick_pack'], 2);

        file_put_contents('storage/logs/warehouse_charges_calc.log', 'totals: ' . print_r($totals, true) . "\n", FILE_APPEND);

        if ($dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'expected' => $totals,
                'expected_total' => $expectedTotal,
                'grn_count' => count($grnDetails),
                'currency' => $currency,
            ];
        }

        try {
            DB::beginTransaction();

            $monthlyCharge = WarehouseMonthlyCharge::create([
                'warehouse_id'       => $warehouseId,
                'company_code'       => $warehouse->company_code,
                'currency'           => $currency,
                'charge_month'       => $month,
                'charge_year'        => $year,
                'rate_card_id'       => $rateCard->id,
                'expected_inward'    => round($totals['inward'], 2),
                'expected_storage'   => round($totals['storage'], 2),
                'expected_fulfillment' => round($totals['fulfillment'], 2),
                'expected_pick_pack' => round($totals['pick_pack'], 2),
                'expected_total'     => $expectedTotal,
                'status'             => 'calculated',
                'calculated_by'      => $userId,
                'calculated_at'      => now(),
                'calculation_snapshot' => [
                    'rate_card_version' => $rateCard->version,
                    'rates' => $rateCard->only(['wh_inward_rate_per_carton', 'wh_storage_rate_per_cft', 'wh_fulfillment_rate_small', 'wh_fulfillment_rate_large', 'wh_fulfillment_qty_threshold', 'wh_pick_pack_rate_per_unit']),
                    'grn_count' => count($grnDetails),
                    'calculated_at' => now()->toISOString(),
                ],
            ]);

            foreach ($grnDetails as $d) {
                WarehouseChargeGrnDetail::create(array_merge($d, [
                    'warehouse_monthly_charge_id' => $monthlyCharge->id,
                ]));

                // Mark GRN as inward-charged
                if ($d['inward_charge'] > 0) {
                    Grn::where('id', $d['grn_id'])->update(['inward_charged' => true]);
                }
            }

            DB::commit();

            ActivityLog::log('calculated', 'warehouse_monthly_charges', $monthlyCharge, null, [
                'warehouse' => $warehouse->name,
                'month' => $month,
                'year' => $year,
                'expected_total' => $expectedTotal,
            ], "Warehouse charges calculated for {$warehouse->name} — {$monthlyCharge->period}");

            return ['success' => true, 'charge_id' => $monthlyCharge->id, 'expected_total' => $expectedTotal, 'grn_count' => count($grnDetails)];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Warehouse charge calc failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function calculateGrnCharges(Grn $grn, WarehouseRateCard $rc, int $month, int $year, string $periodStart, string $periodEnd, string $prevMonthEnd): array
    {
        $grnItems = $grn->items;

        file_put_contents('storage/logs/warehouse_charges_calc.log', "\n=====================+" . date("Y-m-d H:i:s") . "+================\n\n", FILE_APPEND);

        // 1. INWARD — one-time, only if not already charged
        $inwardCartons = 0;
        $inwardCharge = 0;
        if (!$grn->inward_charged) {

            // Only charge inward in the month the GRN was received
            $grnMonth = $grn->receipt_date ? $grn->receipt_date->month : null;
            $grnYear = $grn->receipt_date ? $grn->receipt_date->year : null;

            $inwardCartons = $grnItems->sum('received_quantity');

            file_put_contents('storage/logs/warehouse_charges_calc.log', 'inwardCartons: ' . $inwardCartons . "\n", FILE_APPEND);

            $totalCartonsFromConsignment = 0;
            if ($grnMonth === $month && $grnYear === $year) {
                // Calculate cartons from live sheet items (qty / qty_master_pack)
                if ($grn->shipment && $grn->shipment->consignments) {
                    foreach ($grn->shipment->consignments as $c) {
                        if (!$c->liveSheet) {
                            continue;
                        }
                        foreach ($c->liveSheet->items as $i) {


                            $qty = floatval($i->quantity);
                            $d = $i->product_details ?? [];
                            $qtyPerCarton = floatval($d['qty_master_pack'] ?? $d['qty_per_carton'] ?? 0);
                            $totalCartonsFromConsignment += $qtyPerCarton > 0 ? ceil($qty / $qtyPerCarton) : 0;

                            file_put_contents('storage/logs/warehouse_charges_calc.log', 'Live Sheet Id: ' . $c->liveSheet->id . '_' . $i->live_sheet_id . ', quantity: ' . $i->quantity . ', product_details: ' . json_encode($d) . ', qtyPerCarton: ' . $qtyPerCarton . "\n", FILE_APPEND);
                        }
                    }
                }

                file_put_contents('storage/logs/warehouse_charges_calc.log', 'totalCartonsFromConsignment: ' . $totalCartonsFromConsignment . "\n", FILE_APPEND);

                if ($totalCartonsFromConsignment > 0) {
                    $inwardCartons = $totalCartonsFromConsignment;
                }
                $inwardCharge = round($inwardCartons * floatval($rc->wh_inward_rate_per_carton), 2);
            }
        } else {
            file_put_contents('storage/logs/warehouse_charges_calc.log', 'grn->inward_charged: ' . $grn->inward_charged . "\n", FILE_APPEND);
         }

        file_put_contents('storage/logs/warehouse_charges_calc.log', "Results1: " . 'inwardCartons: ' . $inwardCartons . ', wh_inward_rate_per_carton: ' . $rc->wh_inward_rate_per_carton . ', inwardCharge: ' . $inwardCharge . "\n", FILE_APPEND);

        // 2. STORAGE — remaining qty × CFT × rate
        $storageQty = 0;
        $storageCft = 0;
        foreach ($grnItems as $item) {
            if (!$item->product) continue;
            $grnQty = floatval($item->received_quantity);
            $soldQty = OrderItem::where('product_id', $item->product_id)
                ->whereHas('order', fn($q) => $q->where('order_date', '<=', $prevMonthEnd)->whereNotIn('status', ['cancelled']))
                ->sum('quantity');
            $remaining = max(0, $grnQty - $soldQty);
            //cft_per_unit -> no need to pick this value from product table. It will be calcuated from product dimentions.
            //  $cft = floatval($item->product->cft_per_unit ?? '0.6000');
            $cft = floatval((($item->product->length * $item->product->width * $item->product->height) / 61024) * 35.3147 ?? 0);
            $storageQty += $remaining;
            $storageCft += $remaining * $cft;

            // file_put_contents('storage/logs/warehouse_charges_calc.log', "item: " . print_r($item->product, true)  .    "\n", FILE_APPEND);

            file_put_contents('storage/logs/warehouse_charges_calc.log', "Results2: " . 'Sku: ' . $item->product->sku . ', grnQty: ' . $grnQty . ', soldQty: ' . $soldQty . ', remaining: ' . $remaining . ', cft: ' . $cft .    "\n", FILE_APPEND);
        }
        $storageCharge = round($storageCft * floatval($rc->wh_storage_rate_per_cft), 2);


        // 3. FULFILLMENT — per order in this month
        $threshold = max(1, $rc->wh_fulfillment_qty_threshold);
        $productIds = $grnItems->pluck('product_id')->filter()->toArray();
        $orders = Order::where('order_date', '>=', $periodStart)->where('order_date', '<=', $periodEnd)
            ->whereNotIn('status', ['cancelled'])
            ->whereHas('items', fn($q) => $q->whereIn('product_id', $productIds))
            ->with(['items' => fn($q) => $q->whereIn('product_id', $productIds)])->get();

        $fulfillSmall = 0;
        $fulfillLarge = 0;
        foreach ($orders as $order) {
            $qty = $order->items->sum('quantity');
            if ($qty <= $threshold) $fulfillSmall++;
            else $fulfillLarge++;
        }
        $fulfillCharge = round(($fulfillSmall * floatval($rc->wh_fulfillment_rate_small)) + ($fulfillLarge * floatval($rc->wh_fulfillment_rate_large)), 2);

        file_put_contents('storage/logs/warehouse_charges_calc.log', "Results3: " . 'storageCharge: ' . $storageCharge . ', fulfillCharge: ' . $fulfillCharge . ', fulfillSmall: ' . $fulfillSmall . ', fulfillLarge: ' . $fulfillLarge . "\n", FILE_APPEND);

        // 4. PICK & PACK — per unit sold
        $pickPackUnits = $orders->sum(fn($o) => $o->items->sum('quantity'));
        $pickPackCharge = round($pickPackUnits * floatval($rc->wh_pick_pack_rate_per_unit), 2);

        $total = $inwardCharge + $storageCharge + $fulfillCharge + $pickPackCharge;

        file_put_contents('storage/logs/warehouse_charges_calc.log', "Results4: " . 'pickPackUnits: ' . $pickPackUnits . ', wh_pick_pack_rate_per_unit: ' . $rc->wh_pick_pack_rate_per_unit . ', pickPackCharge: ' . $pickPackCharge . ', total: ' . $total . "\n", FILE_APPEND);


        $finalData = [
            'grn_id' => $grn->id,
            'inward_cartons' => $inwardCartons,
            'inward_charge' => $inwardCharge,
            'storage_qty' => $storageQty,
            'storage_cft' => round($storageCft, 4),
            'storage_charge' => $storageCharge,
            'fulfillment_small_count' => $fulfillSmall,
            'fulfillment_large_count' => $fulfillLarge,
            'fulfillment_charge' => $fulfillCharge,
            'pick_pack_units' => $pickPackUnits,
            'pick_pack_charge' => $pickPackCharge,
            'total_charge' => $total,
        ];
        file_put_contents('storage/logs/warehouse_charges_calc.log', "Results5: " . json_encode($finalData) . "\n", FILE_APPEND);

        return $finalData;
    }
}
