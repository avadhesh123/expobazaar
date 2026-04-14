<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\{FinanceReceivable, Chargeback, VendorPayout, Vendor, Order, SalesChannel};
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

        // Enhanced dashboard data
        $data['kpis'] = array_merge($data['kpis'] ?? [], [
            'unpaid_orders'     => FinanceReceivable::where('payment_status', 'unpaid')->when($companyCode, fn($q) => $q->where('company_code', $companyCode))->count(),
            'receivables'       => FinanceReceivable::where('payment_status', 'unpaid')->when($companyCode, fn($q) => $q->where('company_code', $companyCode))->sum('net_receivable'),
            'payouts_pending'   => VendorPayout::whereIn('status', ['calculated', 'approved', 'payment_pending'])->when($companyCode, fn($q) => $q->where('company_code', $companyCode))->sum('net_payout'),
            'platform_deductions' => FinanceReceivable::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->whereMonth('created_at', now()->month)->sum(\DB::raw('platform_commission + platform_fee')),
            'chargebacks'       => Chargeback::whereMonth('created_at', now()->month)->when($companyCode, fn($q) => $q->where('company_code', $companyCode))->sum('amount'),
            'pending_chargebacks' => Chargeback::where('status', 'pending_confirmation')->count(),
        ]);

        $data['unpaid_by_platform'] = FinanceReceivable::where('payment_status', 'unpaid')
            ->when($companyCode, fn($q) => $q->where('company_code', $companyCode))
            ->selectRaw('sales_channel_id, COUNT(*) as count, SUM(net_receivable) as total')
            ->with('salesChannel')
            ->groupBy('sales_channel_id')->get();

        $data['vendor_settlements'] = VendorPayout::with('vendor')
            ->where('payout_month', now()->month)->where('payout_year', now()->year)
            ->when($companyCode, fn($q) => $q->where('company_code', $companyCode))
            ->latest()->take(10)->get();

        return view('finance.dashboard', compact('data', 'companyCode'));
    }

    // =====================================================================
    //  KYC APPROVAL
    // =====================================================================

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

    // =====================================================================
    //  RECEIVABLES — Only unpaid orders visible until payment recorded
    // =====================================================================

    public function receivables(Request $request)
    {
        $receivables = FinanceReceivable::with('order.salesChannel', 'order.items.product')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn($q, $v) => $q->where('payment_status', $v))
            ->when($request->channel, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->search, fn($q, $v) => $q->whereHas('order', fn($oq) => $oq->where('order_number', 'like', "%{$v}%")->orWhere('platform_order_id', 'like', "%{$v}%")))
            ->when(!$request->status, fn($q) => $q->where('payment_status', '!=', 'paid')) // Default: show unpaid only
            ->latest()->paginate(30);

        $channels = SalesChannel::active()->get();

        $summary = [
            'unpaid_count'   => FinanceReceivable::where('payment_status', 'unpaid')->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->count(),
            'unpaid_total'   => FinanceReceivable::where('payment_status', 'unpaid')->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('net_receivable'),
            'partial_count'  => FinanceReceivable::where('payment_status', 'partial')->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->count(),
            'partial_total'  => FinanceReceivable::where('payment_status', 'partial')->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('net_receivable'),
            'total_deductions' => FinanceReceivable::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum(\DB::raw('platform_commission + platform_fee + insurance_charge + other_deductions')),
        ];

        return view('finance.receivables.index', compact('receivables', 'channels', 'summary'));
    }

    /**
     * Download receivables template as CSV
     */
    public function downloadReceivablesTemplate(Request $request)
    {
        $receivables = FinanceReceivable::with('order.salesChannel', 'order.items.product')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->where('payment_status', '!=', 'paid')
            ->orderBy('company_code')->get();

        $csv = "Order Number,Platform Order ID,Sales Channel,Company Code,Order Amount,Platform Commission,Platform Fee,Insurance,Chargeback,Other Deductions,Net Receivable,Amount Received,Payment Date,Payment Reference,Bank Reference,Payment Status\n";
        foreach ($receivables as $r) {
            $csv .= implode(',', [
                $r->order->order_number ?? '',
                $r->order->platform_order_id ?? '',
                $r->order->salesChannel->name ?? '',
                $r->company_code,
                $r->order_amount,
                $r->platform_commission,
                $r->platform_fee,
                $r->insurance_charge,
                $r->chargeback_amount,
                $r->other_deductions,
                $r->net_receivable,
                $r->amount_received,
                $r->payment_date?->format('Y-m-d') ?? '',
                $r->payment_reference ?? '',
                $r->bank_reference ?? '',
                $r->payment_status,
            ]) . "\n";
        }

        $filename = 'receivables-' . ($request->company_code ?? 'all') . '-' . date('Y-m-d') . '.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function updateDeductions(Request $request, FinanceReceivable $receivable)
    {
        $request->validate([
            'platform_commission' => 'nullable|numeric|min:0',
            'platform_fee'        => 'nullable|numeric|min:0',
            'insurance_charge'    => 'nullable|numeric|min:0',
            'other_deductions'    => 'nullable|numeric|min:0',
            'deduction_notes'     => 'nullable|string|max:500',
        ]);
        $this->financeService->updateDeductions($receivable, $request->all(), auth()->user());
        return back()->with('success', 'Deductions updated. Net receivable recalculated.');
    }

    public function recordPayment(Request $request, FinanceReceivable $receivable)
    {
        $request->validate([
            'amount_received'    => 'required|numeric|min:0',
            'payment_date'       => 'required|date',
            'payment_reference'  => 'nullable|string|max:100',
            'bank_reference'     => 'nullable|string|max:100',
        ]);
        $this->financeService->recordPayment($receivable, $request->all());
        return back()->with('success', 'Payment recorded. Order payment status updated.');
    }

    // =====================================================================
    //  CHARGEBACKS — Raise, notify sourcing, reflect to vendor
    // =====================================================================

    public function chargebacks(Request $request)
    {
        $chargebacks = Chargeback::with('order.salesChannel', 'vendor', 'raiser', 'confirmer')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->vendor_id, fn($q, $v) => $q->where('vendor_id', $v))
            ->latest()->paginate(20);

        $vendors = Vendor::active()->orderBy('company_name')->get();

        $stats = [
            'total'     => Chargeback::count(),
            'pending'   => Chargeback::where('status', 'pending_confirmation')->count(),
            'confirmed' => Chargeback::where('status', 'confirmed')->count(),
            'rejected'  => Chargeback::where('status', 'rejected')->count(),
            'total_amount' => Chargeback::where('status', 'confirmed')->sum('amount'),
        ];

        return view('finance.chargebacks.index', compact('chargebacks', 'vendors', 'stats'));
    }

    public function raiseChargeback(Request $request, Order $order)
    {
        $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'reason'      => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);
        $this->financeService->raiseChargeback($order, $request->all());
        return back()->with('success', 'Chargeback raised. Notification sent to Sourcing team for confirmation.');
    }

    // =====================================================================
    //  VENDOR PAYOUTS — Calculate, process, payment advice, invoice
    // =====================================================================

    public function payouts(Request $request)
    {
        $payouts = VendorPayout::with('vendor')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->vendor_id, fn($q, $v) => $q->where('vendor_id', $v))
            ->when($request->month, fn($q, $v) => $q->where('payout_month', $v))
            ->when($request->year, fn($q, $v) => $q->where('payout_year', $v))
            ->latest()->paginate(20);

        $vendors = Vendor::active()->orderBy('company_name')->get();

        $summary = [
            'total_payouts'   => VendorPayout::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->whereIn('status', ['calculated', 'approved', 'payment_pending'])->sum('net_payout'),
            'paid_this_month' => VendorPayout::where('status', 'paid')->whereMonth('payment_date', now()->month)->sum('net_payout'),
            'pending_invoices' => VendorPayout::where('status', 'paid')->whereNull('vendor_invoice_file')->count(),
        ];

        return view('finance.payouts.index', compact('payouts', 'vendors', 'summary'));
    }

    public function calculatePayout(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'month'     => 'required|integer|between:1,12',
            'year'      => 'required|integer',
        ]);
        $vendor = Vendor::findOrFail($request->vendor_id);
        $payout = $this->financeService->calculateVendorPayout($vendor, $request->month, $request->year);
        $net = number_format($payout->net_payout, 2);
        return back()->with('success', "Payout calculated for {$vendor->company_name}. Net: \${$net}");
    }

    public function processPayment(Request $request, VendorPayout $payout)
    {
        $request->validate([
            'payment_date'      => 'required|date',
            'payment_reference' => 'nullable|string|max:100',
            'payment_method'    => 'nullable|string|max:50',
        ]);
        $this->financeService->processPayment($payout, $request->all());
        $vendorName = $payout->vendor->company_name ?? 'vendor';
        return back()->with('success', "Payment processed for {$vendorName}. Payment advice sent via email.");
    }

    /**
     * View payout detail with breakdown
     */
    public function showPayout(VendorPayout $payout)
    {
        $payout->load('vendor');

        $startDate = now()->setYear($payout->payout_year)->setMonth($payout->payout_month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Get the underlying orders
        $orders = Order::whereHas('items', fn($q) => $q->where('vendor_id', $payout->vendor_id))
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('company_code', $payout->company_code)
            ->with('salesChannel', 'items')->get();

        // Get warehouse charges
        $warehouseCharges = \App\Models\WarehouseCharge::where('vendor_id', $payout->vendor_id)
            ->where('charge_month', $payout->payout_month)
            ->where('charge_year', $payout->payout_year)
            ->with('warehouse')->get();

        // Get chargebacks
        $chargebacks = Chargeback::where('vendor_id', $payout->vendor_id)
            ->where('status', 'confirmed')
            ->whereBetween('confirmed_at', [$startDate, $endDate])
            ->with('order')->get();

        return view('finance.payouts.show', compact('payout', 'orders', 'warehouseCharges', 'chargebacks'));
    }

    /**
     * Download payment advice as CSV
     */
    public function downloadPaymentAdvice(VendorPayout $payout)
    {
        $payout->load('vendor');
        $vendor = $payout->vendor;
        $period = date('F', mktime(0, 0, 0, $payout->payout_month, 1)) . ' ' . $payout->payout_year;

        $csv = "PAYMENT ADVICE\n";
        $csv .= "Vendor,{$vendor->company_name}\n";
        $csv .= "Vendor Code,{$vendor->vendor_code}\n";
        $csv .= "Company Code,{$payout->company_code}\n";
        $csv .= "Period,{$period}\n\n";
        $csv .= "Description,Amount\n";
        $csv .= "Total Sales,{$payout->total_sales}\n";
        $csv .= "Storage Charges,-{$payout->total_storage_charges}\n";
        $csv .= "Inward Charges,-{$payout->total_inward_charges}\n";
        $csv .= "Logistics Charges,-{$payout->total_logistics_charges}\n";
        $csv .= "Platform Deductions,-{$payout->total_platform_deductions}\n";
        $csv .= "Chargebacks,-{$payout->total_chargebacks}\n";
        $csv .= "NET PAYOUT,{$payout->net_payout}\n\n";
        $csv .= "Payment Date,{$payout->payment_date?->format('Y-m-d')}\n";
        $csv .= "Payment Reference,{$payout->payment_reference}\n";
        $csv .= "Payment Method,{$payout->payment_method}\n";

        $filename = "payment-advice-{$vendor->vendor_code}-{$payout->payout_month}-{$payout->payout_year}.csv";
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Upload vendor invoice against a payout
     */
    public function uploadVendorInvoice(Request $request, VendorPayout $payout)
    {
        $request->validate([
            'vendor_invoice_file'   => 'required|file|mimes:pdf,jpg,png|max:10240',
            'vendor_invoice_number' => 'required|string|max:100',
        ]);

        $path = $request->file('vendor_invoice_file')->store('vendor-invoices/' . $payout->company_code, 'public');

        $payout->update([
            'vendor_invoice_file'   => $path,
            'vendor_invoice_number' => $request->vendor_invoice_number,
            'vendor_invoice_date'   => now(),
            'status'                => 'invoice_received',
        ]);

        return back()->with('success', 'Vendor invoice uploaded. Invoice #: ' . $request->vendor_invoice_number);
    }

    // =====================================================================
    //  PRICING REVIEW
    // =====================================================================

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
