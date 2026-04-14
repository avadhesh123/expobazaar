<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\{FinanceReceivable, Chargeback, VendorPayout, Vendor, Order};
use App\Services\{DashboardService, FinanceService, VendorService};
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected FinanceService $financeService,
        protected VendorService $vendorService
    ) {}

    public function dashboard(Request $request)
    {
        $companyCode = $request->get('company_code');
        $data = $this->dashboardService->getFinanceDashboard($companyCode);
        return view('finance.dashboard', compact('data', 'companyCode'));
    }

    // ─── KYC APPROVAL ────────────────────────────────────────────
    public function pendingKyc()
    {
        $vendors = Vendor::pendingKyc()->with('documents', 'user')->latest()->paginate(20);
        return view('finance.kyc.index', compact('vendors'));
    }

    public function approveKyc(Vendor $vendor)
    {
        $this->vendorService->approveKyc($vendor, auth()->user());
        return back()->with('success', 'KYC approved. Contract sent.');
    }

    public function rejectKyc(Request $request, Vendor $vendor)
    {
        $request->validate(['reason' => 'required|string']);
        $this->vendorService->rejectKyc($vendor, auth()->user(), $request->reason);
        return back()->with('success', 'KYC rejected.');
    }

    // ─── RECEIVABLES ─────────────────────────────────────────────
    public function receivables(Request $request)
    {
        $receivables = FinanceReceivable::with('order.salesChannel')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn($q, $v) => $q->where('payment_status', $v))
            ->when($request->channel, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->latest()->paginate(30);
        return view('finance.receivables.index', compact('receivables'));
    }

    public function updateDeductions(Request $request, FinanceReceivable $receivable)
    {
        $request->validate([
            'platform_commission' => 'nullable|numeric|min:0',
            'platform_fee' => 'nullable|numeric|min:0',
            'insurance_charge' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
        ]);
        $this->financeService->updateDeductions($receivable, $request->all(), auth()->user());
        return back()->with('success', 'Deductions updated.');
    }

    public function recordPayment(Request $request, FinanceReceivable $receivable)
    {
        $request->validate(['amount_received' => 'required|numeric|min:0', 'payment_date' => 'required|date']);
        $this->financeService->recordPayment($receivable, $request->all());
        return back()->with('success', 'Payment recorded.');
    }

    // ─── CHARGEBACKS ─────────────────────────────────────────────
    public function chargebacks(Request $request)
    {
        $chargebacks = Chargeback::with('order', 'vendor')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest()->paginate(20);
        return view('finance.chargebacks.index', compact('chargebacks'));
    }

    public function raiseChargeback(Request $request, Order $order)
    {
        $request->validate(['amount' => 'required|numeric|min:0.01', 'reason' => 'required|string']);
        $this->financeService->raiseChargeback($order, $request->all());
        return back()->with('success', 'Chargeback raised.');
    }

    // ─── VENDOR PAYOUTS ──────────────────────────────────────────
    public function payouts(Request $request)
    {
        $payouts = VendorPayout::with('vendor')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->month, fn($q, $v) => $q->where('payout_month', $v))
            ->when($request->year, fn($q, $v) => $q->where('payout_year', $v))
            ->latest()->paginate(20);
        return view('finance.payouts.index', compact('payouts'));
    }

    public function calculatePayout(Request $request)
    {
        $request->validate(['vendor_id' => 'required|exists:vendors,id', 'month' => 'required|integer|between:1,12', 'year' => 'required|integer']);
        $vendor = Vendor::findOrFail($request->vendor_id);
        $this->financeService->calculateVendorPayout($vendor, $request->month, $request->year);
        return back()->with('success', 'Payout calculated.');
    }

    public function processPayment(Request $request, VendorPayout $payout)
    {
        $request->validate(['payment_date' => 'required|date', 'payment_reference' => 'nullable|string']);
        $this->financeService->processPayment($payout, $request->all());
        return back()->with('success', 'Payment processed. Vendor notified.');
    }

    // ─── PRICING REVIEW ──────────────────────────────────────────
    public function pricingReview(Request $request)
    {
        $pricings = \App\Models\PlatformPricing::with('product', 'salesChannel', 'asn')
            ->where('status', 'submitted')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->paginate(30);
        return view('finance.pricing-review', compact('pricings'));
    }

    public function approvePricing(\App\Models\Asn $asn)
    {
        app(\App\Services\PricingService::class)->reviewPricing($asn, auth()->user(), true);
        return back()->with('success', 'Pricing approved.');
    }
}
