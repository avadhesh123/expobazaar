<?php

namespace App\Services;

use App\Models\{WarehouseRateCard, WarehouseMonthlyCharge, Warehouse, Grn, Order, Shipment, ActivityLog};
use Illuminate\Support\Facades\DB;

class WarehouseChargeCalculationService
{
    public function calculateMonthlyCharges(int $warehouseId, int $month, int $year, int $userId, bool $dryRun = false): array
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $rateCard = WarehouseRateCard::where('warehouse_id', $warehouseId)
            ->where('status', 'approved')
            ->orderByDesc('effective_from')
            ->first();

        $this->log("\n=== Calculating {$warehouse->name} — {$month}/{$year} ===" . "\n");

        $currency = match ($warehouse->company_code) {
            '2000' => 'INR',
            '2200' => 'EUR',
            '2400' => 'GBP',
            default => 'USD',
        };

        if (!$rateCard) {
            return ['success' => false, 'error' => 'No approved rate card for this warehouse.'];
        }

        $existing = WarehouseMonthlyCharge::where('warehouse_id', $warehouseId)->byMonth($month, $year)->first();
        if ($existing && !$dryRun) {
            return ['success' => false, 'error' => "Charges already calculated for {$existing->period}. Status: {$existing->status}"];
        }

        $periodStart = now()->create(null, $month, 1)->startOfMonth();
        $periodEnd   = now()->create(null, $month, 1)->endOfMonth();

        $this->log("===  {$warehouse->name} — {$month}/{$year} ===");

        $unloading       = $this->calculateUnloading($warehouseId, $rateCard, $month, $year);
        $putaway          = $this->calculatePutaway($warehouseId, $rateCard, $month, $year);
        $storage          = $this->calculateStorage($warehouseId, $rateCard, $month, $year);
        $orderProcessing  = $this->calculateOrderProcessing($warehouseId, $rateCard, $periodStart, $periodEnd);
        $pickPack         = $this->calculatePickPack($warehouseId, $rateCard, $periodStart, $periodEnd);
        $returnInward     = $this->calculateReturnInward($warehouseId, $rateCard, $periodStart, $periodEnd);

        $totals = [
            'unloading'        => $unloading['charge'],
            'putaway'          => $putaway['charge'],
            'storage'          => $storage['charge'],
            'order_processing' => $orderProcessing['charge'],
            'pick_pack'        => $pickPack['charge'],
            'return_inward'    => $returnInward['charge'],
        ];
        $expectedTotal = round(array_sum($totals), 2);

        $this->log("TOTALS: " . json_encode($totals) . " = {$expectedTotal}");

        if ($dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'expected' => $totals,
                'expected_total' => $expectedTotal,
                'currency' => $currency,
                'details' => compact('unloading', 'putaway', 'storage', 'orderProcessing', 'pickPack', 'returnInward'),
            ];
        }

        try {
            DB::beginTransaction();

            $monthlyCharge = WarehouseMonthlyCharge::create([
                'warehouse_id'              => $warehouseId,
                'company_code'              => $warehouse->company_code,
                'currency'                  => $currency,
                'charge_month'              => $month,
                'charge_year'               => $year,
                'rate_card_id'              => $rateCard->id,
                'expected_unloading'        => round($totals['unloading'], 2),
                'expected_putaway'          => round($totals['putaway'], 2),
                'expected_inward'           => round($totals['unloading'] + $totals['putaway'], 2),
                'expected_storage'          => round($totals['storage'], 2),
                'expected_order_processing' => round($totals['order_processing'], 2),
                'expected_fulfillment'      => round($totals['order_processing'], 2),
                'expected_pick_pack'        => round($totals['pick_pack'], 2),
                'expected_return_inward'    => round($totals['return_inward'], 2),
                'expected_total'            => $expectedTotal,
                'status'                    => 'calculated',
                'calculated_by'             => $userId,
                'calculated_at'             => now(),
                'calculation_snapshot'       => [
                    'rates' => $rateCard->only([
                        'unloading_fcl_rate',
                        'unloading_lcl_palletize',
                        'unloading_carton',
                        'putaway_per_carton',
                        'storage_per_pallet',
                        'order_processing_palletize',
                        'order_processing_non_palletize',
                        'pick_pack_palletize',
                        'pick_pack_non_palletize',
                        'return_inward_per_qty',
                    ]),
                    'details' => compact('unloading', 'putaway', 'storage', 'orderProcessing', 'pickPack', 'returnInward'),
                    'calculated_at' => now()->toISOString(),
                ],
            ]);

            if (!empty($unloading['grn_ids'])) {
                Grn::whereIn('id', $unloading['grn_ids'])->update(['inward_charged' => true]);
            }

            DB::commit();

            ActivityLog::log('calculated', 'warehouse_monthly_charges', $monthlyCharge, null, [
                'warehouse' => $warehouse->name,
                'month' => $month,
                'year' => $year,
                'expected_total' => $expectedTotal,
            ], "Warehouse charges: {$warehouse->name} — {$month}/{$year}: {$currency} {$expectedTotal}");

            return ['success' => true, 'charge_id' => $monthlyCharge->id, 'expected_total' => $expectedTotal, 'details' => $totals];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Warehouse charge calc failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * UNLOADING — one-time per GRN, based on shipment type
     * FCL: No of GRNs × unloading_fcl_rate
     * LCL: No of pallets × unloading_lcl_palletize
     * AIR: No of cartons × unloading_carton
     */
    private function calculateUnloading(int $warehouseId, $rc, int $month, int $year): array
    {
        // $grns = Grn::where('warehouse_id', $warehouseId)
        //     ->where(function($q) { $q->where('inward_charged', false)->orWhereNull('inward_charged'); })
        //     ->whereMonth('receipt_date', $month)
        //     ->whereYear('receipt_date', $year)
        //     ->with('shipment')
        //     ->get();

        $grns = Grn::where('warehouse_id', $warehouseId)
            ->where(function ($q) {
                $q->where('inward_charged', false)->orWhereNull('inward_charged');
            })
            ->whereMonth('receipt_date', $month)
            ->whereYear('receipt_date', $year)
            ->with('shipment.consignments.liveSheet.items')  // added for AIR carton count
            ->get();


        $totalCharge = 0;
        $breakdown = [];
        $grnIds = [];

        foreach ($grns as $grn) {
            $shipment = $grn->shipment;
            $type = strtoupper($shipment->shipment_type ?? 'LCL');
            $charge = 0;
            $qty = 0;
            $rate = 0;
            $unit = '';

            switch ($type) {
                case 'FCL':
                    $qty = 1;
                    $rate = floatval($rc->unloading_fcl ?? 0);
                    $unit = 'GRN';
                    break;
                case 'LCL':
                    $qty = intval($shipment->no_of_pallets ?? 0);
                    $rate = floatval($rc->unloading_lcl_palletize ?? 0);
                    $unit = 'pallets';
                    break;
                case 'AIR':
                    // Get master cartons from live sheet items
                    $qty = 0;
                    foreach ($grn->shipment->consignments ?? [] as $con) {
                        if (!$con->liveSheet) continue;
                        foreach ($con->liveSheet->items as $item) {
                            $d = $item->product_details ?? [];
                            $qty += intval($d['qty_master_pack'] ?? $d['no_of_master_cartons'] ?? 0);
                        }
                    }
                    $rate = floatval($rc->unloading_carton ?? 0);
                    $unit = 'master cartons';
                    break;
                    // case 'AIR':
                    //     $qty = intval($shipment->no_of_cartons ?? 0);
                    //     $rate = floatval($rc->unloading_carton ?? 0);
                    //     $unit = 'cartons';
                    //     break;
            }

            $charge = round($qty * $rate, 2);
            $totalCharge += $charge;
            $grnIds[] = $grn->id;
            $breakdown[] = ['grn_id' => $grn->id, 'type' => $type, 'qty' => $qty, 'rate' => $rate, 'unit' => $unit, 'charge' => $charge];

            $this->log("UNLOADING GRN#{$grn->id}: {$type} - Qty:{$qty} Unit:{$unit} X Rate{$rate} = Charge{$charge}");
        }

        $this->log("UNLOADING GRNs ==== " . json_encode([
            'total_charge' => round($totalCharge ?? 0, 2),
            'grn_count'    => count($grns),
            'grn_ids'      => $grnIds ?? [],
            'breakdown'    => $breakdown ?? []
        ], JSON_PRETTY_PRINT));

        return ['charge' => round($totalCharge, 2), 'grn_count' => count($grns), 'grn_ids' => $grnIds, 'breakdown' => $breakdown];
    }

    /**
     * PUTAWAY — putaway_per_carton × total master cartons from live sheet items
     */
    private function calculatePutaway(int $warehouseId, $rc, int $month, int $year): array
    {
        $rate = floatval($rc->putaway_per_carton ?? 0);

        $grns = Grn::where('warehouse_id', $warehouseId)
            ->where(function ($q) {
                $q->where('inward_charged', false)->orWhereNull('inward_charged');
            })
            ->whereMonth('receipt_date', $month)
            ->whereYear('receipt_date', $year)
            ->with('shipment.consignments.liveSheet.items')
            ->get();

        $totalCartons = 0;
        foreach ($grns as $grn) {
            if (!$grn->shipment) continue;
            foreach ($grn->shipment->consignments ?? [] as $con) {
                if (!$con->liveSheet) continue;
                foreach ($con->liveSheet->items as $item) {
                    $d = $item->product_details ?? [];
                    $totalCartons += intval($d['qty_master_pack'] ?? $d['no_of_master_cartons'] ?? 0);
                }
            }
        }

        $charge = round($rate * $totalCartons, 2);
        $this->log("PUTAWAY: {$totalCartons} cartons x {$rate} rate = charge {$charge}");

        return ['charge' => $charge, 'total_cartons' => $totalCartons, 'rate' => $rate];
    }

    /**
     * STORAGE — storage_per_pallet × monthly average pallets
     * Uses WarehousePalletLog daily entries, weighted average
     */
    private function calculateStorage(int $warehouseId, $rc, int $month, int $year): array
    {
        $rate = floatval($rc->storage_per_pallet ?? 0) * 4.25;

        $palletLogs = \App\Models\WarehousePalletLog::where('warehouse_id', $warehouseId)
            ->whereMonth('entry_date', $month)
            ->whereYear('entry_date', $year)
            ->orderBy('entry_date')
            ->get();

        $daysInMonth = now()->create(null, $month, 1)->daysInMonth;

        if ($palletLogs->isEmpty()) {
            $warehouse = Warehouse::find($warehouseId);
            $avgPallets = intval($warehouse->no_of_pallets ?? 0);
            $method = 'fallback';
        } else {
            $lastKnown = 0;
            $dailyTotal = 0;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                // $entry = $palletLogs->firstWhere('entry_date', $date);

                $entry = $palletLogs->first(fn($log) => $log->entry_date->format('Y-m-d') === $date);

                if ($entry) $lastKnown = $entry->no_of_pallets;
                $dailyTotal += $lastKnown;
                // if ($entry) {
                //     $this->log(json_encode([
                //         'date' => $date,
                //         'daily_total' => $dailyTotal,
                //         'entry'       => $entry->toArray()
                //     ], JSON_PRETTY_PRINT));
                // } else {

                //     $this->log("\n No palletLogs === " . $date . "\n");
                // }
            }
            $avgPallets = round($dailyTotal / $daysInMonth, 2);
            $method = 'daily_average';

            $this->log("\n daysInMonth === " . $daysInMonth . "\n");
        }

        $charge = round($rate * $avgPallets, 2);
        $this->log("STORAGE: avg {$avgPallets} pallets x rate {$rate} = charge {$charge} (method {$method}, {$palletLogs->count()} entries)");

        return ['charge' => $charge, 'avg_pallets' => $avgPallets, 'rate' => $rate, 'method' => $method];
    }

    /**
     * ORDER PROCESSING
     * Palletize: order_processing_palletize × qty of palletize orders
     * Non-palletize: order_processing_non_palletize × no of non-palletize orders
     */
    private function calculateOrderProcessing(int $warehouseId, $rc, $periodStart, $periodEnd): array
    {
        $orders = Order::where('warehouse_id', $warehouseId)
            ->whereBetween('order_date', [$periodStart, $periodEnd])
            ->whereNotIn('status', ['cancelled'])
            ->where('shipping_method', '!=', 'sp')
            ->with('items')
            ->get();

        $palletize = $orders->where('order_pack_type', 'palletize');
        $nonPalletize = $orders->where('order_pack_type', '!=', 'palletize');

        $pQty = $palletize->sum(fn($o) => $o->items->sum('quantity'));
        $pRate = floatval($rc->order_processing_palletize ?? 0);
        $pCharge = round($pRate * $pQty, 2);

        $npCount = $nonPalletize->count();
        $npRate = floatval($rc->order_processing_non_palletize ?? 0);
        $npCharge = round($npRate * $npCount, 2);

        $charge = $pCharge + $npCharge;
        $this->log("ORDER PROC: Palletize {$pQty}qty x {$pRate} Rate = {$pCharge} | Non-Pall {$npCount}orders x {$npRate} = NPcharge {$npCharge}");

        return [
            'charge' => round($charge, 2),
            'palletize_qty' => $pQty,
            'palletize_rate' => $pRate,
            'palletize_charge' => $pCharge,
            'non_palletize_count' => $npCount,
            'non_palletize_rate' => $npRate,
            'non_palletize_charge' => $npCharge,
        ];
    }

    /**
     * PICK & PACK
     * Palletize: pick_pack_palletize × qty of palletize orders
     * Non-palletize: pick_pack_non_palletize × no of non-palletize orders
     */
    private function calculatePickPack(int $warehouseId, $rc, $periodStart, $periodEnd): array
    {
        $orders = Order::where('warehouse_id', $warehouseId)
            ->whereBetween('order_date', [$periodStart, $periodEnd])
            ->whereNotIn('status', ['cancelled'])
            ->where('shipping_method', '!=', 'sp')
            ->with('items')
            ->get();

        $palletize = $orders->where('order_pack_type', 'palletize');
        $nonPalletize = $orders->where('order_pack_type', '!=', 'palletize');
        $pQty = $palletize->sum(fn($o) => $o->items->sum('quantity'));
        $pRate = 0; //floatval($rc->pick_pack ?? 0);
        $pCharge = round($pRate * $pQty, 2);

        //pick_pack only applicable for non palletize
        $nonPalletize = $orders->where('order_pack_type', '!=', 'palletize');
        $npQty = $nonPalletize->sum(fn($o) => $o->items->sum('quantity'));
        $npRate = floatval($rc->pick_pack ?? 0);
        $npCharge = round($npRate * $npQty, 2);

        $charge = $pCharge + $npCharge;
        $this->log("PICK-PACK: Palletize {$pQty}qty x {$pRate} = {$pCharge} | Non-Pall {$npQty} Qty x {$npRate} = {$npCharge}");

        return [
            'charge' => round($charge, 2),
            'palletize_qty' => $pQty,
            'palletize_rate' => $pRate,
            'palletize_charge' => $pCharge,
            'non_palletize_qty' => $npQty,
            'non_palletize_rate' => $npRate,
            'non_palletize_charge' => $npCharge,
        ];
    }

    /**
     * RETURN INWARD — return_inward_per_qty × qty of returned items
     */
    private function calculateReturnInward(int $warehouseId, $rc, $periodStart, $periodEnd): array
    {
        $rate = floatval($rc->return_inward_per_qty ?? 0);

        $returnQty = \App\Models\OrderReturnItem::whereHas('orderReturn', function ($q) use ($warehouseId, $periodStart, $periodEnd) {
            $q->where('warehouse_id', $warehouseId)
                ->whereBetween('return_date', [$periodStart, $periodEnd])
                ->whereIn('status', ['received', 'inspected', 'approved', 'refunded', 'restocked']);
        })->sum('return_qty');

        $charge = round($rate * $returnQty, 2);
        $this->log("RETURN INWARD: {$returnQty} qty x {$rate} = {$charge}");

        return ['charge' => $charge, 'return_qty' => intval($returnQty), 'rate' => $rate];
    }

    private function log(string $msg): void
    {
        file_put_contents(
            storage_path('logs/warehouse_charges_calc.log'),
            '[' . now()->format('Y-m-d H:i:s') . '] ' . $msg . "\n",
            FILE_APPEND
        );
    }
}
