<?php

namespace App\Http\Controllers\Hod;

use App\Http\Controllers\Controller;
use App\Models\{Asn, PlatformPricing};
use App\Services\{DashboardService, PricingService};
use Illuminate\Http\Request;

class HodController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected PricingService $pricingService
    ) {}

    public function dashboard()
    {
        $data = $this->dashboardService->getHodDashboard();
        return view('hod.dashboard', compact('data'));
    }

    // ─── PLATFORM PRICING ────────────────────────────────────────
    public function asnList(Request $request)
    {
        $asns = Asn::with('shipment')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest()->paginate(20);
        return view('hod.asn.index', compact('asns'));
    }

    public function preparePricing(Asn $asn)
    {
        $asn->load('shipment.consignments.liveSheet.items.product');
        $channels = \App\Models\SalesChannel::active()->get();
        return view('hod.pricing.prepare', compact('asn', 'channels'));
    }

    public function storePricing(Request $request, Asn $asn)
    {
        $request->validate(['pricing' => 'required|array|min:1']);
        $this->pricingService->preparePricing($asn, $request->pricing, auth()->user());
        return redirect()->route('hod.asn-list')->with('success', 'Pricing submitted for finance review.');
    }

    public function finalizePricing(Asn $asn)
    {
        $this->pricingService->finalizePricing($asn, auth()->user());
        return redirect()->route('hod.asn-list')->with('success', 'Pricing finalized and sent to cataloguing.');
    }
}
