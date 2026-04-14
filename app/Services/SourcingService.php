<?php

namespace App\Services;

use App\Models\{OfferSheet, OfferSheetItem, Consignment, LiveSheet, LiveSheetItem, Product, ActivityLog, User};
use App\Notifications\{OfferSheetNotification, ConsignmentNotification, LiveSheetNotification};
use Illuminate\Support\Facades\{DB, Notification};

class SourcingService
{
    /**
     * Create offer sheet from vendor upload
     */
    public function createOfferSheet(int $vendorId, string $companyCode, array $products): OfferSheet
    {
        return DB::transaction(function () use ($vendorId, $companyCode, $products) {
            $offerSheet = OfferSheet::create([
                'offer_sheet_number' => OfferSheet::generateNumber($companyCode),
                'vendor_id' => $vendorId,
                'company_code' => $companyCode,
                'status' => 'submitted',
                'total_products' => count($products),
            ]);

            foreach ($products as $product) {
                OfferSheetItem::create([
                    'offer_sheet_id' => $offerSheet->id,
                    'product_name' => $product['name'],
                    'product_sku' => $product['sku'] ?? null,
                    'category_id' => $product['category_id'] ?? null,
                    'vendor_price' => $product['price'] ?? 0,
                    'currency' => $product['currency'] ?? 'USD',
                    'thumbnail' => $product['thumbnail'] ?? null,
                    'product_details' => $product['details'] ?? null,
                ]);
            }

            ActivityLog::log('created', 'offer_sheet', $offerSheet, null, null, 'Offer sheet submitted by vendor');

            // Notify sourcing team
            $sourcing = User::internal()->byDepartment('sourcing')->active()->get();
            Notification::send($sourcing, new OfferSheetNotification($offerSheet, 'submitted'));

            return $offerSheet;
        });
    }

    /**
     * Sourcing team selects products from offer sheet
     */
    public function selectProducts(OfferSheet $offerSheet, array $selectedItemIds, User $reviewer): OfferSheet
    {
        return DB::transaction(function () use ($offerSheet, $selectedItemIds, $reviewer) {
            // Reset all selections
            $offerSheet->items()->update(['is_selected' => false, 'selected_by' => null, 'selected_at' => null]);

            // Select chosen items
            OfferSheetItem::whereIn('id', $selectedItemIds)
                ->where('offer_sheet_id', $offerSheet->id)
                ->update([
                    'is_selected' => true,
                    'selected_by' => $reviewer->id,
                    'selected_at' => now(),
                ]);

            $offerSheet->update([
                'status' => 'selection_done',
                'selected_products' => count($selectedItemIds),
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            ActivityLog::log('selected', 'offer_sheet', $offerSheet, null, ['selected' => $selectedItemIds]);

            // Notify vendor
            $offerSheet->vendor->user->notify(new OfferSheetNotification($offerSheet, 'products_selected'));

            return $offerSheet;
        });
    }

    /**
     * Convert offer sheet to consignment
     */
    public function createConsignment(OfferSheet $offerSheet): Consignment
    {
        return DB::transaction(function () use ($offerSheet) {
            $country = $offerSheet->company_code === '2100' ? 'US' : ($offerSheet->company_code === '2200' ? 'NL' : 'IN');

            $consignment = Consignment::create([
                'consignment_number' => Consignment::generateNumber($offerSheet->company_code, $country),
                'vendor_id' => $offerSheet->vendor_id,
                'offer_sheet_id' => $offerSheet->id,
                'company_code' => $offerSheet->company_code,
                'destination_country' => $country,
                'status' => 'created',
                'total_items' => $offerSheet->selected_products,
                'created_by' => auth()->id(),
            ]);

            // Create products from selected items
            foreach ($offerSheet->selectedItems as $item) {
                $product = Product::create([
                    'sku' => Product::generateSku($offerSheet->company_code, $item->category_id ?? 0),
                    'name' => $item->product_name,
                    'category_id' => $item->category_id,
                    'vendor_id' => $offerSheet->vendor_id,
                    'company_code' => $offerSheet->company_code,
                    'vendor_price' => $item->vendor_price,
                    'currency' => $item->currency,
                    'thumbnail' => $item->thumbnail,
                    'status' => 'selected',
                ]);

                $item->update(['product_id' => $product->id]);
            }

            $offerSheet->update(['status' => 'converted']);

            // Create empty live sheet
            LiveSheet::create([
                'consignment_id' => $consignment->id,
                'live_sheet_number' => LiveSheet::generateNumber($consignment->consignment_number),
                'status' => 'draft',
            ]);

            ActivityLog::log('created', 'consignment', $consignment, null, null, 'Consignment created from offer sheet');

            // Notify vendor about live sheet request
            $consignment->vendor->user->notify(new ConsignmentNotification($consignment, 'created'));

            return $consignment;
        });
    }

    /**
     * Vendor submits live sheet with detailed product info
     * Updates existing items (created when live sheet was generated from offer sheet)
     */
    public function submitLiveSheet(LiveSheet $liveSheet, array $items): LiveSheet
    {
        return DB::transaction(function () use ($liveSheet, $items) {
            $totalCbm = 0;
            $totalValue = 0;

            foreach ($items as $item) {
                $quantity = $item['quantity'] ?? 1;
                $unitPrice = $item['unit_price'] ?? 0;
                $cbmPerUnit = $item['cbm_per_unit'] ?? 0;
                $weightPerUnit = $item['weight_per_unit'] ?? 0;

                $totalPrice = $unitPrice * $quantity;
                $totalItemCbm = $cbmPerUnit * $quantity;
                $totalWeight = $weightPerUnit * $quantity;

                $totalCbm += $totalItemCbm;
                $totalValue += $totalPrice;

                // Update existing item (or create if somehow missing)
                $existing = LiveSheetItem::where('live_sheet_id', $liveSheet->id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'quantity'        => $quantity,
                        'unit_price'      => $unitPrice,
                        'total_price'     => $totalPrice,
                        'cbm_per_unit'    => $cbmPerUnit,
                        'total_cbm'       => $totalItemCbm,
                        'weight_per_unit' => $weightPerUnit,
                        'total_weight'    => $totalWeight,
                        'product_details' => $item['details'] ?? $existing->product_details,
                    ]);
                } else {
                    LiveSheetItem::create([
                        'live_sheet_id'   => $liveSheet->id,
                        'product_id'      => $item['product_id'],
                        'consignment_id'  => $liveSheet->consignment_id,
                        'quantity'        => $quantity,
                        'unit_price'      => $unitPrice,
                        'total_price'     => $totalPrice,
                        'cbm_per_unit'    => $cbmPerUnit,
                        'total_cbm'       => $totalItemCbm,
                        'weight_per_unit' => $weightPerUnit,
                        'total_weight'    => $totalWeight,
                        'product_details' => $item['details'] ?? null,
                    ]);
                }

                // Update product with latest info
                Product::where('id', $item['product_id'])->update([
                    'vendor_price' => $unitPrice,
                    'cbm'          => $cbmPerUnit,
                    'weight_kg'    => $weightPerUnit,
                ]);
            }

            $liveSheet->update([
                'status'    => 'submitted',
                'total_cbm' => $totalCbm,
            ]);

            // Update consignment if it exists (may not in new flow)
            if ($liveSheet->consignment) {
                $liveSheet->consignment->update([
                    'status'      => 'live_sheet_submitted',
                    'total_cbm'   => $totalCbm,
                    'total_value' => $totalValue,
                ]);
            }

            ActivityLog::log('submitted', 'live_sheet', $liveSheet, null, null, 'Live sheet submitted by vendor');

            // Notify sourcing team
            $sourcing = User::internal()->byDepartment('sourcing')->active()->get();
            Notification::send($sourcing, new LiveSheetNotification($liveSheet, 'submitted'));

            return $liveSheet;
        });
    }

    /**
     * Sourcing team approves and locks live sheet
     */
    public function approveLiveSheet(LiveSheet $liveSheet, User $approver): LiveSheet
    {
        if (!$liveSheet->canBeLocked()) {
            throw new \LogicException('Cannot approve and lock this Live Sheet. All items must have SAP codes uploaded by Finance first.');
        }

        return DB::transaction(function () use ($liveSheet, $approver) {
            $liveSheet->update([
                'status' => 'locked',
                'is_locked' => true,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'locked_by' => $approver->id,
                'locked_at' => now(),
            ]);

            // Update consignment if it exists (may not in new flow)
            if ($liveSheet->consignment) {
                $liveSheet->consignment->update(['status' => 'live_sheet_locked']);
            }

            ActivityLog::log('approved', 'live_sheet', $liveSheet, null, null, 'Live sheet approved and locked');

            // Notify logistics team for container planning
            $logistics = User::internal()->byDepartment('logistics')->active()->get();
            Notification::send($logistics, new LiveSheetNotification($liveSheet, 'ready_for_shipment'));

            return $liveSheet;
        });
    }

    /**
     * Admin unlocks live sheet for changes
     */
    public function unlockLiveSheet(LiveSheet $liveSheet, User $admin): LiveSheet
    {
        $liveSheet->update([
            'status' => 'unlocked',
            'is_locked' => false,
            'unlocked_by' => $admin->id,
            'unlocked_at' => now(),
        ]);

        ActivityLog::log('unlocked', 'live_sheet', $liveSheet, null, null, 'Live sheet unlocked by admin');
        return $liveSheet;
    }
}
