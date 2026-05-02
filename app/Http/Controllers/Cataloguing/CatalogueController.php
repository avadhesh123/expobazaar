<?php

namespace App\Http\Controllers\Cataloguing;

use App\Http\Controllers\Controller;
use App\Models\{PlatformPricing, Product, SalesChannel};
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
        $pricings = PlatformPricing::with('product.category', 'product.vendor', 'salesChannel', 'asn')
            ->where('status', 'approved')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->channel_id, fn ($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->asn_id, fn ($q, $v) => $q->where('asn_id', $v))
            ->when($request->search, function ($q, $v) {
                $q->whereHas('product', fn($p) => $p->where('sku', 'LIKE', "%{$v}%")->orWhere('name', 'LIKE', "%{$v}%"));
            })
            ->latest()->paginate(30)->withQueryString();

        // Load product_details from live sheet items for each pricing
        $pricings->getCollection()->transform(function ($p) {
            $p->pd = [];
            if ($p->product) {
                $lsItem = \App\Models\LiveSheetItem::where('product_id', $p->product_id)->latest()->first();
                $p->pd = $lsItem ? ($lsItem->product_details ?? []) : [];
            }
            return $p;
        });

        $channels = SalesChannel::active()->orderBy('name')->get();
        $asns = \App\Models\Asn::orderBy('asn_number', 'desc')->limit(100)->get(['id', 'asn_number']);

        return view('cataloguing.pricing-sheets', compact('pricings', 'channels', 'asns'));
    }

    public function listingPanel(Request $request)
    {
        $products = Product::with('category', 'vendor')
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
            'listings.*.listing_status'    => 'nullable|string|max:50',
        ]);

        $allowed = ['pending', 'listed', 'inactive', 'removed'];
        $aliases = [
            'active' => 'listed', 'published' => 'listed', 'live' => 'listed', 'enabled' => 'listed',
            'draft' => 'pending', 'not_listed' => 'pending', 'unlisted' => 'pending',
            'paused' => 'inactive', 'disabled' => 'inactive',
            'deleted' => 'removed', 'archived' => 'removed', '' => 'pending',
        ];

        try {
            $updated = 0;
            foreach ($request->listings as $l) {
                $product = Product::find($l['product_id']);
                if (!$product) continue;

                $channelId = $l['sales_channel_id'];
                $status = strtolower(trim($l['listing_status'] ?? 'pending'));
                $status = $aliases[$status] ?? $status;
                if (!in_array($status, $allowed)) $status = 'pending';

                // Save to product.platform_listing_status JSON
                $pls = $product->platform_listing_status ?? [];
                $pls[$channelId] = $status;
                $product->update(['platform_listing_status' => $pls]);
                $updated++;
            }

            return back()->with('success', "{$updated} listing(s) updated successfully.");
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

        $productQuery = Product::where('company_code', $companyCode)
            ->when($categoryId, fn ($q, $v) => $q->where('category_id', $v));

        $totalProducts = (clone $productQuery)->count();
        $allProducts = (clone $productQuery)->whereNotNull('platform_listing_status')->get(['id', 'platform_listing_status', 'category_id']);

        // Per-channel stats from Product.platform_listing_status JSON
        $channelStats = $channels->map(function ($ch) use ($allProducts, $totalProducts) {
            $listed = 0; $pending = 0;
            foreach ($allProducts as $p) {
                $pls = $p->platform_listing_status ?? [];
                $status = $pls[$ch->id] ?? $pls[strval($ch->id)] ?? null;
                if ($status === 'listed') $listed++;
                elseif ($status === 'pending') $pending++;
            }
            return [
                'channel'    => $ch,
                'listed'     => $listed,
                'pending'    => $pending,
                'not_listed' => max(0, $totalProducts - $listed - $pending),
                'total'      => $totalProducts,
            ];
        });

        // Category breakdown when channel selected
        $categoryStats = collect();
        if ($channelId) {
            $categoryStats = $categories->map(function ($cat) use ($allProducts, $channelId, $companyCode) {
                $catProducts = $allProducts->where('category_id', $cat->id);
                $totalInCat = Product::where('company_code', $companyCode)->where('category_id', $cat->id)->count();
                $listed = 0; $pending = 0;
                foreach ($catProducts as $p) {
                    $pls = $p->platform_listing_status ?? [];
                    $status = $pls[$channelId] ?? $pls[strval($channelId)] ?? null;
                    if ($status === 'listed') $listed++;
                    elseif ($status === 'pending') $pending++;
                }
                return ['category' => $cat, 'listed' => $listed, 'pending' => $pending, 'total' => $totalInCat];
            })->filter(fn ($cs) => $cs['total'] > 0);
        }

        // Count total listed across all channels
        $totalListed = 0;
        foreach ($allProducts as $p) {
            $pls = $p->platform_listing_status ?? [];
            if (collect($pls)->contains('listed')) $totalListed++;
        }

        $totals = [
            'total_products'    => $totalProducts,
            'total_listed'      => $totalListed,
            'platforms_covered' => $channelStats->where('listed', '>', 0)->count(),
            'total_platforms'   => $channels->count(),
        ];

        return view('cataloguing.sku-dashboard', compact(
            'totals', 'channels', 'categories', 'channelStats', 'categoryStats',
            'companyCode', 'channelId', 'categoryId'
        ));
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
            ->orderBy('product_id')->get();

        $channels = SalesChannel::active()->orderBy('name')->get();

        // Header row matching Excel
        $headers = ['S.no','Vendor SKU','SAP Code','Barcode','Product Name','Description','Picture','HSN/HTS',
            'L (in)','W (in)','H (in)','Wt (lbs)','Material','Other Mat.','Color','Finish',
            'Category','Sub Cat.','Inner Qty','Inner L','Inner W','Inner H','Inner Wt (Lbs)',
            'Master Qty','Master L','Master W','Master H','Master Wt (Lbs)',
            'Final Qty *','Final FOB *','WSP ($)'];
        foreach ($channels as $ch) { $headers[] = $ch->name; }

        $csv = implode(',', $headers) . "\n";

        $sno = 0;
        $seen = [];
        foreach ($pricings as $p) {
            if (!$p->product) continue;
            // One row per product (not per channel)
            if (isset($seen[$p->product_id])) continue;
            $seen[$p->product_id] = true;
            $sno++;

            $lsItem = \App\Models\LiveSheetItem::where('product_id', $p->product_id)->latest()->first();
            $d = $lsItem ? ($lsItem->product_details ?? []) : [];

            $row = [
                $sno,
                '"' . ($p->product->sku ?? '') . '"',
                '"' . ($p->product->sap_code ?? '') . '"',
                '"' . ($d['barcode'] ?? '') . '"',
                '"' . str_replace('"','""', $p->product->name ?? '') . '"',
                '"' . str_replace('"','""', $d['description'] ?? $d['product_description'] ?? '') . '"',
                '', // Picture
                '"' . ($d['hsn_code'] ?? $d['hts_code'] ?? '') . '"',
                $d['product_length'] ?? $d['length'] ?? '',
                $d['product_width'] ?? $d['width'] ?? '',
                $d['product_height'] ?? $d['height'] ?? '',
                $d['product_weight'] ?? $d['weight_per_unit'] ?? '',
                '"' . ($d['material'] ?? '') . '"',
                '"' . ($d['other_material'] ?? '') . '"',
                '"' . ($d['color'] ?? '') . '"',
                '"' . ($d['finish'] ?? '') . '"',
                '"' . ($p->product->category->name ?? $d['category'] ?? '') . '"',
                '"' . ($d['sub_category'] ?? '') . '"',
                $d['qty_inner_pack'] ?? '',
                $d['inner_length'] ?? '',
                $d['inner_width'] ?? '',
                $d['inner_height'] ?? '',
                $d['inner_weight'] ?? '',
                $d['qty_master_pack'] ?? '',
                $d['master_length'] ?? '',
                $d['master_width'] ?? '',
                $d['master_height'] ?? '',
                $d['master_weight'] ?? '',
                $d['final_qty'] ?? $lsItem->quantity ?? '',
                $d['final_fob'] ?? $lsItem->unit_price ?? '',
                '$' . number_format(floatval($d['wsp'] ?? $p->wsp_price ?? 0), 2),
            ];

            // Channel prices
            $productPricings = $pricings->where('product_id', $p->product_id);
            foreach ($channels as $ch) {
                $chPricing = $productPricings->where('sales_channel_id', $ch->id)->first();
                $row[] = $chPricing ? '$' . number_format(floatval($chPricing->channel_price ?? $chPricing->selling_price ?? 0), 2) : '';
            }

            $csv .= implode(',', $row) . "\n";
        }

        $filename = 'Cataloging-' . ($request->company_code ?? 'all') . '-' . date('Y-m-d') . '.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
