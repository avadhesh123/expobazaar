<?php

namespace App\Services;

use App\Models\{LiveSheet, LiveSheetItem, ActivityLog};
use Illuminate\Support\Facades\DB;

class LiveSheetService
{
    /**
     * Update live sheet items — single source of truth
     * Used by: VendorController::submitLiveSheet, SourcingController::updateSourcingFields
     *
     * @param string $source  'vendor' | 'sourcing'
     */
    public function updateItems(LiveSheet $liveSheet, array $items, string $source = 'sourcing', ?string $reason = null): array
    {
        $activeCompany = session('active_company') ?? $liveSheet->company_code ?? '2100';
        $updated = 0;
        $totalChanges = 0;

        DB::beginTransaction();
        try {
            foreach ($items as $row) {
                $item = $this->findItem($liveSheet, $row, $source);
                if (!$item) continue;

                $details = $item->product_details ?? [];

                // ── 1. Build updated fields based on source ──
                $fields = $this->extractFields($row, $details, $item, $source);

                // ── 2. Track changes before updating ──
                $totalChanges += $this->trackChanges($item, $fields, $source, $reason);

                // ── 3. Recalculate derived values ──
                $calculated = $this->recalculate($details, $fields, $row, $activeCompany);

                // ── 4. Merge all into product_details ──
                $details = array_merge($details, $fields, $calculated);

                // ── 5. Update item ──
                $finalQty = intval($fields['final_qty'] ?? $item->quantity);
                $finalFob = floatval($fields['final_fob'] ?? $fields['vendor_fob'] ?? $item->unit_price);

                $item->update([
                    'quantity'        => $finalQty,
                    'unit_price'      => $finalFob ?: $item->unit_price,
                    'total_price'     => round(($finalFob ?: $item->unit_price) * $finalQty, 2),
                    'total_cbm'       => round($calculated['cbm_shipment'] ?? $item->total_cbm, 4),
                    'is_selected'     => $row['is_selected'] ?? $item->is_selected,
                    'product_details' => $details,
                ]);

                // ── 6. Update product master if WSP calculated ──
                if (!empty($calculated['wsp']) && $calculated['wsp'] > 0 && $item->product) {
                    $item->product->update([
                        'vendor_wsp'   => round($calculated['wsp'], 2),
                        'vendor_price' => $finalFob,
                    ]);
                }

                $updated++;
            }

            // Update live sheet totals
            $liveSheet->update([
                'total_cbm' => $liveSheet->items()->sum('total_cbm'),
            ]);

            ActivityLog::log('updated', 'live_sheet', $liveSheet, null, [
                'source'         => $source,
                'items_updated'  => $updated,
                'fields_changed' => $totalChanges,
                'reason'         => $reason,
            ], ucfirst($source) . " updated {$updated} items" . ($reason ? " — {$reason}" : ''));

            DB::commit();

            return [
                'success' => true,
                'updated' => $updated,
                'changes' => $totalChanges,
                'message' => "{$updated} item(s) updated successfully.",
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("LiveSheet update failed [{$source}]: {$e->getMessage()}");
            return [
                'success' => false,
                'updated' => 0,
                'message' => 'Update failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Find item — vendor uses product_id, sourcing uses item_id
     */
    private function findItem(LiveSheet $liveSheet, array $row, string $source): ?LiveSheetItem
    {
        if ($source === 'vendor' && isset($row['product_id'])) {
            return LiveSheetItem::where('live_sheet_id', $liveSheet->id)
                ->where('product_id', $row['product_id'])->first();
        }

        $itemId = $row['item_id'] ?? $row['id'] ?? null;
        if ($itemId) {
            $item = LiveSheetItem::find($itemId);
            return ($item && $item->live_sheet_id === $liveSheet->id) ? $item : null;
        }

        return null;
    }

    /**
     * Extract updatable fields based on source
     */
    private function extractFields(array $row, array $details, LiveSheetItem $item, string $source): array
    {
        if ($source === 'vendor') {
            return [
                'vendor_fob' => $row['unit_price'] ?? $details['vendor_fob'] ?? $item->unit_price,
                'final_qty'  => $row['quantity'] ?? $details['final_qty'] ?? $item->quantity,
                'final_fob'  => $row['unit_price'] ?? $details['final_fob'] ?? $item->unit_price,
                // Vendor can also update these if provided
                'description'    => $row['description'] ?? $details['description'] ?? null,
                'barcode'        => $row['barcode'] ?? $details['barcode'] ?? null,
                'hsn_hts_code'   => $row['hsn_code'] ?? $row['hsn_hts_code'] ?? $details['hsn_hts_code'] ?? null,
                'length'         => $row['length'] ?? $details['length'] ?? null,
                'width'          => $row['width'] ?? $details['width'] ?? null,
                'height'         => $row['height'] ?? $details['height'] ?? null,
                'weight'         => $row['weight'] ?? $details['weight'] ?? null,
                'material'       => $row['material'] ?? $details['material'] ?? null,
                'color'          => $row['color'] ?? $details['color'] ?? null,
                'finish'         => $row['finish'] ?? $details['finish'] ?? null,
                'qty_inner_pack' => $row['qty_inner_pack'] ?? $details['qty_inner_pack'] ?? null,
                'qty_master_pack'=> $row['qty_master_pack'] ?? $details['qty_master_pack'] ?? null,
                'product_name'   => $row['product_name'] ?? $details['product_name'] ?? null,
            ];
        }

        // Sourcing fields
        return [
            'target_fob'     => $row['target_fob'] ?? $details['target_fob'] ?? null,
            'final_qty'      => $row['final_qty'] ?? $details['final_qty'] ?? $item->quantity,
            'final_fob'      => $row['final_fob'] ?? $details['final_fob'] ?? $item->unit_price,
            'freight_factor' => $row['freight_factor'] ?? $details['freight_factor'] ?? null,
            'wsp_factor'     => $row['wsp_factor'] ?? $details['wsp_factor'] ?? null,
            'comments'       => $row['comments'] ?? $details['comments'] ?? null,
            'vendor_wsp'     => $row['vendor_wsp'] ?? $details['vendor_wsp'] ?? null,
            // Sourcing can also update product details via inline edit
            'description'    => $row['description'] ?? $details['description'] ?? null,
            'barcode'        => $row['barcode'] ?? $details['barcode'] ?? null,
            'hsn_hts_code'   => $row['hsn_code'] ?? $row['hsn_hts_code'] ?? $details['hsn_hts_code'] ?? null,
            'length'         => $row['length'] ?? $details['length'] ?? null,
            'width'          => $row['width'] ?? $details['width'] ?? null,
            'height'         => $row['height'] ?? $details['height'] ?? null,
            'weight'         => $row['weight'] ?? $details['weight'] ?? null,
            'material'       => $row['material'] ?? $details['material'] ?? null,
            'color'          => $row['color'] ?? $details['color'] ?? null,
            'finish'         => $row['finish'] ?? $details['finish'] ?? null,
            'product_name'   => $row['product_name'] ?? $details['product_name'] ?? null,
        ];
    }

    /**
     * Recalculate CBM, WSP, duty, freight, landed cost
     */
    private function recalculate(array $details, array $fields, array $row, string $activeCompany): array
    {
        $masterL = floatval($details['master_length'] ?? $details['master_carton_length'] ?? 0);
        $masterW = floatval($details['master_width'] ?? $details['master_carton_width'] ?? 0);
        $masterH = floatval($details['master_height'] ?? $details['master_carton_height'] ?? 0);

        // CBM formula differs by company
        if ($activeCompany === '2100') {
            // USA: cubic inches → CBM
            $masterCbm = ($masterL > 0 && $masterW > 0 && $masterH > 0)
                ? ($masterL * $masterW * $masterH) / 61023 : 0;
        } else {
            // EU & UK: cm → CBM
            $masterCbm = ($masterL > 0 && $masterW > 0 && $masterH > 0)
                ? ($masterL * $masterW * $masterH) / 1000000 : 0;
        }

        $finalQty = intval($fields['final_qty'] ?? 0);
        $finalFob = floatval($fields['final_fob'] ?? $fields['vendor_fob'] ?? 0);
        $qtyMaster = max(1, intval($details['qty_master_pack'] ?? 1));
        $totalCartons = intval($row['no_of_master_carton'] ?? ($qtyMaster > 0 ? ceil($finalQty / $qtyMaster) : 0));
        $cbmShipment = $totalCartons * $masterCbm;

        // WSP calculation (sourcing only)
        $dutyPercent = floatval($details['duty_percent'] ?? 0);
        $freightFactor = floatval($fields['freight_factor'] ?? $details['freight_factor'] ?? 0);
        $wspFactor = floatval($fields['wsp_factor'] ?? $details['wsp_factor'] ?? 0);

        $dutyAmt = $finalFob * ($dutyPercent / 100);
        $freightAmt = $finalFob * ($freightFactor / 100);
        $landedCost = $finalFob + $dutyAmt + $freightAmt;
        $wsp = $wspFactor > 0 ? ($landedCost * $wspFactor) : 0;

        return [
            'total_master_cartons' => $totalCartons,
            'master_cbm'           => round($masterCbm, 6),
            'cbm_shipment'         => round($cbmShipment, 4),
            'duty_amount'          => round($dutyAmt, 2),
            'freight_amount'       => round($freightAmt, 2),
            'landed_cost'          => round($landedCost, 2),
            'wsp'                  => round($wsp, 2),
        ];
    }

    /**
     * Track changes before updating
     */
    private function trackChanges(LiveSheetItem $item, array $fields, string $source, ?string $reason): int
    {
        try {
            return \App\Models\LiveSheetItemChange::trackChanges(
                $item, $fields, auth()->user(), $source, $reason
            ) ?: 0;
        } catch (\Exception $e) {
            \Log::warning("Change tracking failed: {$e->getMessage()}");
            return 0;
        }
    }
}
