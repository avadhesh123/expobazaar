<?php

namespace App\Http\Controllers\Cataloguing;

use App\Http\Controllers\Controller;
use App\Models\{PlatformPricing, ProductCatalogue, Product, SalesChannel, Category};
use App\Services\{DashboardService, CatalogueService};
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected CatalogueService $catalogueService
    ) {}

    public function dashboard(Request $request)
    {
        $companyCode = $request->get('company_code');
        $data = $this->dashboardService->getCataloguingDashboard($companyCode);

        // Extra stats
        $data['listing_summary'] = [
            'total_skus'    => Product::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->count(),
            'listed'        => ProductCatalogue::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->where('listing_status', 'listed')->distinct('product_id')->count('product_id'),
            'pending'       => ProductCatalogue::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->where('listing_status', 'pending')->distinct('product_id')->count('product_id'),
            'not_listed'    => ProductCatalogue::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->where('listing_status', 'not_listed')->distinct('product_id')->count('product_id'),
            'pricing_ready' => PlatformPricing::where('status', 'approved')->when($companyCode, fn($q) => $q->where('company_code', $companyCode))->distinct('product_id')->count('product_id'),
        ];

        $data['by_channel'] = SalesChannel::active()->get()->map(fn($ch) => [
            'channel' => $ch,
            'listed'  => ProductCatalogue::where('sales_channel_id', $ch->id)->where('listing_status', 'listed')->when($companyCode, fn($q) => $q->where('company_code', $companyCode))->count(),
            'total'   => ProductCatalogue::where('sales_channel_id', $ch->id)->when($companyCode, fn($q) => $q->where('company_code', $companyCode))->count(),
        ]);

        return view('cataloguing.dashboard', compact('data', 'companyCode'));
    }

    // =====================================================================
    //  PRICING SHEETS — Receive from HOD, download CSV
    // =====================================================================

    public function pricingSheets(Request $request)
    {
        $pricings = PlatformPricing::with('product.vendor', 'product.category', 'salesChannel', 'asn')
            ->where('status', 'approved')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->asn_id, fn($q, $v) => $q->where('asn_id', $v))
            ->latest()->paginate(30);

        $channels = SalesChannel::active()->get();
        $asns = \App\Models\Asn::where('status', 'finalized')->get();

        return view('cataloguing.pricing-sheets', compact('pricings', 'channels', 'asns'));
    }

    /**
     * Download pricing sheet as CSV
     */
    public function downloadPricingSheet(Request $request)
    {
        $pricings = PlatformPricing::with('product.vendor', 'product.category', 'salesChannel', 'asn')
            ->where('status', 'approved')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->asn_id, fn($q, $v) => $q->where('asn_id', $v))
            ->orderBy('sales_channel_id')->get();

        $csv = "SKU,Product Name,Category,Vendor,Platform,ASN,Company Code,Cost Price,Platform Price,Selling Price,MAP Price,Margin %,Listing SKU,Listing URL,Shopify URL\n";
        foreach ($pricings as $p) {
            $catalogue = ProductCatalogue::where('product_id', $p->product_id)->where('sales_channel_id', $p->sales_channel_id)->first();
            $csv .= implode(',', [
                $p->product->sku ?? '',
                '"' . str_replace('"', '""', $p->product->name ?? '') . '"',
                '"' . str_replace('"', '""', $p->product->category->name ?? '') . '"',
                '"' . str_replace('"', '""', $p->product->vendor->company_name ?? '') . '"',
                $p->salesChannel->name ?? '',
                $p->asn->asn_number ?? '',
                $p->company_code,
                $p->cost_price,
                $p->platform_price,
                $p->selling_price,
                $p->map_price ?? '',
                $p->margin_percent,
                $catalogue->listing_sku ?? '',
                $catalogue->listing_url ?? '',
                $catalogue->shopify_url ?? '',
            ]) . "\n";
        }

        $filename = 'pricing-sheet-' . ($request->company_code ?? 'all') . '-' . date('Y-m-d') . '.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Upload completed catalogue sheet
     */
    public function uploadCatalogue(Request $request)
    {
        $request->validate([
            'catalogue_file' => 'required|file|mimes:csv,xlsx|max:10240',
            'company_code'   => 'required|in:2000,2100,2200',
        ]);

        $path = $request->file('catalogue_file')->store('catalogue-uploads/' . $request->company_code, 'public');

        \App\Models\ActivityLog::log('uploaded', 'catalogue', null, null,
            ['file' => $path, 'company_code' => $request->company_code],
            'Catalogue sheet uploaded');

        return back()->with('success', 'Catalogue sheet uploaded successfully. File: ' . basename($path));
    }

    // =====================================================================
    //  LISTING PANEL — Platform Master Panel with checkbox status update
    // =====================================================================

    public function listingPanel(Request $request)
    {
        $query = Product::with('catalogues.salesChannel', 'vendor', 'category')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($request->vendor_id, fn($q, $v) => $q->where('vendor_id', $v))
            ->when($request->search, fn($q, $v) => $q->where('sku', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%"))
            ->when($request->listing_filter, function ($q, $v) {
                if ($v === 'fully_listed') {
                    return $q->whereHas('catalogues', fn($cq) => $cq->where('listing_status', 'listed'), '>=', SalesChannel::active()->count());
                } elseif ($v === 'partially_listed') {
                    return $q->has('catalogues')->whereDoesntHave('catalogues', fn($cq) => $cq->where('listing_status', 'listed'), '>=', SalesChannel::active()->count());
                } elseif ($v === 'not_listed') {
                    return $q->doesntHave('catalogues');
                }
            });

        $products = $query->paginate(30)->appends($request->query());
        $channels = SalesChannel::active()->orderBy('name')->get();
        $categories = Category::whereNull('parent_id')->orderBy('name')->get();
        $vendors = \App\Models\Vendor::active()->orderBy('company_name')->get();

        return view('cataloguing.listing-panel', compact('products', 'channels', 'categories', 'vendors'));
    }

    public function updateListings(Request $request)
    {
        $request->validate(['listings' => 'required|array|min:1']);
        $this->catalogueService->updateListingStatus($request->listings, auth()->user());
        return back()->with('success', 'Listing status updated successfully.');
    }

    // =====================================================================
    //  SKU DASHBOARD — Platform listing stats with filters
    // =====================================================================

    public function skuDashboard(Request $request)
    {
        $companyCode = $request->get('company_code', '2100');
        $categoryId = $request->get('category_id');
        $channelId = $request->get('channel_id');

        $channels = SalesChannel::active()->get();
        $categories = Category::whereNull('parent_id')->orderBy('name')->get();

        // Per-channel stats
        $channelStats = $channels->map(function ($ch) use ($companyCode, $categoryId) {
            $base = ProductCatalogue::where('sales_channel_id', $ch->id)->where('company_code', $companyCode);
            if ($categoryId) {
                $base = $base->whereHas('product', fn($q) => $q->where('category_id', $categoryId));
            }
            return [
                'channel'    => $ch,
                'listed'     => (clone $base)->where('listing_status', 'listed')->count(),
                'pending'    => (clone $base)->where('listing_status', 'pending')->count(),
                'not_listed' => (clone $base)->where('listing_status', 'not_listed')->count(),
                'total'      => (clone $base)->count(),
            ];
        });

        // Per-category stats (if a channel is selected)
        $categoryStats = collect();
        if ($channelId) {
            $categoryStats = $categories->map(function ($cat) use ($companyCode, $channelId) {
                $base = ProductCatalogue::where('sales_channel_id', $channelId)->where('company_code', $companyCode)
                    ->whereHas('product', fn($q) => $q->where('category_id', $cat->id));
                return [
                    'category'   => $cat,
                    'listed'     => (clone $base)->where('listing_status', 'listed')->count(),
                    'pending'    => (clone $base)->where('listing_status', 'pending')->count(),
                    'total'      => (clone $base)->count(),
                ];
            })->filter(fn($c) => $c['total'] > 0);
        }

        // Overall totals
        $totals = [
            'total_products'    => Product::where('company_code', $companyCode)->when($categoryId, fn($q) => $q->where('category_id', $categoryId))->count(),
            'total_listed'      => ProductCatalogue::where('company_code', $companyCode)->where('listing_status', 'listed')->when($categoryId, fn($q) => $q->whereHas('product', fn($pq) => $pq->where('category_id', $categoryId)))->distinct('product_id')->count('product_id'),
            'platforms_covered' => $channelStats->filter(fn($c) => $c['listed'] > 0)->count(),
            'total_platforms'   => $channels->count(),
        ];

        return view('cataloguing.sku-dashboard', compact('channelStats', 'categoryStats', 'channels', 'categories', 'totals', 'companyCode', 'categoryId', 'channelId'));
    }
}
