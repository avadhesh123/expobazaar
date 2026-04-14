<?php

namespace App\Http\Controllers\Hod;

use App\Http\Controllers\Controller;
use App\Models\{Asn, PlatformPricing, SalesChannel};
use App\Services\{DashboardService, PricingService};
use Illuminate\Http\Request;

class HodController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected PricingService $pricingService
    ) {}

    public function dashboard(Request $request)
    {
        $data = $this->dashboardService->getHodDashboard();
        $data['pricing_stats'] = [
            'pending_preparation'  => Asn::whereIn('status', ['generated', 'locked'])->count(),
            'submitted_to_finance' => PlatformPricing::where('status', 'submitted')->distinct('asn_id')->count('asn_id'),
            'finance_approved'     => PlatformPricing::where('status', 'finance_approved')->distinct('asn_id')->count('asn_id'),
            'finalized'            => PlatformPricing::where('status', 'approved')->distinct('asn_id')->count('asn_id'),
            'rejected'             => PlatformPricing::where('status', 'rejected')->distinct('asn_id')->count('asn_id'),
        ];
        $data['recent_asns'] = Asn::with('shipment')->latest()->take(5)->get();
        return view('hod.dashboard', compact('data'));
    }

    public function asnList(Request $request)
    {
        $asns = Asn::with('shipment', 'platformPricing')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->latest()->paginate(20);

        $stats = [
            'total'         => Asn::count(),
            'needs_pricing' => Asn::whereIn('status', ['generated', 'locked'])->count(),
            'pricing_done'  => Asn::where('status', 'pricing_done')->count(),
            'finalized'     => Asn::where('status', 'finalized')->count(),
        ];

        return view('hod.asn.index', compact('asns', 'stats'));
    }

    public function preparePricing(Asn $asn)
    {
        $asn->load('shipment.consignments.vendor', 'shipment.consignments.liveSheet.items.product.category');
        $channels = SalesChannel::active()->orderBy('name')->get();
        $existingPricing = PlatformPricing::where('asn_id', $asn->id)
            ->get()
            ->groupBy(fn($p) => $p->product_id . '-' . $p->sales_channel_id);

        return view('hod.pricing.prepare', compact('asn', 'channels', 'existingPricing'));
    }

    public function storePricing(Request $request, Asn $asn)
    {
        $request->validate([
            'pricing'                    => 'required|array|min:1',
            'pricing.*.product_id'       => 'required|exists:products,id',
            'pricing.*.sales_channel_id' => 'required|exists:sales_channels,id',
            'pricing.*.cost_price'       => 'required|numeric|min:0',
            'pricing.*.platform_price'   => 'required|numeric|min:0',
            'pricing.*.selling_price'    => 'required|numeric|min:0',
            'pricing.*.map_price'        => 'nullable|numeric|min:0',
        ]);

        $this->pricingService->preparePricing($asn, $request->pricing, auth()->user());
        return redirect()->route('hod.asn-list')->with('success', 'Pricing submitted to Finance for review.');
    }

    public function pricingStatus(Asn $asn)
    {
        $asn->load('shipment');
        $pricings = PlatformPricing::where('asn_id', $asn->id)
            ->with('product', 'salesChannel', 'preparer', 'financeReviewer', 'approver')
            ->get();

        $byChannel = $pricings->groupBy('sales_channel_id');

        $summary = [
            'total_items'      => $pricings->count(),
            'total_cost'       => $pricings->sum('cost_price'),
            'total_selling'    => $pricings->sum('selling_price'),
            'avg_margin'       => $pricings->avg('margin_percent'),
            'submitted'        => $pricings->where('status', 'submitted')->count(),
            'finance_approved' => $pricings->where('status', 'finance_approved')->count(),
            'approved'         => $pricings->where('status', 'approved')->count(),
            'rejected'         => $pricings->where('status', 'rejected')->count(),
        ];

        return view('hod.pricing.status', compact('asn', 'pricings', 'byChannel', 'summary'));
    }

    public function finalizePricing(Asn $asn)
    {
        $pending = PlatformPricing::where('asn_id', $asn->id)
            ->whereNotIn('status', ['finance_approved', 'approved'])
            ->count();

        if ($pending > 0) {
            return back()->with('error', "Cannot finalize — {$pending} item(s) still pending Finance approval.");
        }

        $this->pricingService->finalizePricing($asn, auth()->user());
        return redirect()->route('hod.asn-list')->with('success', 'Pricing finalized and sent to Cataloguing Team.');
    }
}
