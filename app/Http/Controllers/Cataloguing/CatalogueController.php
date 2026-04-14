<?php

namespace App\Http\Controllers\Cataloguing;

use App\Http\Controllers\Controller;
use App\Models\{PlatformPricing, ProductCatalogue, Product, SalesChannel};
use App\Services\{DashboardService, CatalogueService};
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected CatalogueService $catalogueService
    ) {
    }

    public function dashboard(Request $request)
    {
        $companyCode = $request->get('company_code');
        $data = $this->dashboardService->getCataloguingDashboard($companyCode);
        return view('cataloguing.dashboard', compact('data', 'companyCode'));
    }

    public function pricingSheets(Request $request)
    {
        $pricings = PlatformPricing::with('product.category', 'product.vendor', 'product.catalogues', 'salesChannel', 'asn')
            ->where('status', 'approved')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->channel_id, fn ($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->asn_id, fn ($q, $v) => $q->where('asn_id', $v))
            ->latest()->paginate(30)->withQueryString();

        $channels = SalesChannel::active()->orderBy('name')->get();
        $asns = \App\Models\Asn::orderBy('asn_number', 'desc')->limit(100)->get(['id', 'asn_number']);

        return view('cataloguing.pricing-sheets', compact('pricings', 'channels', 'asns'));
    }

    public function listingPanel(Request $request)
    {
        $products = Product::with('catalogues.salesChannel', 'category', 'vendor')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->search, function ($q, $v) {
                $q->where(function ($s) use ($v) {
                    $s->where('sku', 'LIKE', "%{$v}%")
                      ->orWhere('name', 'LIKE', "%{$v}%");
                });
            })
            ->paginate(30)->withQueryString();

        $channels = SalesChannel::active()->orderBy('name')->get();
        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);

        return view('cataloguing.listing-panel', compact('products', 'channels', 'categories'));
    }

    public function updateListings(Request $request)
    {
        $request->validate([
            'listings' => 'required|array|min:1',
            'listings.*.product_id'        => 'required|exists:products,id',
            'listings.*.sales_channel_id'  => 'required|exists:sales_channels,id',
            'listings.*.listing_status'    => 'nullable|in:pending,listed,inactive,removed',
            'listings.*.listing_sku'       => 'nullable|string|max:100',
            'listings.*.listing_url'       => 'nullable|url|max:500',
        ], [
            'listings.*.listing_status.in' => 'Invalid listing status. Allowed values: pending, listed, inactive, removed.',
        ]);

        // Normalize: map any unexpected values to allowed ENUM values
        $allowed = ['pending', 'listed', 'inactive', 'removed'];
        $listings = collect($request->listings)->map(function ($l) use ($allowed) {
            $status = strtolower(trim($l['listing_status'] ?? 'pending'));

            // Map common aliases to ENUM values
            $aliases = [
                'active'    => 'listed',
                'published' => 'listed',
                'live'      => 'listed',
                'enabled'   => 'listed',
                'draft'     => 'pending',
                'paused'    => 'inactive',
                'disabled'  => 'inactive',
                'deleted'   => 'removed',
                'archived'  => 'removed',
                ''          => 'pending',
            ];
            $status = $aliases[$status] ?? $status;

            // Final fallback
            if (!in_array($status, $allowed)) {
                $status = 'pending';
            }

            $l['listing_status'] = $status;
            return $l;
        })->toArray();

        try {
            $this->catalogueService->updateListingStatus($listings, auth()->user());
            return back()->with('success', count($listings) . ' listing(s) updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Listing update failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update listings: ' . $e->getMessage())->withInput();
        }
    }

    public function skuDashboard(Request $request)
    {
        $companyCode = $request->get('company_code', '2100');
        $channelId   = $request->get('channel_id');
        $categoryId  = $request->get('category_id');

        $channels   = SalesChannel::active()->orderBy('name')->get();
        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);

        // Total products in scope
        $productQuery = Product::where('company_code', $companyCode)
            ->when($categoryId, fn ($q, $v) => $q->where('category_id', $v));

        $totalProducts = (clone $productQuery)->count();

        // Per-channel stats
        $channelStats = $channels->map(function ($ch) use ($productQuery, $companyCode) {
            $listed = ProductCatalogue::where('sales_channel_id', $ch->id)
                ->where('company_code', $companyCode)
                ->where('listing_status', 'listed')
                ->count();
            $pending = ProductCatalogue::where('sales_channel_id', $ch->id)
                ->where('company_code', $companyCode)
                ->where('listing_status', 'pending')
                ->count();
            $total = (clone $productQuery)->count();

            return [
                'channel'    => $ch,
                'listed'     => $listed,
                'pending'    => $pending,
                'not_listed' => max(0, $total - $listed - $pending),
                'total'      => $total,
            ];
        });

        // Category breakdown when channel selected
        $categoryStats = collect();
        if ($channelId) {
            $categoryStats = $categories->map(function ($cat) use ($channelId, $companyCode) {
                $totalInCat = Product::where('company_code', $companyCode)->where('category_id', $cat->id)->count();
                $listed = ProductCatalogue::whereHas('product', fn ($q) => $q->where('category_id', $cat->id)->where('company_code', $companyCode))
                    ->where('sales_channel_id', $channelId)
                    ->where('listing_status', 'listed')
                    ->count();
                $pending = ProductCatalogue::whereHas('product', fn ($q) => $q->where('category_id', $cat->id)->where('company_code', $companyCode))
                    ->where('sales_channel_id', $channelId)
                    ->where('listing_status', 'pending')
                    ->count();
                return [
                    'category' => $cat,
                    'listed'   => $listed,
                    'pending'  => $pending,
                    'total'    => $totalInCat,
                ];
            })->filter(fn ($cs) => $cs['total'] > 0);
        }

        $totals = [
            'total_products'    => $totalProducts,
            'total_listed'      => ProductCatalogue::where('company_code', $companyCode)->where('listing_status', 'listed')->distinct('product_id')->count('product_id'),
            'platforms_covered' => $channelStats->where('listed', '>', 0)->count(),
            'total_platforms'   => $channels->count(),
        ];

        return view('cataloguing.sku-dashboard', compact(
            'totals',
            'channels',
            'categories',
            'channelStats',
            'categoryStats',
            'companyCode',
            'channelId',
            'categoryId'
        ));
    }
}
