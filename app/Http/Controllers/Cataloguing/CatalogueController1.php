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
    ) {}

    public function dashboard(Request $request)
    {
        $companyCode = $request->get('company_code');
        $data = $this->dashboardService->getCataloguingDashboard($companyCode);
        return view('cataloguing.dashboard', compact('data', 'companyCode'));
    }

    public function pricingSheets(Request $request)
    {
        $pricings = PlatformPricing::with('product', 'salesChannel', 'asn')
            ->where('status', 'approved')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->latest()->paginate(30);
        return view('cataloguing.pricing-sheets', compact('pricings'));
    }

    public function listingPanel(Request $request)
    {
        $products = Product::with('catalogues.salesChannel')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->paginate(30);
        $channels = SalesChannel::active()->get();
        return view('cataloguing.listing-panel', compact('products', 'channels'));
    }

    public function updateListings(Request $request)
    {
        $request->validate(['listings' => 'required|array|min:1']);
        $this->catalogueService->updateListingStatus($request->listings, auth()->user());
        return back()->with('success', 'Listing status updated.');
    }

    public function skuDashboard(Request $request)
    {
        $companyCode = $request->get('company_code');
        $stats = $this->catalogueService->getListingStats($companyCode ?? '2100');
        return view('cataloguing.sku-dashboard', compact('stats', 'companyCode'));
    }
}
