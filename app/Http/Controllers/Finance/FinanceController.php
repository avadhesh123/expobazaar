<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\{FinanceReceivable, Chargeback, VendorPayout, Vendor, Order, SalesChannel, LiveSheet};
use App\Services\{DashboardService, FinanceService, VendorService};
use Illuminate\Http\Request;

use function PHPUnit\Framework\isArray;

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
        $user = auth()->user();
        // Get user's allowed company codes
        $userCompanyCodes = $user->company_codes ?? [];
        if (is_string($userCompanyCodes)) {
            $userCompanyCodes = json_decode($userCompanyCodes, true) ?? [];
        }
        $userCompanyCodes = array_filter(array_map('strval', $userCompanyCodes));

        $receivables = FinanceReceivable::with('order.salesChannel', 'order.chargebacks')
            // Restrict to user's companies (non-admins)
            ->when(
                !$user->isAdmin() && !empty($userCompanyCodes),
                fn($q) => $q->whereIn('company_code', $userCompanyCodes)
            )
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when(!empty($request->status), fn($q, $v) => $q->where('payment_status', $v))
            ->when($request->channel_id, fn($q, $v) => $q->where('channel_id', $v))
            //  ->when($request->payment === 'unpaid', fn($q) => $q->where('payment_status', 'unpaid'))
            ->orderByRaw("FIELD(payment_status, 'unpaid', 'partial', 'paid') ASC")
            ->latest()
            ->paginate(30);

        // $sql = $receivables->toSql();
        // $bindings = $receivables->getBindings();

        // foreach ($bindings as $binding) {
        //     $value = is_numeric($binding) ? $binding : "'".addslashes($binding)."'";
        //     $sql = preg_replace('/\?/', $value, $sql, 1);
        // }

        // dd("Final SQL Query:", $sql);

        //  //print $receivables->appends($request->query()); 
        //   exit;
        //        $channels = SalesChannel::where('is_active', true)->get();

        $channels = SalesChannel::where('is_active', true)
            ->when(!$user->isAdmin() && !empty($userCompanyCodes), function ($q) use ($userCompanyCodes) {
                foreach ($userCompanyCodes as $code) {
                    $q->whereJsonContains('company_codes', $code);
                }
                return $q;
            })
            ->orderBy('name')
            ->get();

        // Fix: $summary was never built — blade was crashing with "Undefined variable: summary"
        $summary = [
            'unpaid_count'     => FinanceReceivable::where('payment_status', 'unpaid')
                ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->count(),
            'unpaid_total'     => FinanceReceivable::where('payment_status', 'unpaid')
                ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('net_receivable'),
            'partial_count'    => FinanceReceivable::where('payment_status', 'partial')
                ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->count(),
            'partial_total'    => FinanceReceivable::where('payment_status', 'partial')
                ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('net_receivable'),
            'total_deductions' => FinanceReceivable::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('platform_commission')
                + FinanceReceivable::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('platform_fee')
                + FinanceReceivable::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('insurance_charge')
                + FinanceReceivable::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->sum('other_deductions'),
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
        $chargebacks = Chargeback::with('order.salesChannel', 'vendor')
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn($q, $v) => $q->whereHas('order', fn($oq) => $oq->where('company_code', $v)))
            ->latest()->paginate(20);



        $stats = [
            'total'        => Chargeback::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->count(),
            'pending'      => Chargeback::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->where('status', 'pending_confirmation')->count(),
            'confirmed'    => Chargeback::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->where('status', 'confirmed')->count(),
            'total_amount' => Chargeback::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->where('status', 'confirmed')->sum('amount'),
        ];

        // Fix #2: $vendors was never passed — blade vendor filter crashed with Undefined variable
        $vendors = Vendor::active()->orderBy('company_name')->get();

        return view('finance.chargebacks.index', compact('chargebacks', 'stats', 'vendors'));
    }

    public function raiseChargeback(Request $request)
    {
        $request->validate([
            'order_number'    => 'required|exists:orders,order_number',
            'amount'      => 'required|numeric|min:0.01',
            'reason'      => 'required|string|max:500',
            'description' => 'nullable|string|max:1000',
        ]);
   
        try {
            // ── Order validation guards ────────────────────────────────────

            // 1. Order must exist (Route Model Binding handles this, but double-check)
            $order = Order::findByOrderNumber($request->order_number);
            print_r($order->toArray());
            if (!$order || !$order->exists) {
                return back()->with('error', 'Order not found or invalid.');
            }

            // 2. Order must not be cancelled
            if (in_array($order->status ?? '', ['cancelled', 'refunded', 'voided'])) {
                return back()->with('error', "Cannot raise chargeback on a {$order->status} order.");
            }

            // 3. Order must have a vendor associated
            if (!$order->vendor_id && !$order->items()->whereNotNull('vendor_id')->exists()) {
                return back()->with('error', 'Order has no vendor associated. Cannot raise chargeback.');
            }

            // 4. Chargeback amount cannot exceed order total
            $orderTotal = (float) ($order->total_amount ?? $order->grand_total ?? 0);
            if ($orderTotal > 0 && $request->amount > $orderTotal) {
                return back()->with('error', "Chargeback amount (\${$request->amount}) exceeds order total (\${$orderTotal}).");
            }

            // 5. Check for existing pending/confirmed chargeback on same order
            $existing = \App\Models\Chargeback::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->first();

            if ($existing) {
                return back()->with('error', "An active chargeback already exists for this order (Status: {$existing->status}, Amount: \${$existing->amount}).");
            }

            // 6. Order must be at least 1 day old (prevent accidental immediate chargebacks)
            if ($order->created_at && $order->created_at->isToday()) {
                return back()->with('error', 'Chargebacks can only be raised on orders at least 1 day old. Please verify the issue first.');
            }

            // ── All checks passed — proceed ────────────────────────────────
            $this->financeService->raiseChargeback($order, $request->only(['amount', 'reason', 'description']));

            return back()->with('success', "Chargeback of \${$request->amount} raised on order #{$order->id}. Sourcing team notified for confirmation.");
        } catch (\Exception $e) {
            \Log::error('Chargeback creation failed: ' . $e->getMessage(), [
                'order_id' => $order->id ?? null,
                'user_id'  => auth()->id(),
            ]);
            return back()->with('error', 'Failed to raise chargeback: ' . $e->getMessage())->withInput();
        }
    }

    // ─── VENDOR PAYOUTS ──────────────────────────────────────────
    public function payouts(Request $request)
    {
        $user = auth()->user();

        // Handle company_codes (could be array or JSON string)
        $userCompanyCodes = $user->company_codes ?? [];
        if (is_string($userCompanyCodes)) {
            $userCompanyCodes = json_decode($userCompanyCodes, true) ?? [];
        }
        $userCompanyCodes = array_filter(array_map('strval', $userCompanyCodes));

        $payouts = VendorPayout::with('vendor')
            // Restrict to user's allowed companies (non-admins)
            ->when(
                !$user->isAdmin() && !empty($userCompanyCodes),
                fn($q) => $q->whereIn('company_code', $userCompanyCodes)
            )
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->vendor_id, fn($q, $v) => $q->where('vendor_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->month, fn($q, $v) => $q->where('payout_month', $v))
            ->when($request->year, fn($q, $v) => $q->where('payout_year', $v))
            ->latest()
            ->paginate(20);

        // Vendors list - also filtered by user company
        $vendors = Vendor::active()->orderBy('company_name')
            ->when(
                !$user->isAdmin() && !empty($userCompanyCodes),
                fn($q) => $q->whereIn('company_code', $userCompanyCodes)
            )
            ->get();

        // KPI summary for the header cards
        try {
            $summaryQuery = VendorPayout::query()
                ->when(
                    !$user->isAdmin() && !empty($userCompanyCodes),
                    fn($q) => $q->whereIn('company_code', $userCompanyCodes)
                );

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
            ];
        } catch (\Exception $e) {
            \Log::warning('Payout summary calculation failed: ' . $e->getMessage());
            $summary = [
                'total_payouts'    => 0,
                'paid_this_month'  => 0,
                'pending_invoices' => 0,
            ];
        }

        return view('finance.payouts.index', compact('payouts', 'vendors', 'summary'));
    }

    public function showPayout(VendorPayout $payout)
    {
        $payout->load('vendor');

        // Get orders for this vendor in this payout period
        $orders = Order::whereHas('items', fn($q) => $q->where('vendor_id', $payout->vendor_id))
            ->whereMonth('order_date', $payout->payout_month)
            ->whereYear('order_date', $payout->payout_year)
            ->with(['salesChannel', 'receivable', 'items' => fn($q) => $q->where('vendor_id', $payout->vendor_id)->with('product')])
            ->get();


        // Before the loop — build FIFO commission map per product for this vendor
        $vendorLiveSheets = \App\Models\LiveSheet::where('vendor_id', $payout->vendor_id)
            ->where('status', 'locked') // Only consider locked sheets for commission (approved but not yet paid out)
            ->orderBy('approved_at', 'asc') // FIFO — oldest first
            ->with(['items' => fn($q) => $q->select('id', 'live_sheet_id', 'product_id', 'quantity', 'product_details')])
            ->get();

        // Build FIFO queue with BOTH vendor_wsp and commission per batch
        $fifoQueue = [];
        foreach ($vendorLiveSheets as $ls) {
            $commPercent = floatval($ls->commission_percentage ?? 0);
            foreach ($ls->items as $lsItem) {
                $pid = $lsItem->product_id;
                $d = $lsItem->product_details ?? [];
                $batchWsp = floatval($d['wsp'] ?? $d['vendor_wsp'] ?? $lsItem->unit_price ?? 0);

                if (!isset($fifoQueue[$pid])) {
                    $fifoQueue[$pid] = [];
                }
                $fifoQueue[$pid][] = [
                    'live_sheet_id' => $ls->id,
                    'vendor_wsp'    => $batchWsp,
                    'commission'    => $commPercent,
                    'remaining_qty' => intval($lsItem->quantity),
                ];
            }
        }
        // echo '<pre>';
        // print_r($fifoQueue);
        // print_r($vendorLiveSheets->toArray());
        // echo '</pre>';
        // exit;
        // Now we have a FIFO queue of batches with both WSP and commission percentage for each
        $periodStart = \Carbon\Carbon::create($payout->payout_year, $payout->payout_month, 1)->startOfMonth();
        $priorSold = \App\Models\OrderItem::where('vendor_id', $payout->vendor_id)
            ->whereHas('order', fn($q) => $q->where('order_date', '<', $periodStart))
            ->select('product_id', \DB::raw('SUM(quantity) as sold'))
            ->groupBy('product_id')
            ->pluck('sold', 'product_id');

        // Deduct prior sold (same as before)
        foreach ($priorSold as $pid => $soldQty) {
            if (!isset($fifoQueue[$pid])) {
                continue;
            }
            $remaining = intval($soldQty);
            foreach ($fifoQueue[$pid] as &$batch) {
                if ($remaining <= 0) {
                    break;
                }
                $deduct = min($remaining, $batch['remaining_qty']);
                $batch['remaining_qty'] -= $deduct;
                $remaining -= $deduct;
            }
            unset($batch);
        }

        // Build line items — FIFO for both WSP and commission
        $lineItems = collect();
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                $pid = $product->id;
                $qty = intval($item->quantity);
                $qtyToAllocate = $qty;

                $totalSaleAmount = 0;
                $totalCommission = 0;
                $totalPayout = 0;
                $details = [];

                if (isset($fifoQueue[$pid])) {
                    foreach ($fifoQueue[$pid] as &$batch) {
                        if ($qtyToAllocate <= 0) {
                            break;
                        }
                        if ($batch['remaining_qty'] <= 0) {
                            continue;
                        }

                        $allocate = min($qtyToAllocate, $batch['remaining_qty']);
                        $batchSale = round($batch['vendor_wsp'] * $allocate, 2);
                        $batchComm = round(($batch['commission'] / 100) * $batchSale, 2);
                        $batchPayout = round($batchSale - $batchComm, 2);

                        $totalSaleAmount += $batchSale;
                        $totalCommission += $batchComm;
                        $totalPayout += $batchPayout;

                        $details[] = "{$allocate}u × \${$batch['vendor_wsp']} @ {$batch['commission']}%";

                        $batch['remaining_qty'] -= $allocate;
                        $qtyToAllocate -= $allocate;
                    }
                    unset($batch);
                }

                // Unallocated qty — fallback to product.vendor_wsp, 0% commission
                if ($qtyToAllocate > 0) {
                    $fallbackWsp = floatval($product->vendor_wsp ?? $product->fob_price ?? 0);
                    $fallbackSale = round($fallbackWsp * $qtyToAllocate, 2);
                    $totalSaleAmount += $fallbackSale;
                    $totalPayout += $fallbackSale;
                    $details[] = "{$qtyToAllocate}u × \${$fallbackWsp} @ 0%";
                }

                // Weighted average WSP for display
                $avgWsp = $qty > 0 ? round($totalSaleAmount / $qty, 2) : 0;

                $lineItems->push((object)[
                    'order_id'      => $order->id,
                    'order_number'  => $order->order_number,
                    'sku'           => $item->sku ?? $product->sku ?? '—',
                    'channel'       => $order->salesChannel->name ?? '—',
                    'vendor_wsp'    => $avgWsp,
                    'qty'           => $qty,
                    'sale_amount'   => round($totalSaleAmount, 2),
                    'commission'    => round($totalCommission, 2),
                    'net_payout'    => round($totalPayout, 2),
                    'fifo_detail'   => implode(' + ', $details),
                ]);
            }
        }

        /*    //$saleAmount = floatval($item->total_price ?? $item->unit_price * $qty);
                //  $payoutAmount = round($vendorWsp * $qty, 2);
                $commission_percentage = 25; // Assuming a flat 25% commission for demonstration
                $commission = round($commission_percentage / 100 * $saleAmount, 2);
                //  $commission = round($saleAmount - $payoutAmount, 2);
                $payoutAmount = round($saleAmount - $commission, 2);

                $lineItems->push((object)[
                    'order_id'      => $order->id,
                    'order_number'  => $order->order_number,
                    'sku'           => $item->sku ?? $product->sku ?? '—',
                    'channel'       => $order->salesChannel->name ?? '—',
                    'vendor_wsp'    => $vendorWsp,
                    'qty'           => $qty,
                    'sale_amount'   => $saleAmount,
                    'commission'    => $commission,
                    'net_payout'    => $payoutAmount,
                ]);
            }
        } */
        // Summary
        $payoutSummary = [
            'total_qty'        => $lineItems->sum('qty'),
            'total_sales'      => $lineItems->sum('sale_amount'),
            'total_commission' => $lineItems->sum('commission'),
            'total_payout'     => $lineItems->sum('net_payout'), 
        ];

        // Get warehouse charges
        // $warehouseCharges = \App\Models\WarehouseCharge::where('vendor_id', $payout->vendor_id)
        //     ->where('charge_month', $payout->payout_month)
        //     ->where('charge_year', $payout->payout_year)
        //     ->with('warehouse')
        //     ->get();
            
$warehouseCharges = \App\Models\VendorMonthlyCharge::where('vendor_id', $payout->vendor_id)
        ->where('charge_month', $payout->payout_month)
        ->where('charge_year', $payout->payout_year)
        ->with('warehouse','grn')
        ->get();
// echo '<pre>';
// print_r($warehouseCharges->toArray());
// echo '</pre>';
// exit;
        // Get chargebacks
        $chargebacks = Chargeback::where('vendor_id', $payout->vendor_id)
            ->where('status', 'confirmed')
            ->whereMonth('confirmed_at', $payout->payout_month)
            ->whereYear('confirmed_at', $payout->payout_year)
            ->with('order')
            ->get();

        return view('finance.payouts.show', compact('payout', 'orders', 'lineItems', 'payoutSummary', 'warehouseCharges', 'chargebacks'));
    }

    public function calculatePayout(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'month'     => 'required|integer|between:1,12',
            'year'      => 'required|integer',
        ]);
        $vendor = Vendor::findOrFail($request->vendor_id);
        $this->financeService->calculateVendorPayout($vendor, $request->month, $request->year);
        return back()->with('success', "Payout calculated for {$vendor->company_name}.");
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
        $path = $request->file('invoice')->store('vendor-invoices/' . $payout->vendor_id, 'public');
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

        $user = auth()->user();

        // Handle company_codes (could be array or JSON string)
        $userCompanyCodes = $user->company_codes ?? [];
        if (is_string($userCompanyCodes)) {
            $userCompanyCodes = json_decode($userCompanyCodes, true) ?? [];
        }
        $userCompanyCodes = array_filter(array_map('strval', $userCompanyCodes));

        $pricings = \App\Models\PlatformPricing::with('product', 'salesChannel', 'asn')
            ->where('status', 'submitted')
            // Non-admin users: restrict to their assigned companies
            ->when(
                !$user->isAdmin() && !empty($userCompanyCodes),
                fn($q) => $q->whereIn('company_code', $userCompanyCodes)
            )
            // Admin users: allow manual filter
            ->when(
                $user->isAdmin() && $request->company_code,
                fn($q, $v) => $q->where('company_code', $v)
            )->paginate(30);
        return view('finance.pricing-review', compact('pricings'));
    }

    public function approvePricing(\App\Models\Asn $asn)
    {
        app(\App\Services\PricingService::class)->reviewPricing($asn, auth()->user(), true);
        return back()->with('success', 'Pricing approved.');
    }

    // ─── LIVE SHEETS — SAP CODE UPDATE ───────────────────────────
    public function liveSheets(Request $request)
    {
        $user = auth()->user();

        // Handle company_codes (could be array or JSON string)
        $userCompanyCodes = $user->company_codes ?? [];
        if (is_string($userCompanyCodes)) {
            $userCompanyCodes = json_decode($userCompanyCodes, true) ?? [];
        }
        $userCompanyCodes = array_filter(array_map('strval', $userCompanyCodes));

        $liveSheets = \App\Models\LiveSheet::with('vendor', 'offerSheet', 'items.product')
            // Non-admin users: restrict to their assigned companies
            ->when(
                !$user->isAdmin() && !empty($userCompanyCodes),
                fn($q) => $q->whereIn('company_code', $userCompanyCodes)
            )
            // Admin users: allow manual filter
            ->when(
                $user->isAdmin() && $request->company_code,
                fn($q, $v) => $q->where('company_code', $v)
            )
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest()->paginate(20);
        return view('finance.live-sheets.index', compact('liveSheets'));
    }

    public function showLiveSheet(\App\Models\LiveSheet $liveSheet)
    {
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

        // ── Pre-validation: check uniqueness across all products and live sheets ──
        $errors = [];
        $seen = []; // track duplicates within the same submission

        foreach ($request->sap_codes as $idx => $row) {
            $code = trim($row['sap_code'] ?? '');
            if ($code === '') continue;

            // Duplicate within current submission
            if (isset($seen[$code])) {
                $errors[] = "SAP code '{$code}' is used multiple times in this form.";
                continue;
            }
            $seen[$code] = true;

            $item = \App\Models\LiveSheetItem::find($row['item_id']);
            if (!$item) continue;

            // Check products table — exclude the current product (re-saving same code is OK)

            $dup = \App\Models\Product::where('sap_code', $code)
                ->when($item->product_id ?? null, function ($q, $productId) {
                    $q->where('id', '!=', $productId);
                })
                ->count();

            if ($dup > 1) {

                $errors[] = "SAP code '{$code}' is already assigned to product '{$dup->sku}' ({$dup->name}).";
                continue;
            }
            // Check live_sheet_items JSON product_details.sap_code on OTHER items

            $dupItem = \App\Models\LiveSheetItem::whereJsonContains('product_details', ['sap_code' => $code])
                ->get();
            foreach ($dupItem as $dup) {
                if (($dup->product_id ?? null) != ($item->product_id ?? null)) {
                    $sku = $dup->product->sku ?? 'unknown';
                    $errors[] = "SAP code '{$code}' is already used on live sheet item ID {$dup->id} with SKU '{$sku}'.";
                }
            }            
        }
       

        if (!empty($errors)) {
            return back()
                ->withErrors(['sap_codes' => $errors])
                ->with('error', 'SAP code validation failed. Codes must be unique across the system.');
        }

        // ── All codes are unique — proceed with update ──
        $updated = 0;
        foreach ($request->sap_codes as $row) {
            $item = \App\Models\LiveSheetItem::find($row['item_id']);
            if ($item && $item->live_sheet_id === $liveSheet->id) {
                $details = $item->product_details ?? [];
                $details['sap_code'] = $row['sap_code'];
                $details['vendor_wsp'] = $row['vendor_wsp'];
                $item->update(['product_details' => $details]);

                if (!empty($row['sap_code']) && $item->product) {
                    $item->product->update(['sap_code' => $row['sap_code']]);
                }
                if (!empty($row['vendor_wsp']) && $item->product) {
                    $item->product->update(['vendor_wsp' => $row['vendor_wsp']]);
                }
                $updated++;
            }
        }

        // ── All codes are valid — proceed with update ──
        // $updated = 0;
        // foreach ($request->sap_codes as $row) {
        //     $item = \App\Models\LiveSheetItem::find($row['item_id']);
        //     if (!$item || $item->live_sheet_id !== $liveSheet->id) {
        //         continue;
        //     }

        //     $sapCode = trim($row['sap_code'] ?? '');
        //     $vendorWsp = $row['vendor_wsp'] ?? null;

        //     // 1. Update PRODUCT table (master — single source of truth)
        //     if ($item->product) {
        //         $productUpdate = [];
        //         if ($sapCode !== '') {
        //             $productUpdate['sap_code'] = $sapCode;
        //         }
        //         if ($vendorWsp !== null && $vendorWsp !== '') {
        //             $productUpdate['vendor_wsp'] = floatval($vendorWsp);
        //         }
        //         if (!empty($productUpdate)) {
        //             $item->product->update($productUpdate);
        //         }
        //     }

        //     // 2. Sync product_details on THIS live sheet item from product table
        //     $details = $item->product_details ?? [];
        //     if ($item->product) {
        //         $details['sap_code'] = $item->product->fresh()->sap_code ?? $details['sap_code'] ?? '';
        //         $details['vendor_wsp'] = $item->product->fresh()->vendor_wsp ?? $details['vendor_wsp'] ?? '';
        //     }
        //     $item->update(['product_details' => $details]);

        //     // 3. Backfill: update ALL other live sheet items with same product_id
        //     \App\Models\LiveSheetItem::where('product_id', $item->product_id)
        //         ->where('id', '!=', $item->id)
        //         ->each(function ($otherItem) use ($item) {
        //             $d = $otherItem->product_details ?? [];
        //             $d['sap_code'] = $item->product->sap_code ?? $d['sap_code'] ?? '';
        //             $d['vendor_wsp'] = $item->product->vendor_wsp ?? $d['vendor_wsp'] ?? '';
        //             $otherItem->update(['product_details' => $d]);
        //         });

        //     $updated++;
        // }

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
                $spreadsheet = $reader->load($fullPath);
                $rows = $spreadsheet->getActiveSheet()->toArray();
            } else {
                $rows = [];
                if (($handle = fopen($fullPath, 'r')) !== false) {
                    while (($row = fgetcsv($handle)) !== false) {
                        $rows[] = $row;
                    }
                    fclose($handle);
                }
            }

            // File read from temp upload path, no cleanup needed

            if (count($rows) < 2) {
                return back()->with('error', 'File is empty or has no data rows.');
            }

            // Find column indexes
            $header = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
            $itemIdCol = null;
            $sapCol = null;
            $payoutCol = null;

            foreach ($header as $i => $h) {
                if (in_array($h, ['item id', 'item_id', 'id'])) $itemIdCol = $i;
                if (in_array($h, ['new sap code', 'new_sap_code', 'sap code', 'sap_code'])) $sapCol = $i;
                if (in_array($h, ['new vendor payout price', 'vendor_wsp', 'vendor payout price', 'vendor wsp', 'wsp'])) $payoutCol = $i;
            }

            if ($sapCol === null && $payoutCol === null) {
                return back()->with('error', 'CSV must have a "New SAP Code" or "New Vendor WSP" column.');
            }

            $updated = 0;
            $errors = [];
            $sapCodes = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $sapCode = $sapCol !== null ? trim($row[$sapCol] ?? '') : '';
                $payoutPrice = $payoutCol !== null ? trim($row[$payoutCol] ?? '') : '';

                if ($sapCode === '' && $payoutPrice === '') continue;

                $itemId = $itemIdCol !== null ? intval($row[$itemIdCol] ?? 0) : null;
                if (!$itemId) continue;

                // SAP uniqueness check
                if ($sapCode !== '' && in_array($sapCode, $sapCodes)) {
                    $errors[] = "Row " . ($i + 1) . ": Duplicate SAP code '{$sapCode}'.";
                    continue;
                }
                if ($sapCode !== '') $sapCodes[] = $sapCode;

                // Validate payout price
                if ($payoutPrice !== '' && !is_numeric($payoutPrice)) {
                    $errors[] = "Row " . ($i + 1) . ": Invalid payout price '{$payoutPrice}'.";
                    continue;
                }

                $item = \App\Models\LiveSheetItem::where('id', $itemId)
                    ->where('live_sheet_id', $liveSheet->id)
                    ->first();

                if (!$item) continue;

                // Check SAP against existing products
                if ($sapCode !== '') {
                    $dup = \App\Models\Product::where('sap_code', $sapCode)
                        ->when($item->product_id, fn($q) => $q->where('id', '!=', $item->product_id))
                        ->first();

                    if ($dup) {
                        $errors[] = "Row " . ($i + 1) . ": SAP '{$sapCode}' already used by {$dup->sku}.";
                        continue;
                    }
                }

                // Update product
                if ($item->product_id) {
                    $productUpdate = [];
                    if ($sapCode !== '') $productUpdate['sap_code'] = $sapCode;
                    if ($payoutPrice !== '') $productUpdate['vendor_wsp'] = floatval($payoutPrice);
                    if (!empty($productUpdate)) {
                        \App\Models\Product::where('id', $item->product_id)->update($productUpdate);
                    }
                }

                // Update live sheet item product_details
                $d = $item->product_details ?? [];
                if ($sapCode !== '') $d['sap_code'] = $sapCode;
                if ($payoutPrice !== '') $d['vendor_wsp'] = floatval($payoutPrice);
                $item->update(['product_details' => $d]);
                $updated++;
            }

            \App\Models\ActivityLog::log('uploaded', 'sap_codes', $liveSheet, null, [
                'updated' => $updated,
                'errors' => count($errors),
            ], "SAP codes or Vendor payout price uploaded via CSV: {$updated} updated");

            $msg = "{$updated} SAP code,Vendor payout price(s) updated.";
            if (!empty($errors)) {
                $msg .= " Errors: " . implode('; ', array_slice($errors, 0, 5));
            }

            return back()->with($updated > 0 ? 'success' : 'error', $msg);
        } catch (\Exception $e) {
            \Log::error('SAP upload or Vendor payout price failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }
    /**
     * Upload filled SAP CSV and apply codes to products
     */
    public function uploadSapCodes1(Request $request, \App\Models\LiveSheet $liveSheet)
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
            $filePath = $file->store('temp', 'local');
            $fullPath = storage_path('app/' . $filePath);

            if ($ext === 'xlsx') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($fullPath);
                $rows = $spreadsheet->getActiveSheet()->toArray();
            } else {
                $rows = [];
                if (($handle = fopen($fullPath, 'r')) !== false) {
                    while (($row = fgetcsv($handle)) !== false) {
                        $rows[] = $row;
                    }
                    fclose($handle);
                }
            }

            @unlink($fullPath);

            if (count($rows) < 2) {
                return back()->with('error', 'File is empty or has no data rows.');
            }

            // Find column indexes
            $header = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
            $itemIdCol = null;
            $sapCol = null;

            foreach ($header as $i => $h) {
                if (in_array($h, ['item id', 'item_id', 'id'])) $itemIdCol = $i;
                if (in_array($h, ['new sap code', 'new_sap_code', 'sap code', 'sap_code'])) $sapCol = $i;
            }

            if ($sapCol === null) {
                return back()->with('error', 'CSV must have a "New SAP Code" column.');
            }

            $updated = 0;
            $errors = [];
            $sapCodes = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $sapCode = trim($row[$sapCol] ?? '');
                if ($sapCode === '') continue;

                // Get item ID from column or match by row order
                $itemId = $itemIdCol !== null ? intval($row[$itemIdCol] ?? 0) : null;

                if (!$itemId) continue;

                // Check uniqueness
                if (in_array($sapCode, $sapCodes)) {
                    $errors[] = "Row " . ($i + 1) . ": Duplicate SAP code '{$sapCode}'.";
                    continue;
                }
                $sapCodes[] = $sapCode;

                $item = \App\Models\LiveSheetItem::where('id', $itemId)
                    ->where('live_sheet_id', $liveSheet->id)
                    ->first();

                if (!$item) continue;

                // Check against existing products
                $dup = \App\Models\Product::where('sap_code', $sapCode)
                    ->when($item->product_id, fn($q) => $q->where('id', '!=', $item->product_id))
                    ->first();

                if ($dup) {
                    $errors[] = "Row " . ($i + 1) . ": SAP '{$sapCode}' already used by {$dup->sku}.";
                    continue;
                }

                // Update product and live sheet item
                if ($item->product_id) {
                    \App\Models\Product::where('id', $item->product_id)->update(['sap_code' => $sapCode]);
                }
                $d = $item->product_details ?? [];
                $d['sap_code'] = $sapCode;
                $item->update(['product_details' => $d]);
                $updated++;
            }

            \App\Models\ActivityLog::log('uploaded', 'sap_codes', $liveSheet, null, [
                'updated' => $updated,
                'errors' => count($errors),
            ], "SAP codes uploaded via CSV: {$updated} updated");

            $msg = "{$updated} SAP code(s) updated.";
            if (!empty($errors)) {
                $msg .= " Errors: " . implode('; ', array_slice($errors, 0, 5));
            }

            return back()->with($updated > 0 ? 'success' : 'error', $msg);
        } catch (\Exception $e) {
            \Log::error('SAP upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    // ═══ VENDOR RATE CARDS ═══

    public function vendorRateCards(Request $request)
    {
        $user = auth()->user();
        $userCompanyCodes = $user->company_codes ?? [];   // Array

        // Convert to array if it's stored as JSON string
        if (is_string($userCompanyCodes)) {
            $userCompanyCodes = json_decode($userCompanyCodes, true) ?? [];
        }
        $rateCards = \App\Models\VendorRateCard::with('vendor', 'creator', 'approver')
            // Restrict to user's allowed companies (unless admin)
            ->when(!$user->isAdmin() && !empty($userCompanyCodes), function ($q) use ($userCompanyCodes) {
                return $q->whereIn('company_code', $userCompanyCodes);
            })
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->vendor_id, fn($q, $v) => $q->where('vendor_id', $v))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        // Filter vendors based on user's allowed companies
        $vendors = \App\Models\Vendor::orderBy('company_name')
            ->when(!$user->isAdmin() && !empty($userCompanyCodes), fn($q) => $q->whereIn('company_code', $userCompanyCodes))
            ->get();


        return view('finance.vendor-rate-cards', compact('rateCards', 'vendors'));
    }

    public function storeVendorRateCard(Request $request)
    {
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
        $currency = match ($vendor->company_code) {
            '2000' => 'INR',
            '2200' => 'EUR',
            default => 'USD',
        };
        $maxV = \App\Models\VendorRateCard::where('vendor_id', $vendor->id)->max('version') ?? 0;

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
        ]), ['company_code' => $vendor->company_code, 'currency' => $currency, 'version' => $maxV + 1, 'status' => 'draft', 'created_by' => auth()->id()]));

        \App\Models\ActivityLog::log('created', 'vendor_rate_card', $rc, null, $rc->toArray(), "Rate card v{$rc->version} for {$vendor->company_name}");
        return back()->with('success', "Rate card v{$rc->version} created for {$vendor->company_name}.");
    }

    public function submitVendorRateCard(\App\Models\VendorRateCard $vendorRateCard)
    {
        if (!$vendorRateCard->isComplete()) return back()->with('error', 'All rate fields must be filled.');
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
        $user = auth()->user();
        // User's allowed company codes
        $userCompanyCodes = $user->company_codes ?? [];
        if (is_string($userCompanyCodes)) {
            $userCompanyCodes = json_decode($userCompanyCodes, true) ?? [];
        }
        $userCompanyCodes = array_filter(array_map('strval', $userCompanyCodes));

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $charges = \App\Models\VendorMonthlyCharge::with('vendor', 'grn', 'warehouse')
            ->when(!$user->isAdmin() && !empty($userCompanyCodes), fn($q) => $q->whereIn('company_code', $userCompanyCodes))
            ->byMonth($month, $year)->when($request->vendor_id, fn($q, $v) => $q->where('vendor_id', $v))
            ->orderBy('vendor_id')->paginate(50)->withQueryString();
        $vendors = \App\Models\Vendor::orderBy('company_name')
            ->when(!$user->isAdmin() && !empty($userCompanyCodes), fn($q) => $q->whereIn('company_code', $userCompanyCodes))
            ->get();
        $baseQ = \App\Models\VendorMonthlyCharge::byMonth($month, $year);
        $stats = [
            'total_charges' => (float)(clone $baseQ)->sum('total_charges'),
            'total_inward' => (float)(clone $baseQ)->sum('inward_charge'),
            'total_storage' => (float)(clone $baseQ)->sum('storage_charge'),
            'total_fulfill' => (float)(clone $baseQ)->sum('fulfillment_charge'),
            'total_pickpack' => (float)(clone $baseQ)->sum('pick_pack_charge'),
            'total_material' => (float)(clone $baseQ)->sum('material_cost'),
            'vendor_count' => (int)(clone $baseQ)->distinct('vendor_id')->count('vendor_id'),
            'pending_count' => (int)(clone $baseQ)->where('status', 'calculated')->count(),
        ];
        return view('finance.vendor-charges.index', compact('charges', 'vendors', 'stats', 'month', 'year'));
    }

    public function runVendorCharges(Request $request)
    {

        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2024',
            'vendor_id' => 'nullable|exists:vendors,id'
        ]);
        $service = new \App\Services\WarehouseChargesService();
        $results = $service->runMonthlyCharges($request->month, $request->year, $request->vendor_id, auth()->id(), (bool)$request->dry_run);

        $msg = ($request->dry_run ? "[DRY RUN] " : "") . "{$results['created']} created, {$results['skipped']} skipped.";
        if (!empty($results['errors'])) $msg .= " Errors: " . implode('; ', array_slice($results['errors'], 0, 5));
        return back()->with($results['created'] > 0 ? 'success' : 'error', $msg);
    }

    public function approveVendorCharge(\App\Models\VendorMonthlyCharge $vendorMonthlyCharge)
    {
        $vendorMonthlyCharge->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now(), 'is_locked' => true]);
        return back()->with('success', 'Charge approved.');
    }

    public function vendorStatement(Request $request, \App\Models\Vendor $vendor)
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $service = new \App\Services\WarehouseChargesService();
        $statement = $service->getVendorStatement($vendor->id, $month, $year);
        return view('finance.vendor-charges.statement', compact('statement', 'month', 'year'));
    }

    public function downloadVendorCharges(Request $request)
    {
        $user = auth()->user();
        // User's allowed company codes
        $userCompanyCodes = $user->company_codes ?? [];
        if (is_string($userCompanyCodes)) {
            $userCompanyCodes = json_decode($userCompanyCodes, true) ?? [];
        }
        $userCompanyCodes = array_filter(array_map('strval', $userCompanyCodes));

        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);
        $charges = \App\Models\VendorMonthlyCharge::with('vendor', 'grn')
            ->when(!$user->isAdmin() && !empty($userCompanyCodes), fn($q) => $q->whereIn('company_code', $userCompanyCodes))
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
}
