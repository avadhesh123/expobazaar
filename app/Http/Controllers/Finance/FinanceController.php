<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\{FinanceReceivable, Chargeback, VendorPayout, Vendor, Order, SalesChannel, LiveSheet,PayoutWarehouseAdjustment};
use App\Services\{DashboardService, FinanceService, VendorService};
use Illuminate\Http\Request;
use App\Services\VendorPayoutService;
use App\Helpers\FileStorage;


class FinanceController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected FinanceService $financeService,
        protected VendorService $vendorService
    ) {
    }

    public function dashboard(Request $request)
    {
        // $companyCode = $request->get('company_code');
        $companyCode = session('active_company');
        $data = $this->dashboardService->getFinanceDashboard($companyCode);
        return view('finance.dashboard', compact('data', 'companyCode'));
    }

    // ─── KYC APPROVAL ────────────────────────────────────────────
    public function pendingKyc()
    {
        $vendors = Vendor::pendingKyc()->with('documents', 'user')->latest()->paginate(20);
        return view('finance.kyc.index', compact('vendors'));
    }

    public function approveKyc(Request $request, Vendor $vendor)
    {
        $request->validate([
            'vendor_code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9\-_]+$/',
                \Illuminate\Validation\Rule::unique('vendors', 'vendor_code')->ignore($vendor->id),
            ],
        ], [
            'vendor_code.required' => 'Vendor code is required to approve KYC.',
            'vendor_code.unique'   => 'This vendor code is already in use. Please choose another.',
            'vendor_code.regex'    => 'Vendor code can only contain letters, numbers, hyphens and underscores.',
        ]);

        $vendor->update(['vendor_code' => strtoupper(trim($request->vendor_code))]);
        $this->vendorService->approveKyc($vendor, auth()->user());

        \App\Models\ActivityLog::log('approved', 'vendor_kyc', $vendor, null, ['vendor_code' => $vendor->vendor_code], 'KYC approved with vendor code ' . $vendor->vendor_code);

        return back()->with('success', 'KYC approved. Vendor code ' . $vendor->vendor_code . ' assigned. Contract sent.');
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
        $activeCode = session('active_company');

        $receivables = FinanceReceivable::with('order.salesChannel', 'order.chargebacks')
            ->where('company_code', $activeCode)
            ->when($request->status, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->channel, fn ($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->payment_status, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->search, fn ($q, $v) => $q->whereHas('order', fn ($q) => $q->where('order_number', 'like', "%{$v}%")))
            ->orderByRaw("FIELD(payment_status, 'unpaid', 'partial', 'paid') ASC")
            ->latest()
            ->paginate(30);


        $channels = SalesChannel::where('is_active', true)
            ->whereJsonContains('company_codes', $activeCode)
            ->orderBy('name')
            ->get();

        // Fix: $summary was never built — blade was crashing with "Undefined variable: summary"
        $summary = [
            'unpaid_count'     => FinanceReceivable::where('payment_status', 'unpaid')
                ->when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->count(),
            'unpaid_total'     => FinanceReceivable::where('payment_status', 'unpaid')
                ->when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->sum('net_receivable'),
            'partial_count'    => FinanceReceivable::where('payment_status', 'partial')
                ->when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->count(),
            'partial_total'    => FinanceReceivable::where('payment_status', 'partial')
                ->when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->sum('net_receivable'),
            'total_deductions' => FinanceReceivable::when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->sum('platform_commission')
                + FinanceReceivable::when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->sum('platform_fee')
                + FinanceReceivable::when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->sum('insurance_charge')
                + FinanceReceivable::when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->sum('other_deductions'),
        ];

        return view('finance.receivables.index', compact('receivables', 'channels', 'summary'));
    }

    public function downloadReceivablesTemplate()
    {
        $csv = "Order Number,Platform Order ID,Sales Channel,Order Date,Gross Amount,Platform Commission,Platform Fee,Insurance Charge,Chargeback,Other Deductions,Net Amount,Amount Received,Payment Date,Payment Reference,Status\n";
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Receivables_Template.csv"',
        ]);
    }

    public function updateDeductions(Request $request, FinanceReceivable $receivable)
    {
        $request->validate([
            'platform_commission' => 'nullable|numeric|min:0',
            'platform_fee'        => 'nullable|numeric|min:0',
            'insurance_charge'    => 'nullable|numeric|min:0',
            'other_deductions'    => 'nullable|numeric|min:0',
        ]);
        $this->financeService->updateDeductions($receivable, $request->all(), auth()->user());
        return back()->with('success', 'Deductions updated. Net amount recalculated.');
    }

    public function recordPayment(Request $request, FinanceReceivable $receivable)
    {
        $request->validate([
            'amount_received'    => 'required|numeric|min:0',
            'payment_date'       => 'required|date',
            'payment_reference'  => 'nullable|string|max:255',
        ]);
        $this->financeService->recordPayment($receivable, $request->all());
        return back()->with('success', 'Payment recorded. Order marked as paid.');
    }

    // ─── CHARGEBACKS ─────────────────────────────────────────────
    public function chargebacks(Request $request)
    {

        $activeCode = session('active_company');

        // AJAX: Lookup order by number
        if ($request->has('lookup_order')) {
            $orderNumber = trim($request->lookup_order);
            $order = Order::withoutGlobalScopes()->where('order_number', $orderNumber)
                ->orWhere('platform_order_id', $orderNumber)
                ->with(['items.product', 'salesChannel'])
                ->first();

            if (!$order) {
                return response()->json(['found' => false]);
            }

            $items = $order->items->map(fn ($item) => [
                'id'         => $item->id,
                'sku'        => $item->sku ?? $item->product->sku ?? '—',
                'name'       => $item->product->name ?? '—',
                'quantity'   => $item->quantity,
                'unit_price' => floatval($item->unit_price),
                'total'      => floatval($item->total_price),
            ]);

            $existingCb = Chargeback::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'pending_confirmation', 'confirmed'])
                ->first();

            return response()->json([
                'found'     => true,
                'order_id'  => $order->id,
                'order_number' => $order->order_number,
                'channel'   => $order->salesChannel->name ?? '—',
                'date'      => $order->order_date?->format('d M Y'),
                'total'     => floatval($order->total_amount),
                'status'    => $order->status,
                'customer'  => $order->customer_name ?? '—',
                'items'     => $items,
                'has_active_chargeback' => $existingCb ? true : false,
                'existing_cb_status'    => $existingCb?->status,
                'existing_cb_amount'    => $existingCb ? floatval($existingCb->amount) : null,
            ]);
        }

        $chargebacks = Chargeback::with('order.salesChannel', 'vendor')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($activeCode, fn ($q, $v) => $q->whereHas('order', fn ($oq) => $oq->where('company_code', $v)))
            ->latest()->paginate(20);

        $stats = [
            'total'        => Chargeback::when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->count(),
            'pending'      => Chargeback::when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->where('status', 'pending_confirmation')->count(),
            'confirmed'    => Chargeback::when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->where('status', 'confirmed')->count(),
            'total_amount' => Chargeback::when($activeCode, fn ($q, $v) => $q->where('company_code', $v))->where('status', 'confirmed')->sum('amount'),
        ];

        // Fix #2: $vendors was never passed — blade vendor filter crashed with Undefined variable
        $vendors = Vendor::active()->orderBy('company_name')->get();

        return view('finance.chargebacks.index', compact('chargebacks', 'stats', 'vendors'));
    }

    public function raiseChargeback(Request $request, $orderNumber)
    {
        $request->validate([
            'amount'                       => 'required|numeric|min:0.01',
            'reason'                       => 'required|string|max:500',
            'description'                  => 'nullable|string|max:1000',
            'chargeback_items'             => 'nullable|array',
            'chargeback_items.*.item_id'   => 'nullable|integer',
            'chargeback_items.*.evidence'  => 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $order = Order::withoutGlobalScopes()->where('order_number', $orderNumber)
            ->orWhere('platform_order_id', $orderNumber)
            ->first();

        if (!$order) {
            return back()->with('error', 'Order not found.')->withInput();
        }

        try {
            if (in_array($order->status ?? '', ['cancelled', 'refunded', 'voided'])) {
                return back()->with('error', "Cannot raise chargeback on a {$order->status} order.");
            }

            if (!$order->items()->whereNotNull('vendor_id')->exists()) {
                return back()->with('error', 'Order has no vendor associated.');
            }

            $orderTotal = floatval($order->total_amount ?? 0);
            if ($orderTotal > 0 && $request->amount > $orderTotal) {
                return back()->with('error', "Chargeback amount exceeds order total (\${$orderTotal}).");
            }

            $existing = Chargeback::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'pending_confirmation', 'confirmed'])
                ->first();
            if ($existing) {
                return back()->with('error', "Active chargeback already exists (Status: {$existing->status}).");
            }

            // Handle per-item evidence uploads
            $itemsData = [];
            if ($request->chargeback_items) {
                foreach ($request->chargeback_items as $idx => $itemData) {
                    if (empty($itemData['item_id'])) {
                        continue;
                    }

                    $evidencePath = null;
                    if ($request->hasFile("chargeback_items.{$idx}.evidence")) {
                        $evidencePath = $request->file("chargeback_items.{$idx}.evidence")
                            ->store("chargebacks/{$order->id}/items", FileStorage::disk());
                    }

                    $itemsData[] = [
                        'item_id'  => intval($itemData['item_id']),
                        'evidence' => $evidencePath,
                    ];
                }
            }

            $cbData = $request->only(['amount', 'reason', 'description']);
            $cbData['chargeback_items'] = !empty($itemsData) ? json_encode($itemsData) : null;

            $this->financeService->raiseChargeback($order, $cbData);

            return back()->with('success', "Chargeback of \${$request->amount} raised on order #{$order->order_number}. Sourcing team notified.");
        } catch (\Exception $e) {
            \Log::error('Chargeback failed: ' . $e->getMessage(), ['order_id' => $order->id ?? null]);
            return back()->with('error', 'Failed: ' . $e->getMessage())->withInput();
        }
    }

    // ─── VENDOR PAYOUTS ──────────────────────────────────────────
    public function payouts(Request $request)
    {
        $activeCode = session('active_company');

        $payouts = VendorPayout::with('vendor')
            ->where('company_code', $activeCode)
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->month, fn ($q, $v) => $q->where('payout_month', $v))
            ->when($request->year, fn ($q, $v) => $q->where('payout_year', $v))
            ->latest()
            ->paginate(20);

        $vendors = Vendor::active()->orderBy('company_name')
            ->where(function ($q) use ($activeCode) {
                $q->where('company_code', $activeCode)
                    ->orWhereHas('user', fn ($uq) => $uq->whereJsonContains('company_codes', $activeCode));
            })
            ->get();

        try {
            $summaryQuery = VendorPayout::where('company_code', $activeCode);

            $summary = [
                'total_payouts' => (float) (clone $summaryQuery)
                    ->whereIn('status', ['calculated', 'approved', 'payment_pending'])
                    ->sum('net_payout'),

                'paid_this_month' => (float) (clone $summaryQuery)
                    ->where('status', 'paid')
                    ->whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year)
                    ->sum('net_payout'),

                'pending_invoices' => (int) (clone $summaryQuery)
                    ->where('status', 'paid')
                    ->whereNull('vendor_invoice_file')
                    ->count(),

                'total_shipped_qty' => (int) (clone $summaryQuery)
                    ->whereIn('status', ['calculated', 'approved', 'payment_pending'])
                    ->sum('total_shipped_qty'),
            ];
        } catch (\Exception $e) {
            \Log::warning('Payout summary failed: ' . $e->getMessage());
            $summary = ['total_payouts' => 0, 'paid_this_month' => 0, 'pending_invoices' => 0, 'total_shipped_qty' => 0];
        }

        return view('finance.payouts.index', compact('payouts', 'vendors', 'summary'));
    }

    // ─── SHOW PAYOUT DETAIL ──────────────────────────────────────

    public function showPayout(VendorPayout $payout)
    {
        $activeCompany = session('active_company');
        if ($activeCompany && $payout->company_code !== $activeCompany) {
            return redirect()->route('finance.payouts')
                ->with('error', 'This payout does not belong to your active company.');
        }

        $payout->load('vendor');
        // Use saved snapshot if available, otherwise recalculate
        $snapshot = $payout->calculation_snapshot;
        $returnOrders = collect();
        $returnsRaw = collect();
        $summary = [];
        if (!empty($snapshot) && !request('recalculate')) {
            // ── Read from saved snapshot ──
            $lineItems = collect($snapshot['line_items'] ?? [])->map(fn ($i) => (object) $i);

            $payoutSummary = $snapshot['summary'] ?? [
                'total_qty' => $lineItems->sum('qty'),
                'total_sales' => $lineItems->sum('sale_amount'),
                'total_commission' => $lineItems->sum('commission'),
                'total_payout' => $lineItems->sum('net_payout'),
                'total_warehouse_charges' => $payout->total_warehouse_charges ?? 0,
                'total_chargebacks' => $payout->total_chargebacks ?? 0,
                'total_returns' => $payout->total_returns ?? 0,
                'net_payout' => $payout->net_payout ?? 0,
            ];

            // Load warehouse charges and chargebacks (live data for display)
            $warehouseCharges = \App\Models\VendorMonthlyCharge::withoutGlobalScopes()
                ->where('vendor_id', $payout->vendor_id)
                ->where('company_code', $payout->company_code)
                ->where('charge_month', $payout->payout_month)
                ->where('charge_year', $payout->payout_year)
                ->where('status', 'approved')
                 ->where(function ($q) {
                     $q->where('charge_status', 'active')
                       ->orWhereNull('charge_status');
                 })
                ->with('warehouse')
                ->get();
            // echo '<pre>';
            //print_r( $warehouseCharges->toArray());exit;
            $chargebacks = Chargeback::withoutGlobalScopes()
                ->where('vendor_id', $payout->vendor_id)
                ->whereHas('order', fn ($q) => $q->withoutGlobalScopes()->where('company_code', $payout->company_code))
                ->where('status', 'confirmed')
                ->whereMonth('confirmed_at', $payout->payout_month)
                ->whereYear('confirmed_at', $payout->payout_year)
                ->with(['order' => fn ($q) => $q->withoutGlobalScopes()])
                ->get();

            $calculatedAt = $snapshot['calculated_at'] ?? null;
        } else {
            // ── Recalculate live ──
            $service = new \App\Services\VendorPayoutService();
            $data = $service->buildPayoutData($payout->vendor_id, $payout->company_code, $payout->payout_month, $payout->payout_year);

            $lineItems = collect($data['line_items'])->map(fn ($i) => (object) $i);
            $payoutSummary = $data['summary'];
            $warehouseCharges = $data['warehouse_charges'];
            $chargebacks = $data['chargebacks'];
            $returnOrders = $data['return_orders'];
            $returnsRaw = $data['returns_raw'];
            $summary = $data['summary'];
            // print_r($returnOrders->toArray());
            // exit;
            $calculatedAt = null;
        }

        $orders = Order::withoutGlobalScopes()
            ->whereHas('items', fn ($q) => $q->where('vendor_id', $payout->vendor_id)->where('shipped_qty', '>', 0))
            ->where('company_code', $payout->company_code)
            ->whereMonth('order_date', $payout->payout_month)
            ->whereYear('order_date', $payout->payout_year)
            ->whereIn('status', ['shipped', 'delivered'])
            ->with('salesChannel')
            ->get();
         return view('finance.payouts.show', compact(
            'payout',
            'orders',
            'lineItems',
            'payoutSummary',
            'warehouseCharges',
            'chargebacks',
            'returnOrders',
            'returnsRaw',
            'summary',
            'calculatedAt'
        ));
    }
    // ─── CALCULATE PAYOUT ────────────────────────────────────────

    public function calculatePayout(Request $request)
    {
        $request->validate([
            'pay_vendor_id' => 'required|exists:vendors,id',
            'pay_month'     => 'required|integer|between:1,12',
            'pay_year'      => 'required|integer|min:2024',
        ]);

        $activeCode = session('active_company');
        $vendor = Vendor::findOrFail($request->pay_vendor_id);
        $service = new \App\Services\VendorPayoutService();

        $result = $service->calculateAndSave(
            $vendor->id,
            $activeCode ?? $vendor->company_code,
            $request->pay_month,
            $request->pay_year
        );

        if ($result['success']) {
            $summary = $result['data']['summary'];
            $currency = config('app.active_currency_symbol', '$');
            return back()->with(
                'success',
                "Payout calculated for {$vendor->company_name}: " .
                    "{$summary['total_qty']} shipped units, " .
                    "Sales: {$currency}" . number_format($summary['total_sales'], 2) .
                    ", Commission: {$currency}" . number_format($summary['total_commission'], 2) .
                    ", WH Charges: {$currency}" . number_format($summary['total_warehouse_charges'], 2) .
                    ", Chargebacks: {$currency}" . number_format($summary['total_chargebacks'], 2) .
                    ", Net Payout: {$currency}" . number_format($summary['net_payout'], 2)
            );
        }

        return back()->with('error', $result['error'] ?? 'Calculation failed.');
    }

    public function processPayment(Request $request, VendorPayout $payout)
    {
        $request->validate([
            'payment_date'      => 'required|date',
            'payment_reference' => 'nullable|string',
        ]);
        $this->financeService->processPayment($payout, $request->all());
        return back()->with('success', 'Payment processed. Vendor notified.');
    }

    public function downloadPaymentAdvice(VendorPayout $payout)
    {
        $payout->load('vendor');

        $csv = "PAYMENT ADVICE\n";
        $csv .= "Vendor,{$payout->vendor->company_name}\n";
        $csv .= "Vendor Code,{$payout->vendor->vendor_code}\n";
        $csv .= "Period," . date('F', mktime(0, 0, 0, $payout->payout_month, 1)) . " {$payout->payout_year}\n";
        $csv .= "Company Code,{$payout->company_code}\n\n";
        $csv .= "Description,Amount\n";
        $csv .= "Gross Sales,{$payout->gross_sales}\n";
        $csv .= "Platform Deductions,-{$payout->platform_deductions}\n";
        $csv .= "Warehouse Charges,-{$payout->warehouse_charges}\n";
        $csv .= "Chargebacks,-{$payout->chargeback_amount}\n";
        $csv .= "Other Deductions,-{$payout->other_deductions}\n";
        $csv .= "NET PAYOUT,{$payout->net_payout}\n\n";
        $csv .= "Payment Date,{$payout->payment_date}\n";
        $csv .= "Payment Reference,{$payout->payment_reference}\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"Payment-Advice-{$payout->vendor->vendor_code}-{$payout->payout_month}-{$payout->payout_year}.csv\"",
        ]);
    }

    public function uploadVendorInvoice(Request $request, VendorPayout $payout)
    {
        $request->validate([
            'invoice'               => 'required|file|mimes:pdf|max:10240',
            'vendor_invoice_number' => 'required|string|max:100',
        ]);
        $path = $request->file('invoice')->store('vendor-invoices/' . $payout->vendor_id, FileStorage::disk());
        $payout->update([
            'vendor_invoice_file'   => $path,
            'vendor_invoice_number' => $request->vendor_invoice_number,
            'vendor_invoice_date'   => now(),
            'status'                => 'invoice_received',
        ]);
        return back()->with('success', 'Vendor invoice uploaded.');
    }

    // ─── PRICING REVIEW ──────────────────────────────────────────
    public function pricingReview(Request $request)
    {
        $activeCode = session('active_company');
        $pricings = \App\Models\PlatformPricing::with('product', 'salesChannel', 'asn')
            ->where('status', 'submitted')
            ->where('company_code', $activeCode)
            ->paginate(30);
        return view('finance.pricing-review', compact('pricings'));
    }

    public function approvePricing(\App\Models\Asn $asn)
    {
        app(\App\Services\PricingService::class)->reviewPricing($asn, auth()->user(), true);
        return back()->with('success', 'Pricing approved.');
    }

    // ─── LIVE SHEETS — SAP CODE UPDATE ───────────────────────────
    // In FinanceController::liveSheets()
    public function liveSheets(Request $request)
    {
        $activeCompany = session('active_company');

        $liveSheets = LiveSheet::with('vendor', 'offerSheet', 'consignment', 'items.product')
            ->where('company_code', $activeCompany)
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($q2) use ($v) {
                $q2->where('live_sheet_number', 'LIKE', "%{$v}%")
                    ->orWhereHas('vendor', fn ($vq) => $vq->where('company_name', 'LIKE', "%{$v}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();


        $vendors = \App\Models\Vendor::whereIn(
            'id',
            \App\Models\LiveSheet::where('company_code', $activeCompany)->distinct()->pluck('vendor_id')
        )
            ->orderBy('company_name')
            ->get();


        return view('finance.live-sheets.index', compact('liveSheets', 'vendors'));
    }
    public function showLiveSheet(\App\Models\LiveSheet $liveSheet)
    {
        if ($liveSheet->company_code !== session('active_company')) {
            return redirect()->route('finance.live-sheets')
                ->with('error', 'This live sheet does not belong to your active company.');
        }
        $liveSheet->load('vendor', 'offerSheet', 'items.product');
        return view('finance.live-sheets.show', compact('liveSheet'));
    }

    public function updateSapCodes(Request $request, \App\Models\LiveSheet $liveSheet)
    {
        $request->validate([
            'sap_codes'              => 'required|array',
            'sap_codes.*.item_id'    => 'required|exists:live_sheet_items,id',
            'sap_codes.*.sap_code'   => 'nullable|string|max:50|regex:/^[A-Za-z0-9\-_]+$/',
            'sap_codes.*.vendor_wsp' => 'nullable|numeric|min:0|decimal:0,2',
        ], [
            'sap_codes.*.sap_code.regex' => 'SAP code can only contain letters, numbers, hyphens and underscores.',
            'sap_codes.*.vendor_wsp.decimal' => 'Vendor WSP must be a valid decimal number with up to 2 decimal places.',
        ]);

        $errors = [];
        $seen = [];

        foreach ($request->sap_codes as $idx => $row) {
            $code = trim($row['sap_code'] ?? '');
            if ($code === '') {
                continue;
            }

            // Duplicate within current submission
            if (isset($seen[$code])) {
                $errors[] = "SAP code '{$code}' is used multiple times in this form.";
                continue;
            }
            $seen[$code] = true;

            $item = \App\Models\LiveSheetItem::find($row['item_id']);
            if (!$item) {
                continue;
            }

            // Check products table — use first() not count()
            $dupProduct = \App\Models\Product::withoutGlobalScopes()
                ->where('sap_code', $code)
                ->where('company_code', $liveSheet->company_code)
                ->where('id', '!=', $item->product_id)
                ->first();

            if ($dupProduct) {
                $errors[] = "SAP code '{$code}' is already assigned to product '{$dupProduct->sku}' ({$dupProduct->name}).";
                continue;
            }

            // Check live_sheet_items JSON — same company only
            $dupItems = \App\Models\LiveSheetItem::whereJsonContains('product_details', ['sap_code' => $code])
                ->where('product_id', '!=', $item->product_id)
                ->whereHas('liveSheet', fn($q) => $q->withoutGlobalScopes()->where('company_code', $liveSheet->company_code))
                ->with(['product' => fn($q) => $q->withoutGlobalScopes()])
                ->get();

            foreach ($dupItems as $dup) {
                $sku = $dup->product->sku ?? 'unknown';
                $errors[] = "SAP code '{$code}' is already used on live sheet item (SKU: {$sku}) in {$liveSheet->company_code}.";
            }
        }

        if (!empty($errors)) {
            return back()
                ->withErrors(['sap_codes' => $errors])
                ->with('error', 'SAP code validation failed: ' . implode(' | ', array_slice($errors, 0, 5)));
        }

        // All codes unique — proceed
        $updated = 0;
        foreach ($request->sap_codes as $row) {
            $item = \App\Models\LiveSheetItem::find($row['item_id']);
            if (!$item || $item->live_sheet_id !== $liveSheet->id) {
                continue;
            }

            $details = $item->product_details ?? [];
            if (isset($row['sap_code'])) {
                $details['sap_code'] = $row['sap_code'];
            }
            if (isset($row['vendor_wsp'])) {
                $details['vendor_wsp'] = $row['vendor_wsp'];
            }
            $item->update(['product_details' => $details]);

            if ($item->product) {
                $productUpdate = [];
                if (!empty($row['sap_code'])) {
                    $productUpdate['sap_code'] = $row['sap_code'];
                }
                if (!empty($row['vendor_wsp'])) {
                    $productUpdate['vendor_wsp'] = $row['vendor_wsp'];
                }
                if (!empty($productUpdate)) {
                    $item->product->update($productUpdate);
                }
            }

            $updated++;
        }

        \App\Models\ActivityLog::log('updated', 'live_sheet', $liveSheet, null, ['sap_codes_updated' => $updated], 'SAP codes and Vendor WSP updated by Finance');

        return back()->with('success', "{$updated} SAP code and Vendor WSP(s) updated successfully.");
    }
     
    /**
     * Download pre-filled SAP template CSV for a live sheet
     */
    public function downloadSapTemplate(\App\Models\LiveSheet $liveSheet)
    {
        $liveSheet->load('items.product');

        $csv = "Item ID,SKU,Product Name,Current SAP Code,New SAP Code,Current Vendor WSP,New Vendor WSP\n";

        foreach ($liveSheet->items as $item) {
            $d = $item->product_details ?? [];
            $currentSap = $item->product->sap_code ?? $d['sap_code'] ?? '';
            $currentPayout = $item->product->vendor_wsp ?? $d['vendor_wsp'] ?? '';

            $csv .= implode(',', [
                $item->id,
                '"' . ($item->product->sku ?? '') . '"',
                '"' . str_replace('"', '""', $item->product->name ?? '') . '"',
                '"' . $currentSap . '"',
                '', // New SAP Code
                $currentPayout,
                '', // New Vendor Payout Price
            ]) . "\n";
        }

        $filename = "SAP-Template-{$liveSheet->live_sheet_number}.csv";
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
    /**
     * Upload filled SAP CSV and apply codes to products
     */

    public function uploadSapCodes(Request $request, \App\Models\LiveSheet $liveSheet)
    {
        $request->validate([
            'sap_file' => 'required|file|max:5120',
        ]);

        $file = $request->file('sap_file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt', 'xlsx'])) {
            return back()->with('error', 'File must be CSV or XLSX format.');
        }

        try {
            $fullPath = $file->getRealPath();

            if ($ext === 'xlsx') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(false);
                $rows = $reader->load($fullPath)->getActiveSheet()->toArray();
            } else {
                $rows = [];
                if (($handle = fopen($fullPath, 'r')) !== false) {
                    while (($row = fgetcsv($handle)) !== false) {
                        $rows[] = $row;
                    }
                    fclose($handle);
                }
            }

            if (count($rows) < 2) {
                return back()->with('error', 'File is empty or has no data rows.');
            }

            $header = array_map(fn ($h) => strtolower(trim($h ?? '')), $rows[0]);
            $itemIdCol = $currSapCol = $newSapCol = $currPayoutCol = $newPayoutCol = null;

            foreach ($header as $i => $h) {
                if (in_array($h, ['item id', 'item_id', 'id'])) {
                    $itemIdCol = $i;
                }
                if (in_array($h, ['current sap code', 'current_sap_code'])) {
                    $currSapCol = $i;
                }
                if (in_array($h, ['new sap code', 'new_sap_code', 'sap code', 'sap_code'])) {
                    $newSapCol = $i;
                }
                if (in_array($h, ['current vendor wsp', 'current_vendor_wsp'])) {
                    $currPayoutCol = $i;
                }
                if (in_array($h, ['new vendor wsp', 'new_vendor_wsp', 'vendor wsp', 'vendor_wsp'])) {
                    $newPayoutCol = $i;
                }
            }

            if ($newSapCol === null && $newPayoutCol === null) {
                return back()->with('error', 'CSV must have a "New SAP Code" or "New Vendor WSP" column.');
            }

            // Add right after header parsing, before the loop:

            $updated = 0;
            $errors = [];
            $sapCodes = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $rowNum = $i + 1;

                $itemId = $itemIdCol !== null ? intval($row[$itemIdCol] ?? 0) : null;
                if (!$itemId) {
                    continue;
                }

                $currentSap = $currSapCol !== null ? trim($row[$currSapCol] ?? '') : '';
                $newSap     = $newSapCol !== null ? trim($row[$newSapCol] ?? '') : '';
                $newWsp     = $newPayoutCol !== null ? trim($row[$newPayoutCol] ?? '') : '';

                // Skip row if nothing to update
                if ($newSap === '' && $newWsp === '') {
                    continue;
                }

                // Find live sheet item
                $item = \App\Models\LiveSheetItem::withoutGlobalScopes()
                    ->where('id', $itemId)
                    ->where('live_sheet_id', $liveSheet->id)
                    ->first();
                if (!$item) {
                    continue;
                }

                // Validate new WSP
                if ($newWsp !== '' && !is_numeric($newWsp)) {
                    $errors[] = "Row {$rowNum}: Invalid Vendor WSP '{$newWsp}'.";
                    continue;
                }

                // SAP: only update if new_sap_code is provided
                // If current_sap_code exists but new is blank → skip SAP update
                if ($newSap !== '') {
                    // Uniqueness within this upload
                    if (in_array($newSap, $sapCodes)) {
                        $errors[] = "Row {$rowNum}: Duplicate SAP code '{$newSap}'.";
                        continue;
                    }
                    $sapCodes[] = $newSap;

                    // Uniqueness against existing products — same company
                    $dup = \App\Models\Product::withoutGlobalScopes()
                        ->where('sap_code', $newSap)
                        ->where('company_code', $liveSheet->company_code)
                        ->where('id', '!=', $item->product_id)
                        ->first();
                    if ($dup) {
                        $errors[] = "Row {$rowNum}: SAP '{$newSap}' already used by SKU '{$dup->sku}' in {$liveSheet->company_code}.";
                        continue;
                    }

                    // Check live_sheet_items JSON — same company
                    $dupLsItem = \App\Models\LiveSheetItem::whereJsonContains('product_details', ['sap_code' => $newSap])
                        ->where('product_id', '!=', $item->product_id)
                        ->whereHas('liveSheet', fn($q) => $q->withoutGlobalScopes()->where('company_code', $liveSheet->company_code))
                        ->with(['product' => fn($q) => $q->withoutGlobalScopes()])
                        ->first();
                    if ($dupLsItem) {
                        $sku = $dupLsItem->product->sku ?? 'unknown';
                        $errors[] = "Row {$rowNum}: SAP '{$newSap}' already used on live sheet item (SKU: {$sku}) in {$liveSheet->company_code}.";
                        continue;
                    }
                }

                // Build updates
                $productUpdate = [];
                $detailsUpdate = $item->product_details ?? [];

                if ($newSap !== '') {
                    $productUpdate['sap_code'] = $newSap;
                    $detailsUpdate['sap_code'] = $newSap;
                }

                if ($newWsp !== '') {
                    $productUpdate['vendor_wsp'] = floatval($newWsp);
                    $detailsUpdate['vendor_wsp'] = floatval($newWsp);
                }

                // Update product table
                if (!empty($productUpdate) && $item->product_id) {
                    \App\Models\Product::withoutGlobalScopes()->where('id', $item->product_id)->update($productUpdate);
                }

                // Update live sheet item product_details
                $item->update(['product_details' => $detailsUpdate]);
                $updated++;
            }

            \App\Models\ActivityLog::log('uploaded-live-sheet-' . $liveSheet->id, 'sap_codes', $liveSheet, null, [
                'updated' => $updated,
                'errors' => count($errors),
            ], "SAP/WSP uploaded: {$updated} updated");

            $msg = "{$updated} item(s) updated.";
            if (!empty($errors)) {
                $msg .= " " . count($errors) . " error(s): " . implode('; ', array_slice($errors, 0, 5));
            }

            return back()->with($updated > 0 ? 'success' : 'error', $msg);
        } catch (\Exception $e) {
            \Log::error('SAP/WSP upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }
    // ═══ VENDOR RATE CARDS ═══

    public function vendorRateCards(Request $request)
    {
        $activeCode = session('active_company');

        $rateCards = \App\Models\VendorRateCard::with('vendor', 'creator', 'approver')
            ->where('company_code', $activeCode)
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        // Filter vendors based on user's allowed companies
        // $vendors = \App\Models\Vendor::orderBy('company_name')
        //     ->where('company_code', $activeCode)
        //     ->get();

        $vendors = \App\Models\Vendor::whereHas('user', fn ($q) => $q->whereJsonContains('company_codes', $activeCode))
            ->orderBy('company_name')
            ->get();

        return view('finance.vendor-rate-cards', compact('rateCards', 'vendors'));
    }

    public function storeVendorRateCard(Request $request)
    {

        $activeCode = session('active_company');

        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'inward_rate_per_carton' => 'required|numeric|min:0|max:500',
            'storage_rate_per_cft' => 'required|numeric|min:0|max:100',
            'fulfillment_rate_small' => 'required|numeric|min:0|max:50',
            'fulfillment_rate_large' => 'required|numeric|min:0|max:50',
            'fulfillment_qty_threshold' => 'required|integer|min:1|max:100',
            'pick_pack_rate_per_unit' => 'required|numeric|min:0|max:50',
            'effective_from' => 'required|date',
        ]);
        $vendor = \App\Models\Vendor::findOrFail($request->vendor_id);
        $currency = match ($activeCode ?? $vendor->company_code) {
            '2000' => 'INR',
            '2200' => 'EUR',
            default => 'USD',
        };
        $maxV = \App\Models\VendorRateCard::where(['vendor_id' => $vendor->id, 'company_code' => $activeCode])->max('version') ?? 0;

        $newEffectiveFrom = $request->effective_from;

        \App\Models\VendorRateCard::where('vendor_id', $vendor->id)
            ->where('status', 'approved')
            ->whereNull('effective_to')
            ->update([
                'effective_to' => \Carbon\Carbon::parse($newEffectiveFrom)->subDay()->toDateString(),
            ]);

        $rc = \App\Models\VendorRateCard::create(array_merge($request->only([
            'vendor_id',
            'inward_rate_per_carton',
            'storage_rate_per_cft',
            'fulfillment_rate_small',
            'fulfillment_rate_large',
            'fulfillment_qty_threshold',
            'pick_pack_rate_per_unit',
            'effective_from',
        ]), ['company_code' => $activeCode ?: $vendor->company_code, 'currency' => $currency, 'version' => $maxV + 1, 'status' => 'draft', 'created_by' => auth()->id()]));

        \App\Models\ActivityLog::log('created', 'vendor_rate_card', $rc, null, $rc->toArray(), "Rate card v{$rc->version} for {$vendor->company_name}");
        return back()->with('success', "Rate card v{$rc->version} created for {$vendor->company_name}.");
    }

    public function submitVendorRateCard(\App\Models\VendorRateCard $vendorRateCard)
    {
        if (!$vendorRateCard->isComplete()) {
            return back()->with('error', 'All rate fields must be filled.');
        }
        $vendorRateCard->update(['status' => 'pending_approval']);
        return back()->with('success', 'Submitted for approval.');
    }

    public function approveVendorRateCard(\App\Models\VendorRateCard $vendorRateCard)
    {
        $vendorRateCard->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        \App\Models\ActivityLog::log('approved', 'vendor_rate_card', $vendorRateCard);
        return back()->with('success', "Rate card approved.");
    }

    // ═══ VENDOR MONTHLY CHARGES ═══

    public function vendorCharges(Request $request)
    {

        $activeCode = session('active_company');

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $charges = \App\Models\VendorMonthlyCharge::with('vendor', 'grn', 'warehouse')
            ->where('company_code', $activeCode)
            ->byMonth($month, $year)->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->orderBy('vendor_id')->paginate(50)->withQueryString();


        $vendors = \App\Models\Vendor::whereHas('user', fn ($q) => $q->whereJsonContains('company_codes', $activeCode))
                    ->orderBy('company_name')
                    ->get();

        $baseQ = \App\Models\VendorMonthlyCharge::where('company_code', $activeCode)
            ->byMonth($month, $year);

        $stats = [
            'total_charges' => (float)(clone $baseQ)->where('status', 'approved')->sum('total_charges'),
            'total_inward' => (float)(clone $baseQ)->where('status', 'approved')->sum('inward_charge'),
            'total_storage' => (float)(clone $baseQ)->where('status', 'approved')->sum('storage_charge'),
            'total_fulfill' => (float)(clone $baseQ)->where('status', 'approved')->sum('fulfillment_charge'),
            'total_pickpack' => (float)(clone $baseQ)->where('status', 'approved')->sum('pick_pack_charge'),
            'total_material' => (float)(clone $baseQ)->where('status', 'approved')->sum('material_cost'),
            'vendor_count' => (int)(clone $baseQ)->distinct('vendor_id')->count('vendor_id'),
            'pending_count' => (int)(clone $baseQ)->where('status', 'calculated')->count(),
        ];
        return view('finance.vendor-charges.index', compact('charges', 'vendors', 'stats', 'month', 'year'));
    }

    public function runVendorCharges(Request $request)
    {

        $companyCode = session('active_company');

        $request->validate([
            'charge_month' => 'required|integer|min:1|max:12',
            'charge_year' => 'required|integer|min:2024',
            'charge_vendor_id' => 'nullable|exists:vendors,id'
        ]);
        $service = new \App\Services\VendorChargesService();
        // echo $request->charge_month. ", " . $request->charge_year . ", " . $request->charge_vendor_id . ", " . $request->dry_run;
        //exit;
        $results = $service->runMonthlyCharges($request->charge_month, $request->charge_year, $request->charge_vendor_id, auth()->id(), (bool)$request->dry_run);

        $msg = ($request->dry_run ? "[DRY RUN] " : "") . "{$results['created']} created, {$results['skipped']} skipped.";
        if (!empty($results['errors'])) {
            $msg .= " Errors: " . implode('; ', array_slice($results['errors'], 0, 5));
        }
        return back()->with($results['created'] > 0 ? 'success' : 'error', $msg);
    }

    public function approveVendorCharge(\App\Models\VendorMonthlyCharge $vendorMonthlyCharge)
    {
        $vendorMonthlyCharge->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'is_locked' => true]);
        return back()->with('success', 'Charge approved.');
    }

    public function vendorStatement(Request $request, \App\Models\Vendor $vendor)
    {
        $activeCompany = session('active_company');
        if ($activeCompany) {
            $vendorUser = $vendor->user;
            $vendorCompanyCodes = $vendorUser ? ($vendorUser->company_codes ?? []) : [];
            if (is_string($vendorCompanyCodes)) {
                $vendorCompanyCodes = json_decode($vendorCompanyCodes, true) ?? [];
            }

            // Also check vendor's own company_code
            $vendorCompanyCodes[] = $vendor->company_code;
            $vendorCompanyCodes = array_unique(array_filter($vendorCompanyCodes));

            if (!empty($vendorCompanyCodes) && !in_array($activeCompany, $vendorCompanyCodes)) {

                $companyNames = ['2100' => '🇺🇸 ExpoBazaar USA', '2200' => '🇪🇺 ExpoBazaar EU', '2400' => '🇬🇧 ExpoBazaar UK'];
                $vendorCompanyLabels = array_map(fn ($c) => ($companyNames[$c] ?? $c) . " ($c)", $vendorCompanyCodes);

                // return redirect()->route('finance.vendor-charges')
                //                  ->with('error', 'This vendor does not belong to your active company.Please switch company from the profile dropdown.');

                return redirect()->route('finance.vendor-charges')->with(
                    'error',
                    "This vendor operates under: " . implode(', ', $vendorCompanyLabels) . ". " .
                        "Please switch company from the profile dropdown."
                );
            }
        }
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $service = new \App\Services\VendorChargesService();
        $statement = $service->getVendorStatement($vendor->id, $month, $year, $activeCompany);
        return view('finance.vendor-charges.statement', compact('statement', 'month', 'year'));
    }

    public function downloadVendorCharges(Request $request)
    {
        $activeCode = session('active_company');
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $charges = \App\Models\VendorMonthlyCharge::with('vendor', 'grn')
            ->where('company_code', $activeCode)
            ->byMonth($month, $year)->get();
        $csv = "Vendor,GRN,Inward,Storage,Fulfillment,Pick&Pack,Material,Total,Currency,Status\n";
        foreach ($charges as $c) {
            $s = $c->getCurrencySymbol();
            $csv .= '"' . ($c->vendor->company_name ?? '') . '",'
                . ($c->grn->grn_number ?? '') . ',' . "{$s}" . number_format(floatval($c->inward_charge), 2)
                . ",{$s}" . number_format(floatval($c->storage_charge), 2) . ",{$s}" . number_format(floatval($c->fulfillment_charge), 2)
                . ",{$s}" . number_format(floatval($c->pick_pack_charge), 2) . ",{$s}" . number_format(floatval($c->material_cost), 2)
                . ",{$s}" . number_format(floatval($c->total_charges), 2) . ",{$c->currency},{$c->status}\n";
        }
        $mn = date('M', mktime(0, 0, 0, $month, 1));
        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"vendor-charges-{$mn}-{$year}.csv\""]);
    }
    public function updateCommission(Request $request, LiveSheet $liveSheet)
    {
        $request->validate([
            'commission_percentage' => 'required|numeric|min:0|max:100'
        ]);

        $liveSheet->update([
            'commission_percentage' => $request->commission_percentage,
            'cp_updated_by' => auth()->id(),
            'cp_updated_at' => now(),
        ]);
        file_put_contents(storage_path('logs/commission_updates.log'), "LiveSheet ID: {$liveSheet->id}, New Commission: {$request->commission_percentage}%, Updated By: " . auth()->user()->name . " at " . now() . "\n", FILE_APPEND);
        return response()->json([
            'success' => true,
            'message' => 'Commission percentage updated successfully.'
        ]);
    }


    public function storeCommissionRevision(Request $request, \App\Models\LiveSheet $liveSheet)
    {
        $request->validate([
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'effective_from'        => 'required|date',
            'effective_to'          => 'nullable|date|after_or_equal:effective_from',
            'remarks'               => 'nullable|string|max:500',
        ]);

        // Close previous open revision
        $previousOpen = \App\Models\CommissionRevision::where('live_sheet_id', $liveSheet->id)
            ->whereNull('effective_to')
            ->latest('effective_from')
            ->first();

        if ($previousOpen) {
            $previousOpen->update([
                'effective_to' => \Carbon\Carbon::parse($request->effective_from)->subDay()->toDateString(),
            ]);
        }

        $revision = \App\Models\CommissionRevision::create([
            'live_sheet_id'         => $liveSheet->id,
            'vendor_id'             => $liveSheet->vendor_id,
            'company_code'          => $liveSheet->company_code,
            'commission_percentage' => $request->commission_percentage,
            'effective_from'        => $request->effective_from,
            'effective_to'          => $request->effective_to,
            'remarks'               => $request->remarks,
            'created_by'            => auth()->id(),
        ]);

        // Update live sheet's current commission to latest active
        $activeRate = \App\Models\CommissionRevision::getActiveRate($liveSheet->id);
        if ($activeRate !== null) {
            $liveSheet->update(['commission_percentage' => $activeRate]);
        }

        \App\Models\ActivityLog::log('created', 'commission_revision', $revision, null, [
            'live_sheet' => $liveSheet->live_sheet_number,
            'old_rate'   => $previousOpen?->commission_percentage,
            'new_rate'   => $request->commission_percentage,
            'from'       => $request->effective_from,
            'to'         => $request->effective_to,
        ], "Commission revised: {$request->commission_percentage}% from {$request->effective_from} on {$liveSheet->live_sheet_number}");

        return back()->with('success', "Commission {$request->commission_percentage}% added effective from " . \Carbon\Carbon::parse($request->effective_from)->format('d M Y'));
    }

    public function deleteCommissionRevision(\App\Models\CommissionRevision $revision)
    {
        $liveSheet = $revision->liveSheet;
        $revision->delete();

        // Recalculate current commission
        $activeRate = \App\Models\CommissionRevision::getActiveRate($liveSheet->id);
        if ($activeRate !== null) {
            $liveSheet->update(['commission_percentage' => $activeRate]);
        }

        return back()->with('success', 'Commission revision deleted.');
    }


    /**
     *
     * Routes (inside finance group):
     *   Route::post('live-sheets/{liveSheet}/wsp', [FinanceController::class, 'storeWspRevision'])->name('live-sheets.wsp.store');
     *   Route::delete('wsp-revision/{revision}', [FinanceController::class, 'deleteWspRevision'])->name('wsp-revision.delete');
     */
    public function storeWspRevision(Request $request, \App\Models\LiveSheet $liveSheet)
    {
        $request->validate([
            'vendor_wsp'     => $request->boolean('bulk') ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to'   => 'nullable|date|after_or_equal:effective_from',
            'product_id'     => 'nullable|exists:products,id',
            'remarks'        => 'nullable|string|max:500',
        ]);

        $isBulk = $request->boolean('bulk');

        if ($isBulk) {
            // ── BULK: Apply to all items ──
            // Capture old WSP values before closing
            $oldRevisions = \App\Models\WspRevision::where('live_sheet_id', $liveSheet->id)
                ->whereNull('effective_to')
                ->get()
                ->keyBy('product_id');

            // Close ALL open revisions
            \App\Models\WspRevision::where('live_sheet_id', $liveSheet->id)
                ->whereNull('effective_to')
                ->where('effective_from', '<', $request->effective_from)
                ->update(['effective_to' => \Carbon\Carbon::parse($request->effective_from)->subDay()->toDateString()]);

            // Create one revision per product

            $changes = [];
            foreach ($liveSheet->items as $item) {
                $existingWsp = $oldRevisions[$item->product_id]->vendor_wsp
                    ?? floatval($item->product_details['vendor_wsp'] ?? $item->product_details['wsp'] ?? $request->vendor_wsp ?? 0);

                \App\Models\WspRevision::create([
                    'live_sheet_id'      => $liveSheet->id,
                    'live_sheet_item_id' => $item->id,
                    'product_id'         => $item->product_id,
                    'vendor_id'          => $liveSheet->vendor_id,
                    'company_code'       => $liveSheet->company_code,
                    'vendor_wsp'         => $existingWsp,  // keep existing WSP
                    'effective_from'     => $request->effective_from,
                    'effective_to'       => $request->effective_to,
                    'remarks'            => $request->remarks ?? 'Bulk date update',
                    'created_by'         => auth()->id(),
                ]);

                $changes[] = [
                    'product_id' => $item->product_id,
                    'sku'        => $item->product->sku ?? '—',
                    'wsp'        => floatval($existingWsp),
                ];
            }

            $msg = "Dates updated for all {$liveSheet->items->count()} items: {$request->effective_from}" .
                    ($request->effective_to ? " to {$request->effective_to}" : ' (open-ended)');

            \App\Models\ActivityLog::log('created', 'wsp_revision', $liveSheet, null, [
                 'bulk'        => true,
                 'live_sheet'  => $liveSheet->live_sheet_number,
                 'from'        => $request->effective_from,
                 'to'          => $request->effective_to,
                 'items_count' => count($changes),
                 'changes'     => array_slice($changes, 0, 20),
             ], "Bulk dates: {$request->effective_from} → " . ($request->effective_to ?? 'open') . " for {$liveSheet->items->count()} items on {$liveSheet->live_sheet_number}");

        } else {
            // ── SINGLE: Apply to one product or sheet-level ──

            // Get previous open revision
            $previousQuery = \App\Models\WspRevision::where('live_sheet_id', $liveSheet->id)
                ->whereNull('effective_to')
                ->latest('effective_from');

            if ($request->product_id) {
                $previousQuery->where('product_id', $request->product_id);
            } else {
                $previousQuery->whereNull('product_id');
            }

            $previousOpen = $previousQuery->first();
            $oldWsp = $previousOpen->vendor_wsp ?? null;

            // Close previous
            if ($previousOpen && $previousOpen->effective_from < $request->effective_from) {
                $previousOpen->update([
                    'effective_to' => \Carbon\Carbon::parse($request->effective_from)->subDay()->toDateString(),
                ]);
            }

            // If no previous revision, capture from product_details
            if ($oldWsp === null && $request->product_id) {
                $item = \App\Models\LiveSheetItem::where('live_sheet_id', $liveSheet->id)
                    ->where('product_id', $request->product_id)->first();
                $oldWsp = $item ? floatval($item->product_details['vendor_wsp'] ?? $item->product_details['wsp'] ?? 0) : null;
            }

            $revision = \App\Models\WspRevision::create([
                'live_sheet_id'      => $liveSheet->id,
                'live_sheet_item_id' => $request->product_id
                    ? \App\Models\LiveSheetItem::where('live_sheet_id', $liveSheet->id)->where('product_id', $request->product_id)->value('id')
                    : null,
                'product_id'         => $request->product_id,
                'vendor_id'          => $liveSheet->vendor_id,
                'company_code'       => $liveSheet->company_code,
                'vendor_wsp'         => $request->vendor_wsp,
                'effective_from'     => $request->effective_from,
                'effective_to'       => $request->effective_to,
                'remarks'            => $request->remarks,
                'created_by'         => auth()->id(),
            ]);

            // Update product_details
            if ($request->product_id) {
                $item = \App\Models\LiveSheetItem::where('live_sheet_id', $liveSheet->id)
                    ->where('product_id', $request->product_id)->first();
                if ($item) {
                    $details = $item->product_details ?? [];
                    $details['vendor_wsp'] = floatval($request->vendor_wsp);
                    $details['wsp'] = floatval($request->vendor_wsp);
                    $item->update(['product_details' => $details]);
                }
                \App\Models\Product::withoutGlobalScopes()->where('id', $request->product_id)
                    ->update(['vendor_wsp' => $request->vendor_wsp]);
            } else {
                foreach ($liveSheet->items as $item) {
                    $details = $item->product_details ?? [];
                    $details['vendor_wsp'] = floatval($request->vendor_wsp);
                    $details['wsp'] = floatval($request->vendor_wsp);
                    $item->update(['product_details' => $details]);
                }
            }

            $sku = $request->product_id ? (\App\Models\Product::find($request->product_id)?->sku ?? '—') : 'All items';
            $msg = "WSP {$request->vendor_wsp} applied to {$sku}";

            \App\Models\ActivityLog::log('created', 'wsp_revision', $revision, null, [
                'bulk'       => false,
                'live_sheet' => $liveSheet->live_sheet_number,
                'product_id' => $request->product_id,
                'sku'        => $sku,
                'old_wsp'    => floatval($oldWsp ?? 0),
                'new_wsp'    => floatval($request->vendor_wsp),
                'from'       => $request->effective_from,
                'to'         => $request->effective_to,
            ], "WSP: {$oldWsp} → {$request->vendor_wsp} for {$sku} on {$liveSheet->live_sheet_number}");
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return back()->with('success', $msg);
    }
    public function deleteWspRevision(\App\Models\WspRevision $revision)
    {
        $liveSheet = $revision->liveSheet;
        $revision->delete();

        return back()->with('success', 'WSP revision deleted.');
    }

    public function downloadPayout(\App\Models\VendorPayout $payout)
    {
        $snapshot = $payout->calculation_snapshot ?? [];
        $lineItems = collect($snapshot['line_items'] ?? []);
        $summary = $snapshot['summary'] ?? [];
        $vendor = $payout->vendor;
        $period = date('M-Y', mktime(0, 0, 0, $payout->payout_month, 1, $payout->payout_year));

        $returnOrders = collect();
        $returnsRaw = collect();

        if (!empty($snapshot) && !request('recalculate')) {
            // ── Read from saved snapshot ──
            $lineItems = collect($snapshot['line_items'] ?? [])->map(fn ($i) => (object) $i);

            $payoutSummary = $snapshot['summary'] ?? [
                'total_qty' => $lineItems->sum('qty'),
                'total_sales' => $lineItems->sum('sale_amount'),
                'total_commission' => $lineItems->sum('commission'),
                'total_payout' => $lineItems->sum('net_payout'),
                'total_warehouse_charges' => $payout->total_warehouse_charges ?? 0,
                'total_chargebacks' => $payout->total_chargebacks ?? 0,
                'total_returns' => $payout->total_returns ?? 0,
                'net_payout' => $payout->net_payout ?? 0,
            ];

            // Load warehouse charges and chargebacks (live data for display)
            $warehouseCharges = \App\Models\VendorMonthlyCharge::withoutGlobalScopes()
                ->where('vendor_id', $payout->vendor_id)
                ->where('company_code', $payout->company_code)
                ->where('charge_month', $payout->payout_month)
                ->where('charge_year', $payout->payout_year)
                ->where('status', 'approved')
                 ->where(function ($q) {
                     $q->where('charge_status', 'active')
                       ->orWhereNull('charge_status');
                 })
                ->with('warehouse')
                ->get();
            // echo '<pre>';
            //print_r( $warehouseCharges->toArray());exit;
            $chargebacks = Chargeback::withoutGlobalScopes()
                ->where('vendor_id', $payout->vendor_id)
                ->whereHas('order', fn ($q) => $q->withoutGlobalScopes()->where('company_code', $payout->company_code))
                ->where('status', 'confirmed')
                ->whereMonth('confirmed_at', $payout->payout_month)
                ->whereYear('confirmed_at', $payout->payout_year)
                ->with(['order' => fn ($q) => $q->withoutGlobalScopes()])
                ->get();

            $calculatedAt = $snapshot['calculated_at'] ?? null;
        } else {
            // ── Recalculate live ──
            $service = new \App\Services\VendorPayoutService();
            $data = $service->buildPayoutData($payout->vendor_id, $payout->company_code, $payout->payout_month, $payout->payout_year);

            $lineItems = collect($data['line_items'])->map(fn ($i) => (object) $i);
            $payoutSummary = $data['summary'];
            $warehouseCharges = $data['warehouse_charges'];
            $chargebacks = $data['chargebacks'];            
            $returnOrders = $data['return_orders'];
            $returnsRaw = $data['returns_raw']; 
            $calculatedAt = null;
        }

        // Payment data
        $payments = \App\Models\PayoutPayment::where('vendor_payout_id', $payout->id)
            ->orderBy('payment_date')
            ->with('creator')
            ->get();
        $totalPaid = $payments->sum('amount');
        $balanceDue = max(0, round(floatval($payout->net_payout) - $totalPaid, 2));

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // ═══════════════════════════════════════════
        // SHEET 1: Payout Summary + Line Items
        // ═══════════════════════════════════════════
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Payout Details');

        $boldFont = ['font' => ['bold' => true, 'name' => 'Arial', 'size' => 10]];
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial', 'size' => 9],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        ];
        $greenBg = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']]];
        $yellowBg = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEFCE8']]];
        $redFont = ['font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']]];
        $greenFont = ['font' => ['bold' => true, 'color' => ['rgb' => '166534']]];

        // ── Title ──
        $sheet->setCellValue('A1', 'VENDOR PAYOUT STATEMENT');
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A5F'));

        // ── Vendor Info ──
        $sheet->setCellValue('A3', 'Vendor:');
        $sheet->setCellValue('B3', $vendor->company_name ?? '—');
        $sheet->setCellValue('A4', 'Vendor Code:');
        $sheet->setCellValue('B4', $vendor->vendor_code ?? '—');
        $sheet->setCellValue('A5', 'Period:');
        $sheet->setCellValue('B5', $period);
        $sheet->setCellValue('A6', 'Company:');
        $sheet->setCellValue('B6', $payout->company_code);
        $sheet->setCellValue('A7', 'Status:');
        $sheet->setCellValue('B7', ucfirst(str_replace('_', ' ', $payout->status)));
        $sheet->setCellValue('A8', 'Calculated:');
        $sheet->setCellValue('B8', $payout->updated_at?->format('d M Y H:i') ?? '—');
        $sheet->getStyle('A3:A8')->applyFromArray($boldFont);
        $sheet->getStyle('B7')->applyFromArray($payout->status === 'paid' ? $greenFont : ($payout->status === 'partially_paid' ? ['font' => ['bold' => true, 'color' => ['rgb' => 'E8A838']]] : []));

        $whCharges = $warehouseCharges->sum(fn ($c) => floatval($c->total_charges ?? $c->amount ?? 0));

        $totalPayout = $payoutSummary['total_payout'] ?? 0;

        $totalChargebacks = $chargebacks->sum('amount');
        $netPayout = round($totalPayout - $whCharges - $totalChargebacks, 2);

        $finalPayout = $payoutSummary['total_payout'] - $whCharges - $totalChargebacks;

        // ── Summary Box ──
        $totalSales = $summary['total_sales'] ?? $payout->total_sales;
        $commission = $summary['total_commission'] ?? $payout->total_commission;
        $totalReturns = $summary['total_returns'] ?? $payout->total_returns;
        $grossPayout = $totalSales - $commission;//$summary['total_payout'] ?? $payout->gross_payout;
        // $whCharges = $summary['total_warehouse_charges'] ?? $payout->total_warehouse_charges;
        //$chargebacks = $summary['total_chargebacks'] ?? $payout->total_chargebacks;
        $netPayout = $finalPayout - $totalReturns;
        $sheet->setCellValue('D3', 'Total Sales:');
        $sheet->setCellValue('E3', $totalSales);
        $sheet->setCellValue('D4', 'Commission:');
        $sheet->setCellValue('E4', $commission);
        $sheet->setCellValue('D5', 'Gross Payout:');
        $sheet->setCellValue('E5', $grossPayout);
        $sheet->setCellValue('D6', 'WH Charges:');
        $sheet->setCellValue('E6', $whCharges);
        $sheet->setCellValue('D7', 'Return Orders:');
        $sheet->setCellValue('E7', $totalReturns);

        $sheet->setCellValue('D8', 'Chargebacks:');
        $sheet->setCellValue('E8', $totalChargebacks);

        $sheet->setCellValue('D9', 'Net Payout:');
        $sheet->setCellValue('E9', $netPayout);
        $sheet->getStyle('D3:D9')->applyFromArray($boldFont);
        $sheet->getStyle('E3:E9')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('E9')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('D9:E9')->applyFromArray($greenBg);

        // ── Payment Summary Box ──
        $sheet->setCellValue('G3', 'Total Paid:');
        $sheet->setCellValue('H3', $totalPaid);
        $sheet->setCellValue('G4', 'Balance Due:');
        $sheet->setCellValue('H4', $balanceDue);
        $sheet->setCellValue('G5', 'Payment Status:');
        $sheet->setCellValue('H5', $balanceDue <= 0.01 && $totalPaid > 0 ? 'Fully Paid' : ($totalPaid > 0 ? 'Partially Paid' : 'Unpaid'));
        $sheet->setCellValue('G6', 'Tranches:');
        $sheet->setCellValue('H6', $payments->count());
        $sheet->getStyle('G3:G6')->applyFromArray($boldFont);
        $sheet->getStyle('H3:H4')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('H3')->applyFromArray($totalPaid > 0 ? $greenFont : []);
        $sheet->getStyle('H4')->applyFromArray($balanceDue > 0 ? $redFont : $greenFont);
        $sheet->getStyle('G3:H6')->applyFromArray($yellowBg);

        // ── Line Items Table ──
        $row = 10;
        $sheet->setCellValue("A{$row}", 'ORDER-WISE BREAKDOWN');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E3A5F'));
        $row++;

        $itemHeaders = ['#', 'Order #', 'Order Date', 'Channel', 'SKU', 'Product', 'Shipped Qty', 'Vendor WSP', 'Sale Amount', 'Commission', 'Net Payout', 'FIFO Detail'];
        foreach ($itemHeaders as $col => $h) {
            $sheet->setCellValue([$col + 1, $row], $h);
        }
        $sheet->getStyle([1, $row, count($itemHeaders), $row])->applyFromArray($headerStyle);
        $row++;

        foreach ($lineItems as $idx => $item) {
            $item = (object) $item;
            $sheet->setCellValue([1, $row], $idx + 1);
            $sheet->setCellValue([2, $row], $item->order_number ?? '');
            $sheet->setCellValue([3, $row], $item->order_date ?? '');
            $sheet->setCellValue([4, $row], $item->channel ?? '');
            $sheet->setCellValue([5, $row], $item->sku ?? '');
            $sheet->setCellValue([6, $row], $item->product_name ?? '');
            $sheet->setCellValue([7, $row], $item->qty ?? 0);
            $sheet->setCellValue([8, $row], $item->vendor_wsp ?? 0);
            $sheet->setCellValue([9, $row], $item->sale_amount ?? 0);
            $sheet->setCellValue([10, $row], $item->commission ?? 0);
            $sheet->setCellValue([11, $row], $item->net_payout ?? 0);
            $sheet->setCellValue([12, $row], $item->fifo_detail ?? '');
            $row++;
        }

        // Totals row
        $sheet->setCellValue([5, $row], 'TOTAL');
        $sheet->setCellValue([7, $row], $lineItems->sum('qty'));
        $sheet->setCellValue([9, $row], $lineItems->sum('sale_amount'));
        $sheet->setCellValue([10, $row], $lineItems->sum('commission'));
        $sheet->setCellValue([11, $row], $lineItems->sum('net_payout'));
        $sheet->getStyle([1, $row, 12, $row])->applyFromArray($boldFont);
        $sheet->getStyle([1, $row, 12, $row])->applyFromArray($greenBg);

        // Format number columns
        for ($r = 12; $r <= $row; $r++) {
            $sheet->getStyle([8, $r, 11, $r])->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // ── Deductions Section ──
        $row += 2;
        $sheet->setCellValue("A{$row}", 'DEDUCTIONS');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DC2626'));
        $row++;

        $sheet->setCellValue("A{$row}", 'Type');
        $sheet->setCellValue("B{$row}", 'Details');
        $sheet->setCellValue("C{$row}", 'Amount');
        $sheet->getStyle([1, $row, 3, $row])->applyFromArray($headerStyle);
        $row++;

        // Warehouse charges
        foreach ($snapshot['warehouse_charges_raw'] ?? [] as $wc) {
            $sheet->setCellValue([1, $row], 'Warehouse Charge');
            $sheet->setCellValue([2, $row], $wc['warehouse'] ?? '—');
            $sheet->setCellValue([3, $row], floatval($wc['amount'] ?? 0));
            $sheet->getStyle([3, $row])->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }

        // Chargebacks
        foreach ($snapshot['chargebacks_raw'] ?? [] as $cb) {
            $sheet->setCellValue([1, $row], 'Chargeback');
            $sheet->setCellValue([2, $row], ($cb['order'] ?? '—') . ' — ' . ($cb['reason'] ?? ''));
            $sheet->setCellValue([3, $row], floatval($cb['amount'] ?? 0));
            $sheet->getStyle([3, $row])->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }

        // Charge discount
        if (!empty($snapshot['summary']['charge_discount'])) {
            $sheet->setCellValue([1, $row], 'Charge Discount');
            $sheet->setCellValue([2, $row], 'Approved discount');
            $sheet->setCellValue([3, $row], -floatval($snapshot['summary']['charge_discount']));
            $sheet->getStyle([3, $row])->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle([1, $row, 3, $row])->applyFromArray($greenFont);
            $row++;
        }

        // Deduction totals
        $sheet->setCellValue([1, $row], 'TOTAL DEDUCTIONS');
        $totalDed = floatval($summary['total_warehouse_charges'] ?? $payout->total_warehouse_charges ?? 0) + floatval($summary['total_chargebacks'] ?? $payout->total_chargebacks ?? 0);
        $sheet->setCellValue([3, $row], $totalDed);
        $sheet->getStyle([1, $row, 3, $row])->applyFromArray($boldFont);
        $sheet->getStyle([3, $row])->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle([1, $row, 3, $row])->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF2F2']]]);

        // ── Net Payout ──
        $row += 2;
        $sheet->setCellValue([1, $row], 'NET PAYOUT');
        $sheet->setCellValue([3, $row], $payout->net_payout);
        $sheet->getStyle([1, $row, 3, $row])->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '166534']]]);
        $sheet->getStyle([3, $row])->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle([1, $row, 3, $row])->applyFromArray($greenBg);

        // ── Payment History Section ──
        if ($payments->isNotEmpty()) {
            $row += 2;
            $sheet->setCellValue("A{$row}", 'PAYMENT HISTORY');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E40AF'));
            $row++;

            $payHeaders = ['#', 'Payment Date', 'Amount', 'Mode', 'Reference #', 'Remarks', 'Recorded By', 'Running Total'];
            foreach ($payHeaders as $col => $h) {
                $sheet->setCellValue([$col + 1, $row], $h);
            }
            $sheet->getStyle([1, $row, count($payHeaders), $row])->applyFromArray($headerStyle);
            $row++;

            $runningTotal = 0;
            foreach ($payments as $idx => $payment) {
                $runningTotal += floatval($payment->amount);
                $modeLabels = ['bank_transfer' => 'Bank Transfer', 'wire' => 'Wire', 'cheque' => 'Cheque', 'upi' => 'UPI', 'cash' => 'Cash'];

                $sheet->setCellValue([1, $row], $idx + 1);
                $sheet->setCellValue([2, $row], $payment->payment_date->format('d M Y'));
                $sheet->setCellValue([3, $row], floatval($payment->amount));
                $sheet->setCellValue([4, $row], $modeLabels[$payment->payment_mode] ?? $payment->payment_mode ?? '—');
                $sheet->setCellValue([5, $row], $payment->reference_number ?? '—');
                $sheet->setCellValue([6, $row], $payment->remarks ?? '—');
                $sheet->setCellValue([7, $row], $payment->creator->name ?? '—');
                $sheet->setCellValue([8, $row], $runningTotal);

                $sheet->getStyle([3, $row])->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle([8, $row])->getNumberFormat()->setFormatCode('#,##0.00');
                $row++;
            }

            // Payment summary row
            $sheet->setCellValue([2, $row], 'TOTAL PAID');
            $sheet->setCellValue([3, $row], $totalPaid);
            $sheet->setCellValue([5, $row], 'BALANCE DUE');
            $sheet->setCellValue([6, $row], $balanceDue);
            $sheet->getStyle([1, $row, 8, $row])->applyFromArray($boldFont);
            $sheet->getStyle([3, $row])->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle([6, $row])->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle([3, $row])->applyFromArray($greenFont);
            $sheet->getStyle([6, $row])->applyFromArray($balanceDue > 0 ? $redFont : $greenFont);
            $sheet->getStyle([1, $row, 8, $row])->applyFromArray($yellowBg);
        }

        // Auto-size key columns
        foreach (range(1, 12) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $filename = "Payout-{$vendor->company_name}-{$period}.xlsx";
        $path = storage_path("app/temp/{$filename}");
        @mkdir(dirname($path), 0775, true);

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function recordPayoutPayment(Request $request, \App\Models\VendorPayout $payout)
    {
        $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'payment_date'     => 'required|date',
            'payment_mode'     => 'nullable|string|in:bank_transfer,cheque,upi,cash,other',
            'reference_number' => 'nullable|string|max:100',
            'remarks'          => 'nullable|string|max:500',
        ]);

        // Calculate how much is already paid
        $totalPaid = \App\Models\PayoutPayment::where('vendor_payout_id', $payout->id)->sum('amount');
        $balanceDue = round(floatval($payout->net_payout) - $totalPaid, 2);
        $newAmount = floatval($request->amount);

        // Validate: amount must not exceed balance
        if ($newAmount > $balanceDue + 0.01) { // +0.01 for floating point
            $error = "Payment amount ({$newAmount}) exceeds balance due ({$balanceDue}). Net payout is {$payout->net_payout}, already paid {$totalPaid}.";
            if ($request->expectsJson()) {
                return response()->json(['error' => $error], 422);
            }
            return back()->with('error', $error);
        }

        // Record payment
        $payment = \App\Models\PayoutPayment::create([
            'vendor_payout_id' => $payout->id,
            'vendor_id'        => $payout->vendor_id,
            'company_code'     => $payout->company_code,
            'amount'           => $newAmount,
            'payment_date'     => $request->payment_date,
            'payment_mode'     => $request->payment_mode,
            'reference_number' => $request->reference_number,
            'remarks'          => $request->remarks,
            'created_by'       => auth()->id(),
        ]);

        // Update payout totals
        $newTotalPaid = round($totalPaid + $newAmount, 2);
        $newBalance = round(floatval($payout->net_payout) - $newTotalPaid, 2);

        $status = 'calculated';
        if ($newBalance <= 0.01) {
            $status = 'paid';
        } elseif ($newTotalPaid > 0) {
            $status = 'partially_paid';
        }

        $payout->update([
            'total_paid'   => $newTotalPaid,
            'balance_due'  => max(0, $newBalance),
            'paid_amount'  => $newTotalPaid,
            'paid_date'    => $request->payment_date,
            'status'       => $status,
        ]);

        \App\Models\ActivityLog::log('payment', 'vendor_payout', $payout, null, [
            'payment_id'    => $payment->id,
            'amount'        => $newAmount,
            'total_paid'    => $newTotalPaid,
            'balance_due'   => max(0, $newBalance),
            'payment_mode'  => $request->payment_mode,
            'reference'     => $request->reference_number,
            'status'        => $status,
        ], "Payment of {$newAmount} recorded for {$payout->vendor->company_name} {$payout->payout_month}/{$payout->payout_year} — {$status}");

        $msg = "Payment of " . number_format($newAmount, 2) . " recorded. ";
        $msg .= $newBalance <= 0.01 ? "Payout fully paid." : "Balance due: " . number_format(max(0, $newBalance), 2);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'status' => $status, 'total_paid' => $newTotalPaid, 'balance_due' => max(0, $newBalance)]);
        }

        return back()->with('success', $msg);
    }

    public function deletePayoutPayment(\App\Models\PayoutPayment $payment)
    {
        $payout = $payment->payout;
        $amount = $payment->amount;
        $payment->delete();

        // Recalculate totals
        $totalPaid = \App\Models\PayoutPayment::where('vendor_payout_id', $payout->id)->sum('amount');
        $balance = round(floatval($payout->net_payout) - $totalPaid, 2);

        $status = 'calculated';
        if ($balance <= 0.01 && $totalPaid > 0) {
            $status = 'paid';
        } elseif ($totalPaid > 0) {
            $status = 'partially_paid';
        }

        $payout->update([
            'total_paid'  => $totalPaid,
            'balance_due' => max(0, $balance),
            'paid_amount' => $totalPaid,
            'status'      => $status,
        ]);

        \App\Models\ActivityLog::log('deleted', 'payout_payment', $payout, null, [
            'deleted_amount' => $amount,
            'new_total_paid' => $totalPaid,
            'new_balance'    => max(0, $balance),
        ], "Payment of {$amount} deleted — balance: {$balance}");

        return back()->with('success', "Payment of " . number_format($amount, 2) . " removed. Balance due: " . number_format(max(0, $balance), 2));
    }

    public function supersedeCharge(Request $request, \App\Models\VendorMonthlyCharge $charge)
    {
        $request->validate([
            'supersede_reason' => 'required|string|max:500',
        ]);

        if ($charge->charge_status === 'superseded') {
            return back()->with('error', 'This charge is already superseded.');
        }

        $charge->update([
            'charge_status'    => 'superseded',
            'superseded_by'    => auth()->id(),
            'superseded_at'    => now(),
            'supersede_reason' => $request->supersede_reason,
        ]);

        $vendor = $charge->vendor;
        $period = date('M Y', mktime(0, 0, 0, $charge->charge_month, 1, $charge->charge_year));
        $amount = floatval($charge->total_charge ?? $charge->calculated_amount ?? 0);

        \App\Models\ActivityLog::log('superseded', 'vendor_monthly_charge', $charge, null, [
            'vendor'  => $vendor->company_name ?? '—',
            'period'  => $period,
            'amount'  => $amount,
            'reason'  => $request->supersede_reason,
            'voided_by' => auth()->user()->name,
        ], "Charge of {$amount} superseded for {$vendor->company_name} ({$period}) — {$request->supersede_reason}");

        return back()->with('success', "Charge of " . number_format($amount, 2) . " for {$vendor->company_name} ({$period}) has been superseded.");
    }

    public function restoreCharge(Request $request, \App\Models\VendorMonthlyCharge $charge)
    {
        if ($charge->charge_status !== 'superseded') {
            return back()->with('error', 'This charge is not superseded.');
        }

        $oldReason = $charge->supersede_reason;

        $charge->update([
            'charge_status'    => 'active',
            'superseded_by'    => null,
            'superseded_at'    => null,
            'supersede_reason' => null,
        ]);

        $vendor = $charge->vendor;
        $amount = floatval($charge->total_charge ?? $charge->calculated_amount ?? 0);

        \App\Models\ActivityLog::log('restored', 'vendor_monthly_charge', $charge, null, [
            'vendor'     => $vendor->company_name ?? '—',
            'amount'     => $amount,
            'old_reason' => $oldReason,
            'restored_by' => auth()->user()->name,
        ], "Charge of {$amount} restored for {$vendor->company_name} by " . auth()->user()->name);

        return back()->with('success', "Charge restored to active.");
    }

    /**
     * Record Warehouse Charge Adjustment
     */
    public function storeWarehouseAdjustment(Request $request, VendorPayout $payout)
    {
        $request->validate([
            'amount'           => 'required|numeric',
            'adjustment_date'  => 'required|date',
            'reason'           => 'nullable|string|max:255',
            'remarks'          => 'nullable|string|max:500',
        ]);

        PayoutWarehouseAdjustment::create([
            'vendor_payout_id' => $payout->id,
            'amount'           => $request->amount,
            'reason'           => $request->reason,
            'remarks'          => $request->remarks,
            'adjustment_date'  => $request->adjustment_date,
            'created_by'       => auth()->id(),
        ]);
        $netPayoutBefore = $payout->net_payout;
        $netPayoutAfter = round($payout->net_payout + $request->amount, 2);
        $payout->update([ 'net_payout' => $netPayoutAfter]);

        \App\Models\ActivityLog::log('wh_ch_adjustment', 'WarehouseChargesAdjustment', $payout, [
            'net_payout_before' => $netPayoutBefore,
            'amount'            => $request->amount,
            'net_payout_after'  => $netPayoutAfter,
        ], [
                  'vendor_payout_id'     => $payout->id,
                  'amount'     => $request->amount,
                  'reason' => $request->reason,
                  'adjusted_by' => auth()->user()->name,
              ], "Wh charge adjustment of {$request->amount} for {$payout->id} by " . auth()->user()->name);

        return back()->with('success', 'Warehouse charge adjustment recorded successfully.');
    }

    /**
     * Delete Warehouse Charge Adjustment
     */
    public function deleteWarehouseAdjustment(PayoutWarehouseAdjustment $adjustment)
    {
        // Get the related payout
        $payout = $adjustment->payout;

        if (!$payout) {
            return back()->with('error', 'Related payout not found.');
        }

        $netPayoutBefore = $payout->net_payout;
        $netPayoutAfter = round($payout->net_payout - $adjustment->amount, 2);

        // Reverse the adjustment → Add the amount back to net_payout
        $payout->update(['net_payout' => $netPayoutAfter]);

        // Activity Log
        \App\Models\ActivityLog::log(
            'wh_ch_adjustment_deleted',
            'WarehouseChargesAdjustment',
            $adjustment,
            [
            'net_payout_before' => $netPayoutBefore,
            'amount'            => $adjustment->amount,
            'net_payout_after'  => $netPayoutAfter,
          ],
            [
                'vendor_payout_id' => $adjustment->vendor_payout_id ?? '—',
                'amount'           => $adjustment->amount,
                'reason'           => $adjustment->reason,
                'deleted_by'       => auth()->user()->name,
            ],
            "WH charge adjustment of {$adjustment->amount} deleted by " . auth()->user()->name
        );

        // Delete the record
        $adjustment->delete();

        return back()->with('success', 'Warehouse charge adjustment deleted.');
    }

}
