<?php

namespace App\Http\Controllers\Sourcing;

use App\Http\Controllers\Controller;
use App\Models\{Vendor, OfferSheet, Consignment, LiveSheet};
use App\Services\{DashboardService, VendorService, SourcingService};
use Illuminate\Http\Request;

class SourcingController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected VendorService $vendorService,
        protected SourcingService $sourcingService
    ) {}

    public function dashboard()
    {
        $data = $this->dashboardService->getSourcingDashboard();
        return view('sourcing.dashboard', compact('data'));
    }

    // ─── VENDOR ONBOARDING ───────────────────────────────────────
    public function createVendor()
    {
        return view('sourcing.vendors.create');
    }

    public function storeVendor(Request $request)
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'company_code' => 'required|in:2000,2100,2200',
        ]);
        $this->vendorService->createVendorRequest($request->all(), auth()->user());
        return redirect()->route('sourcing.dashboard')->with('success', 'Vendor request submitted to admin.');
    }

    public function vendors(Request $request)
    {
        $vendors = Vendor::with('user')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->latest()->paginate(25);
        return view('sourcing.vendors.index', compact('vendors'));
    }

    // ─── OFFER SHEET REVIEW ──────────────────────────────────────
    public function offerSheets(Request $request)
    {
        $sheets = OfferSheet::with('vendor')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest()->paginate(20);
        return view('sourcing.offer-sheets.index', compact('sheets'));
    }

    public function reviewOfferSheet(OfferSheet $offerSheet)
    {
        $offerSheet->load('items', 'vendor');
        return view('sourcing.offer-sheets.review', compact('offerSheet'));
    }

    public function selectProducts(Request $request, OfferSheet $offerSheet)
    {
        $request->validate(['selected_items' => 'required|array|min:1']);
        $this->sourcingService->selectProducts($offerSheet, $request->selected_items, auth()->user());
        return redirect()->route('sourcing.offer-sheets')->with('success', 'Products selected.');
    }

    public function convertToConsignment(OfferSheet $offerSheet)
    {
        $this->sourcingService->createConsignment($offerSheet);
        return redirect()->route('sourcing.consignments')->with('success', 'Consignment created.');
    }

    // ─── CONSIGNMENTS ────────────────────────────────────────────
    public function consignments(Request $request)
    {
        $consignments = Consignment::with('vendor', 'liveSheet')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->latest()->paginate(20);
        return view('sourcing.consignments.index', compact('consignments'));
    }

    // ─── LIVE SHEET ──────────────────────────────────────────────
    public function liveSheets(Request $request)
    {
        $liveSheets = LiveSheet::with('consignment.vendor')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest()->paginate(20);
        return view('sourcing.live-sheets.index', compact('liveSheets'));
    }

    public function approveLiveSheet(LiveSheet $liveSheet)
    {
        $this->sourcingService->approveLiveSheet($liveSheet, auth()->user());
        return redirect()->route('sourcing.live-sheets')->with('success', 'Live sheet approved and locked.');
    }

    // ─── CHARGEBACK CONFIRMATION ─────────────────────────────────
    public function pendingChargebacks()
    {
        $chargebacks = \App\Models\Chargeback::pending()->with('order', 'vendor')->latest()->paginate(20);
        return view('sourcing.chargebacks.index', compact('chargebacks'));
    }

    public function confirmChargeback(Request $request, \App\Models\Chargeback $chargeback)
    {
        $request->validate(['approved' => 'required|boolean']);
        app(\App\Services\FinanceService::class)->confirmChargeback($chargeback, auth()->user(), $request->boolean('approved'), $request->remarks);
        return back()->with('success', 'Chargeback ' . ($request->boolean('approved') ? 'confirmed' : 'rejected') . '.');
    }
}
