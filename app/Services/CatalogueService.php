<?php

namespace App\Services;

use App\Models\{ProductCatalogue, PlatformPricing, Product, SalesChannel, ActivityLog, User};
use App\Notifications\CatalogueNotification;
use Illuminate\Support\Facades\{DB, Notification};

class CatalogueService
{
    /**
     * Update listing status for products on platforms
     */
    public function updateListingStatus(array $listings, User $user): array
    {
        $updated = [];

        DB::transaction(function () use ($listings, $user, &$updated) {
            foreach ($listings as $listing) {
                $catalogue = ProductCatalogue::updateOrCreate(
                    [
                        'product_id' => $listing['product_id'],
                        'sales_channel_id' => $listing['sales_channel_id'],
                    ],
                    [
                        'company_code' => $listing['company_code'],
                        'listing_sku' => $listing['listing_sku'] ?? null,
                        'listing_url' => $listing['listing_url'] ?? null,
                        'shopify_url' => $listing['shopify_url'] ?? null,
                        'listing_status' => $listing['listing_status'] ?? 'listed',
                        'catalogue_details' => $listing['details'] ?? null,
                        'platform_pricing_id' => $listing['platform_pricing_id'] ?? null,
                        'listed_by' => $user->id,
                        'listed_at' => now(),
                    ]
                );

                // Update product platform listing status
                $product = Product::find($listing['product_id']);
                if ($product) {
                    $statuses = $product->platform_listing_status ?? [];
                    $channel = SalesChannel::find($listing['sales_channel_id']);
                    if ($channel) {
                        $statuses[$channel->slug] = $listing['listing_status'] === 'listed';
                        $product->update([
                            'platform_listing_status' => $statuses,
                            'status' => 'listed',
                        ]);
                    }
                }

                $updated[] = $catalogue;
            }
        });

        // Notify sales team of new listings
        $sales = User::internal()->byDepartment('sales')->active()->get();
        Notification::send($sales, new CatalogueNotification(null, 'skus_listed'));

        return $updated;
    }

    /**
     * Get listing dashboard stats
     */
    public function getListingStats(string $companyCode): array
    {
        $channels = SalesChannel::active()->get();
        $stats = [];

        foreach ($channels as $channel) {
            $stats[$channel->slug] = [
                'name' => $channel->name,
                'listed' => ProductCatalogue::where('sales_channel_id', $channel->id)
                    ->where('company_code', $companyCode)
                    ->where('listing_status', 'listed')->count(),
                'pending' => ProductCatalogue::where('sales_channel_id', $channel->id)
                    ->where('company_code', $companyCode)
                    ->where('listing_status', 'pending')->count(),
                'total' => ProductCatalogue::where('sales_channel_id', $channel->id)
                    ->where('company_code', $companyCode)->count(),
            ];
        }

        return $stats;
    }
}
