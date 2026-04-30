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

        DB::transaction(function () use ($asn, $pricingData, $preparer, &$pricings) {
            foreach ($pricingData as $data) {
                $pricing = PlatformPricing::updateOrCreate(
                    [
                        'asn_id' => $asn->id,
                        'product_id' => $data['product_id'],
                        'sales_channel_id' => $data['sales_channel_id'],
                    ],
                    [
                        'company_code' => $asn->company_code,
                        'cost_price' => $data['cost_price'],
                        'platform_price' => $data['platform_price'],
                        'selling_price' => $data['selling_price'],
                        'map_price' => $data['map_price'] ?? null,
                        'margin_percent' => $data['selling_price'] > 0
                            ? round((($data['selling_price'] - $data['cost_price']) / $data['selling_price']) * 100, 2) : 0,
                        'status' => 'submitted',
                        'prepared_by' => $preparer->id,
                    ]
                );
                $pricings[] = $pricing;
            }

            $asn->update(['status' => 'pricing_done']);
        });

        // Notify Finance HOD for review
        $financeHod = User::internal()->byDepartment('finance')->active()->get();
        Notification::send($financeHod, new PricingNotification($asn, 'review_required'));

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
