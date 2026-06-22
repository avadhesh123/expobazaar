<?php

namespace App\Services;

use App\Models\{PlatformPricing, ProductCatalogue, Asn, Product, SalesChannel, ActivityLog, User};
use App\Notifications\{PricingNotification, CatalogueNotification};
use Illuminate\Support\Facades\{DB, Notification};

class PricingService
{
    /**
     * HOD prepares platform pricing from ASN
     */
    public function preparePricing(Asn $asn, array $pricingData, User $preparer): array
    {
        $pricings = [];
        $errors = [];

        DB::transaction(function () use ($asn, $pricingData, $preparer, &$pricings, &$errors) {
            foreach ($pricingData as $data) {
                $productId = $data['product_id'];
                $channels = $data['channels'] ?? [];

                if (empty($channels)) {
                    $sku = \App\Models\Product::find($productId)?->sku ?? $productId;
                    $errors[] = "Product {$sku}: No sales channel selected. Skipped.";
                    continue;
                }

                $fob       = floatval($data['fob'] ?? 0);
                $wsp       = floatval($data['wsp'] ?? 0);
                $lastMile  = floatval($data['last_mile'] ?? 0);
                $retailPrice = floatval($data['retail_price'] ?? ($wsp + $lastMile));

                foreach ($channels as $chData) {
                    $channelId    = $chData['sales_channel_id'];
                    $factor       = floatval($chData['pricing_factor'] ?? 1.0);
                    $channelPrice = floatval($chData['channel_price'] ?? ($wsp * $factor));
                    $costPrice    = $fob;
                    $sellingPrice = $channelPrice;
                    $margin       = $sellingPrice > 0
                        ? round((($sellingPrice - $costPrice) / $sellingPrice) * 100, 2) : 0;

                    $pricing = PlatformPricing::updateOrCreate(
                        [
                            'asn_id'           => $asn->id,
                            'product_id'       => $productId,
                            'sales_channel_id' => $channelId,
                        ],
                        [
                            'inward'          => $data['inward'] ?? 0,
                            'fulfillment'     => $data['fulfillment'] ?? 0,
                            'storage'         => $data['storage'] ?? 0,
                            'ad_budget'       => $data['ad_budget'] ?? 0,
                            'final_wsp'       => $data['final_wsp'] ?? 0,
                            'company_code'    => $asn->company_code,
                            'cost_price'      => $costPrice,
                            'fob_price'       => $fob,
                            'wsp_price'       => $wsp,
                            'last_mile'       => $lastMile,
                            'retail_price'    => $retailPrice,
                            'pricing_factor'  => $factor,
                            'channel_price'   => $channelPrice,
                            'platform_price'  => $channelPrice,
                            'selling_price'   => $sellingPrice,
                            'map_price'       => null,
                            'margin_percent'  => $margin,
                            'status'          => 'approved',
                            'prepared_by'     => $preparer->id,
                            'approved_by'     => auth()->id(),
                            'approved_at'     => now(),
                        ]
                    );
                    $pricings[] = $pricing;
                }
            }

            if (empty($pricings)) {
                throw new \Exception('No pricing created. All products are missing sales channel selection.');
            }

            $asn->update(['status' => 'pricing_done']);
        });

        if (!empty($errors)) {
            session()->flash('warning', implode(' | ', $errors));
        }

        try {
            $financeHod = User::internal()->byDepartment('finance')->active()->get();
            Notification::send($financeHod, new PricingNotification($asn, 'review_required'));
        } catch (\Exception $e) {
            \Log::warning('Pricing notification failed: ' . $e->getMessage());
        }

        return $pricings;
    }
    public function preparePricingBAK(Asn $asn, array $pricingData, User $preparer): array
    {
        $pricings = [];

        DB::transaction(function () use ($asn, $pricingData, $preparer, &$pricings) {
            foreach ($pricingData as $data) {
                $productId = $data['product_id'];
                $fob       = floatval($data['fob'] ?? 0);
                $wsp       = floatval($data['wsp'] ?? 0);
                $lastMile  = floatval($data['last_mile'] ?? 0);
                $retailPrice = floatval($data['retail_price'] ?? ($wsp + $lastMile));

                // Create one PlatformPricing record per channel
                $channels = $data['channels'] ?? [];

                if (!empty($channels)) {
                    foreach ($channels as $chData) {
                        $channelId    = $chData['sales_channel_id'];
                        $factor       = floatval($chData['pricing_factor'] ?? 1.0);
                        $channelPrice = floatval($chData['channel_price'] ?? ($wsp * $factor));

                        $costPrice    = $fob;
                        $sellingPrice = $channelPrice;
                        $margin       = $sellingPrice > 0
                            ? round((($sellingPrice - $costPrice) / $sellingPrice) * 100, 2) : 0;


                        $pricing = PlatformPricing::updateOrCreate(
                            [
                                'asn_id'          => $asn->id,
                                'product_id'      => $productId,
                                'sales_channel_id' => $channelId,
                            ],
                            [
                                'inward'         => $data['inward'] ?? 0,
                                'fulfillment'     => $data['fulfillment'] ?? 0,
                                'storage'         => $data['storage'] ?? 0,
                                'ad_budget'       => $data['ad_budget'] ?? 0,
                                'final_wsp'       => $data['final_wsp'] ?? 0,
                                'company_code'    => $asn->company_code,
                                'cost_price'      => $costPrice,
                                'fob_price'       => $fob,
                                'wsp_price'       => $wsp,
                                'last_mile'       => $lastMile,
                                'retail_price'    => $retailPrice,
                                'pricing_factor'  => $factor,
                                'channel_price'   => $channelPrice,
                                'platform_price'  => $channelPrice,
                                'selling_price'   => $sellingPrice,
                                'map_price'       => null,
                                'margin_percent'  => $margin,
                                'status'          => 'approved', //'submitted',
                                'prepared_by'     => $preparer->id,
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                            ]
                        );
                        $pricings[] = $pricing;
                    }
                } else {

                    // Fallback: no channels submitted, save product-level data only
                    $pricing = PlatformPricing::updateOrCreate(
                        [
                            'asn_id'     => $asn->id,
                            'product_id' => $productId,
                        ],
                        [
                            'inward'         => $data['inward'] ?? 0,
                            'fulfillment'     => $data['fulfillment'] ?? 0,
                            'storage'         => $data['storage'] ?? 0,
                            'ad_budget'       => $data['ad_budget'] ?? 0,
                            'final_wsp'       => $data['final_wsp'] ?? 0,
                            'company_code'   => $asn->company_code,
                            'cost_price'     => $fob,
                            'fob_price'      => $fob,
                            'wsp_price'      => $wsp,
                            'last_mile'      => $lastMile,
                            'retail_price'   => $retailPrice,
                            'selling_price'  => $retailPrice,
                            'platform_price' => $retailPrice,
                            'margin_percent' => $retailPrice > 0
                                ? round((($retailPrice - $fob) / $retailPrice) * 100, 2) : 0,
                            'status'         => 'approved', //'submitted',
                            'prepared_by'    => $preparer->id,
                            'approved_by' => auth()->id(),
                            'approved_at' => now()
                        ]
                    );
                    $pricings[] = $pricing;
                }
            }
            $asn->update(['status' => 'pricing_done']);
        });

        // Notify Finance for review
        try {
            $financeHod = User::internal()->byDepartment('finance')->active()->get();
            Notification::send($financeHod, new PricingNotification($asn, 'review_required'));
        } catch (\Exception $e) {
            \Log::warning('Pricing notification failed: ' . $e->getMessage());
        }

        return $pricings;
    }

    /**
     * Finance HOD reviews pricing
     */
    public function reviewPricing(Asn $asn, User $reviewer, bool $approved): void
    {
        $status = $approved ? 'approved' : 'rejected';

        PlatformPricing::where('asn_id', $asn->id)->update([
            'status' => $status,
            'finance_reviewed_by' => $reviewer->id,
        ]);

        if ($approved) {
            // Notify cataloguing team
            $cataloguing = User::internal()->byDepartment('cataloguing')->active()->get();
            Notification::send($cataloguing, new PricingNotification($asn, 'approved_for_cataloguing'));
        }
    }

    /**
     * Final approval by HOD
     */
    public function finalizePricing(Asn $asn, User $approver): void
    {
        Asn::where('id', $asn->id)->update(['status' => 'approved']);
        PlatformPricing::where('asn_id', $asn->id)->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        // Notify cataloguing
        $cataloguing = User::internal()->byDepartment('cataloguing')->active()->get();
        Notification::send($cataloguing, new CatalogueNotification($asn, 'pricing_ready'));
    }
}
