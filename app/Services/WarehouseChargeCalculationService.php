<?php

namespace App\Services;

use App\Models\{WarehouseRateCard, WarehouseMonthlyCharge, Warehouse, Grn, Order, OrderItem, Shipment, ActivityLog};
use Illuminate\Support\Facades\DB;

class WarehouseChargeCalculationService
{
    public function calculateMonthlyCharges(int $warehouseId, int $month, int $year, int $userId, bool $dryRun = false): array
    {
        //$dryRun = true;
        $warehouse = Warehouse::findOrFail($warehouseId);
        $rateCard = WarehouseRateCard::where('warehouse_id', $warehouseId)
            ->where('status', 'approved')
            ->orderByDesc('effective_from')
            ->first();

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

        $this->log("=== Calculating {$warehouse->name} ({$warehouse->company_code}) — {$month}/{$year} ===");

        // Route to correct strategy based on company code
        $strategy = $this->getStrategy($warehouse->company_code);

        $unloading    = $this->calculateUnloading($warehouseId, $rateCard, $month, $year);
        $putaway      = $this->calculatePutaway($warehouseId, $rateCard, $month, $year);
        $storage      = $this->{$strategy . 'Storage'}($warehouseId, $rateCard, $month, $year);
        $fulfillment  = $this->{$strategy . 'Fulfillment'}($warehouseId, $rateCard, $periodStart, $periodEnd);
        $pickPack     = $this->{$strategy . 'PickPack'}($warehouseId, $rateCard, $periodStart, $periodEnd);
        $returnInward = $this->calculateReturnInward($warehouseId, $rateCard, $periodStart, $periodEnd);

        $totals = [
            'unloading'        => $unloading['charge'],
            'putaway'          => $putaway['charge'],
            'storage'          => $storage['charge'],
            'order_processing' => $fulfillment['charge'],
            'pick_pack'        => $pickPack['charge'],
            'return_inward'    => $returnInward['charge'],
        ];
        $expectedTotal = round(array_sum($totals), 2);
        $details = compact('unloading', 'putaway', 'storage', 'fulfillment', 'pickPack', 'returnInward');

        $this->log("TOTALS: " . json_encode($totals) . " = {$expectedTotal}");

        if ($dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'expected' => $totals,
                'expected_total' => $expectedTotal,
                'currency' => $currency,
                'strategy' => $strategy,
                'details' => $details,
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
                    'strategy' => $strategy,
                    'company_code' => $warehouse->company_code,
                    'rates' => $rateCard->toArray(),
                    'details' => $details,
                    'calculated_at' => now()->toISOString(),
                ],
            ]);

            if (!empty($unloading['grn_ids'])) {
                Grn::whereIn('id', $unloading['grn_ids'])->update(['inward_charged' => true]);
            }

            DB::commit();

            ActivityLog::log('calculated', 'warehouse_monthly_charges', $monthlyCharge, null, [
                'warehouse' => $warehouse->name,
                'strategy' => $strategy,
                'month' => $month,
                'year' => $year,
                'expected_total' => $expectedTotal,
            ], "WH charges ({$strategy}): {$warehouse->name} — {$month}/{$year}: {$currency} {$expectedTotal}");

            return ['success' => true, 'charge_id' => $monthlyCharge->id, 'expected_total' => $expectedTotal, 'details' => $totals, 'strategy' => $strategy];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Warehouse charge calc failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── STRATEGY ROUTER ──────────────────────────────────────────

    private function getStrategy(string $companyCode): string
    {
        return match ($companyCode) {
            '2100' => 'usa',
            '2200' => 'eu',
            '2400' => 'eu',   // UK uses EU logic
            '2000' => 'eu',   // India uses EU logic
            default => 'eu',
        };
    }

    // ═══════════════════════════════════════════════════════════════
    //  SHARED — Unloading, Putaway, Return Inward (same for all)
    // ═══════════════════════════════════════════════════════════════

    /**
     * UNLOADING — one-time per GRN based on shipment type
     * FCL: count of GRNs × unloading_fcl_rate
     * LCL: no_of_pallets (shipment) × unloading_lcl_palletize
     * AIR: master cartons (live sheet) × unloading_carton
     */
    private function calculateUnloading(int $warehouseId, $rc, int $month, int $year): array
    {
        $grns = Grn::where('warehouse_id', $warehouseId)
            ->where(function ($q) {
                $q->where('inward_charged', false)->orWhereNull('inward_charged');
            })
            ->whereMonth('receipt_date', $month)
            ->whereYear('receipt_date', $year)
            ->with('shipment.consignments.liveSheet.items')
            ->get();

        $totalCharge = 0;
        $breakdown = [];
        $grnIds = [];

        // Group GRNs by shipment to count correctly
        $grnsByShipment = $grns->groupBy(fn($g) => $g->shipment_id ?? 'none');

        foreach ($grns as $grn) {
            $shipment = $grn->shipment;
            $type = strtoupper($shipment->shipment_type ?? 'LCL');
            $charge = 0;
            $qty = 0;
            $rate = 0;
            $unit = '';

            switch ($type) {
                case 'FCL':
                    $qty = 1; // Each GRN counts as 1
                    $rate = floatval($rc->unloading_fcl_rate ?? 0);
                    $unit = 'GRN';
                    break;
                case 'LCL':
                    $qty = intval($shipment->no_of_pallets ?? 0);
                    $rate = floatval($rc->unloading_lcl_palletize ?? 0);
                    $unit = 'pallets';
                    break;
                case 'AIR':
                    $qty = 0;
                    foreach ($shipment->consignments ?? [] as $con) {
                        if (!$con->liveSheet) continue;
                        foreach ($con->liveSheet->items as $item) {
                            $d = $item->product_details ?? [];
                            $qty += intval($d['master_cartons'] ?? $d['no_of_master_cartons'] ?? 0);
                        }
                    }
                    $rate = floatval($rc->unloading_carton ?? 0);
                    $unit = 'master cartons';
                    break;
            }

            $charge = round($qty * $rate, 2);
            $totalCharge += $charge;
            $grnIds[] = $grn->id;
            $breakdown[] = ['grn_id' => $grn->id,'grn_no' => $grn->grn_number, 'type' => $type, 'qty' => $qty, 'rate' => $rate, 'unit' => $unit, 'charge' => $charge];
            $this->log("UNLOADING GRN#{$grn->id} NO-{$grn->grn_number}: {$type} — {$qty} {$unit} x {$rate} = {$charge}");
        }

        $fclCount = collect($breakdown)->where('type', 'FCL')->count();
        $this->log("UNLOADING SUMMARY: FCL={$fclCount} GRNs, Total={$totalCharge}");

        return ['charge' => round($totalCharge, 2), 'grn_count' => count($grns), 'grn_ids' => $grnIds, 'breakdown' => $breakdown];
    }

    /**
     * PUTAWAY — putaway_per_carton × master cartons from live sheet
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
                    $totalCartons += intval($d['master_cartons'] ?? $d['no_of_master_cartons'] ?? 0);
                }
            }
        }

        $charge = round($rate * $totalCartons, 2);
        $this->log("PUTAWAY: {$totalCartons} cartons × {$rate} = {$charge}");

        return ['charge' => $charge, 'total_cartons' => $totalCartons, 'rate' => $rate];
    }

    /**
     * RETURN INWARD — return_inward_per_qty × returned qty
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
        $this->log("RETURN INWARD: {$returnQty} qty × {$rate} = {$charge}");

        return ['charge' => $charge, 'return_qty' => intval($returnQty), 'rate' => $rate];
    }

    // ═══════════════════════════════════════════════════════════════
    //  USA WAREHOUSE — CFT storage, threshold-based fulfillment
    // ═══════════════════════════════════════════════════════════════

    /**
     * USA STORAGE — CFT of inventory on 1st of month × storage_per_cft
     * CFT comes from live sheet items product dimensions
     */
    private function usaStorage(int $warehouseId, $rc, int $month, int $year): array
    {
        $rate = floatval($rc->storage_per_cft ?? 0);

        // Get inventory as of 1st day of current month
        // Calculate total CFT from products in this warehouse
        $inventories = \App\Models\Inventory::where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->with('product')
            ->get();

        $totalCft = 0;
        $breakdown = [];

        foreach ($inventories as $inv) {
            $p = $inv->product;
            if (!$p) continue;

            // CFT = (L × W × H in cm) / 28316.85 OR (L × W × H in inches) / 1728
            $lengthCm = floatval($p->length_cm ?? $p->length ?? 0);
            $widthCm = floatval($p->width_cm ?? $p->width ?? 0);
            $heightCm = floatval($p->height_cm ?? $p->height ?? 0);

            if ($lengthCm > 0 && $widthCm > 0 && $heightCm > 0) {
                //   $cftPerUnit = (($lengthCm * $widthCm * $heightCm) / 61024) * 35.3147; if LxWxH in cm
                $cftPerUnit = ($lengthCm * $widthCm * $heightCm) / 28316.85; //L × W × H in inches
            } else {
                $cftPerUnit = 0;
            }

            $qty = intval($inv->quantity);
            $itemCft = round($cftPerUnit * $qty, 4);
            $totalCft += $itemCft;

            if ($itemCft > 0) {
                $breakdown[] = ['sku' => $p->sku, 'qty' => $qty, 'cft_per_unit' => round($cftPerUnit, 4), 'total_cft' => $itemCft];
            }
        }

        $charge = round($rate * $totalCft, 2);
        $this->log("USA STORAGE: {$totalCft} CFT x {$rate}/CFT = {$charge} ({$inventories->count()} SKUs)");

        return ['charge' => $charge, 'total_cft' => round($totalCft, 4), 'rate' => $rate, 'method' => 'cft_based', 'sku_count' => count($breakdown), 'breakdown' => $breakdown];
    }

    /**
     * USA FULFILLMENT — threshold-based per order
     * If order qty > threshold → qty × upper rate
     * If order qty <= threshold → qty × lower rate
     */
    private function usaFulfillment(int $warehouseId, $rc, $periodStart, $periodEnd): array
    {
        $threshold = max(1, intval($rc->fulfillment_qty_threshold ?? 10));
        $lowerRate = floatval($rc->fulfillment_rate_lower ?? 0);
        $upperRate = floatval($rc->fulfillment_rate_upper ?? 0);

        $orders = Order::where('warehouse_id', $warehouseId)
            ->whereBetween('order_date', [$periodStart, $periodEnd])
            ->whereNotIn('status', ['cancelled'])
            ->with('items')
            ->get();

        $smallOrders = 0;
        $largeOrders = 0;
        $smallQty = 0;
        $largeQty = 0;

        foreach ($orders as $order) {
            $orderQty = $order->items->sum('quantity');
            if ($orderQty > $threshold) {
                $largeOrders++;
                $largeQty += $orderQty;
            } else {
                $smallOrders++;
                $smallQty += $orderQty;
            }
        }

        $smallCharge = round($smallQty * $lowerRate, 2);
        $largeCharge = round($largeQty * $upperRate, 2);
        $charge = $smallCharge + $largeCharge;

        $this->log("USA FULFILLMENT: ≤{$threshold}: {$smallQty}qty x {$lowerRate} = {$smallCharge} | >{$threshold}: {$largeQty}qty × {$upperRate} = {$largeCharge}");

        return [
            'charge' => round($charge, 2),
            'threshold' => $threshold,
            'small_orders' => $smallOrders,
            'small_qty' => $smallQty,
            'lower_rate' => $lowerRate,
            'small_charge' => $smallCharge,
            'large_orders' => $largeOrders,
            'large_qty' => $largeQty,
            'upper_rate' => $upperRate,
            'large_charge' => $largeCharge,
        ];
    }

    /**
     * USA PICK & PACK — per unit sold
     */
    private function usaPickPack(int $warehouseId, $rc, $periodStart, $periodEnd): array
    {
        $rate = floatval($rc->pick_pack_rate_per_unit ?? 0);

        $totalUnits = OrderItem::whereHas('order', function ($q) use ($warehouseId, $periodStart, $periodEnd) {
            $q->where('warehouse_id', $warehouseId)
                ->whereBetween('order_date', [$periodStart, $periodEnd])
                ->whereNotIn('status', ['cancelled']);
        })->sum('quantity');

        $charge = round($rate * $totalUnits, 2);
        $this->log("USA PICK-PACK: {$totalUnits} units x {$rate} = {$charge}");

        return ['charge' => $charge, 'total_units' => intval($totalUnits), 'rate' => $rate];
    }

    // ═══════════════════════════════════════════════════════════════
    //  EU WAREHOUSE — Pallet storage, palletize/non-palletize orders
    // ═══════════════════════════════════════════════════════════════

    /**
     * EU STORAGE — storage_per_pallet × average pallets (from daily pallet logs)
     */
    private function euStorage(int $warehouseId, $rc, int $month, int $year): array
    {
        $rate = floatval($rc->storage_per_pallet ?? 0);

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
                $entry = $palletLogs->first(fn($log) => $log->entry_date->format('Y-m-d') === $date);
                if ($entry) $lastKnown = $entry->no_of_pallets;
                $dailyTotal += $lastKnown;
            }
            $avgPallets = round($dailyTotal / $daysInMonth, 2);
            $method = 'daily_average';
        }

        $charge = round($rate * $avgPallets, 2);
        $this->log("EU STORAGE: avg {$avgPallets} pallets x {$rate} = {$charge} ({$method})");

        return ['charge' => $charge, 'avg_pallets' => $avgPallets, 'rate' => $rate, 'method' => $method, 'entries' => $palletLogs->count()];
    }

    /**
     * EU FULFILLMENT — palletize/non-palletize order processing
     * Palletize: rate × qty
     * Non-palletize: rate × no of orders
     */
    private function euFulfillment(int $warehouseId, $rc, $periodStart, $periodEnd): array
    {
        $palletizeRate = floatval($rc->order_processing_palletize ?? 0);
        $nonPalletizeRate = floatval($rc->order_processing_non_palletize ?? 0);

        $orders = Order::where('warehouse_id', $warehouseId)
            ->whereBetween('order_date', [$periodStart, $periodEnd])
            ->whereNotIn('status', ['cancelled'])
            ->with('items')
            ->get();

        $palletize = $orders->where('order_pack_type', 'palletize');
        $nonPalletize = $orders->where('order_pack_type', '!=', 'palletize');

        $pQty = $palletize->sum(fn($o) => $o->items->sum('quantity'));
        $pCharge = round($palletizeRate * $pQty, 2);

        $npCount = $nonPalletize->count();
        $npCharge = round($nonPalletizeRate * $npCount, 2);

        $charge = $pCharge + $npCharge;
        $this->log("EU FULFILLMENT: Palletize {$pQty}qty x {$palletizeRate} = {$pCharge} | Non-Pall {$npCount}orders x {$nonPalletizeRate} = {$npCharge}");

        return [
            'charge' => round($charge, 2),
            'palletize_qty' => $pQty,
            'palletize_rate' => $palletizeRate,
            'palletize_charge' => $pCharge,
            'non_palletize_count' => $npCount,
            'non_palletize_rate' => $nonPalletizeRate,
            'non_palletize_charge' => $npCharge,
        ];
    }

    /**
     * EU PICK & PACK — palletize/non-palletize
     * Palletize: rate × qty
     * Non-palletize: rate × no of orders
     */
    private function euPickPack(int $warehouseId, $rc, $periodStart, $periodEnd): array
    {
        $palletizeRate = floatval($rc->pick_pack_palletize ?? 0);
        $nonPalletizeRate = floatval($rc->pick_pack_non_palletize ?? 0);

        $orders = Order::where('warehouse_id', $warehouseId)
            ->whereBetween('order_date', [$periodStart, $periodEnd])
            ->whereNotIn('status', ['cancelled'])
            ->with('items')
            ->get();

        $palletize = $orders->where('order_pack_type', 'palletize');
        $nonPalletize = $orders->where('order_pack_type', '!=', 'palletize');

        $pQty = $palletize->sum(fn($o) => $o->items->sum('quantity'));
        $pCharge = round($palletizeRate * $pQty, 2);

        $npCount = $nonPalletize->count();
        $npCharge = round($nonPalletizeRate * $npCount, 2);

        $charge = $pCharge + $npCharge;
        $this->log("EU PICK-PACK: Palletize {$pQty}qty x {$palletizeRate} = {$pCharge} | Non-Pall {$npCount}orders x {$nonPalletizeRate} = {$npCharge}");

        return [
            'charge' => round($charge, 2),
            'palletize_qty' => $pQty,
            'palletize_rate' => $palletizeRate,
            'palletize_charge' => $pCharge,
            'non_palletize_count' => $npCount,
            'non_palletize_rate' => $nonPalletizeRate,
            'non_palletize_charge' => $npCharge,
        ];
    }

    // ─── LOGGER ──────────────────────────────────────────────────

    private function log(string $msg): void
    {
        file_put_contents(
            storage_path('logs/warehouse_charges_calc.log'),
            '[' . now()->format('Y-m-d H:i:s') . '] ' . $msg . "\n",
            FILE_APPEND
        );
    }
}
