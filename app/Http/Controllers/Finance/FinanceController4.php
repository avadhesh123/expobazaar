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
    ) {
    }

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
        $receivables = FinanceReceivable::with('order.salesChannel', 'order.chargebacks')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->channel_id, fn ($q, $v) => $q->where('channel_id', $v))
            ->when($request->payment === 'unpaid', fn ($q) => $q->where('status', 'unpaid'))
            ->orderByRaw("FIELD(status, 'unpaid', 'partial', 'paid') ASC")
            ->latest()->paginate(30);

        $channels = SalesChannel::where('is_active', true)->get();
        return view('finance.receivables.index', compact('receivables', 'channels'));
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
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn ($q, $v) => $q->whereHas('order', fn ($oq) => $oq->where('company_code', $v)))
            ->latest()->paginate(20);
        return view('finance.chargebacks.index', compact('chargebacks'));
    }

    public function raiseChargeback(Request $request, Order $order)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);
        $this->financeService->raiseChargeback($order, $request->all());
        return back()->with('success', 'Chargeback raised. Sourcing team notified for confirmation.');
    }

    // ─── VENDOR PAYOUTS ──────────────────────────────────────────
    public function payouts(Request $request)
    {
        $payouts = VendorPayout::with('vendor')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->month, fn ($q, $v) => $q->where('payout_month', $v))
            ->when($request->year, fn ($q, $v) => $q->where('payout_year', $v))
            ->latest()->paginate(20);

        $vendors = Vendor::active()->orderBy('company_name')->get();
        return view('finance.payouts.index', compact('payouts', 'vendors'));
    }

    public function showPayout(VendorPayout $payout)
    {
        $payout->load('vendor');

        // Get orders for this vendor in this payout period
        $orders = Order::whereHas('items.product', fn ($q) => $q->where('vendor_id', $payout->vendor_id))
            ->whereMonth('order_date', $payout->payout_month)
            ->whereYear('order_date', $payout->payout_year)
            ->with('salesChannel', 'receivable')
            ->get();

        // Get warehouse charges
        $warehouseCharges = \App\Models\WarehouseCharge::where('vendor_id', $payout->vendor_id)
            ->where('charge_month', $payout->payout_month)
            ->where('charge_year', $payout->payout_year)
            ->with('warehouse')
            ->get();

        // Get chargebacks
        $chargebacks = Chargeback::where('vendor_id', $payout->vendor_id)
            ->where('status', 'confirmed')
            ->whereMonth('created_at', $payout->payout_month)
            ->whereYear('created_at', $payout->payout_year)
            ->with('order')
            ->get();

        return view('finance.payouts.show', compact('payout', 'orders', 'warehouseCharges', 'chargebacks'));
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
        $pricings = \App\Models\PlatformPricing::with('product', 'salesChannel', 'asn')
            ->where('status', 'submitted')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->paginate(30);
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
        $liveSheets = \App\Models\LiveSheet::with('vendor', 'offerSheet', 'items.product')
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
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
            'sap_codes.*.sap_code'   => 'nullable|string|max:50',
        ]);

        $updated = 0;
        foreach ($request->sap_codes as $row) {
            $item = \App\Models\LiveSheetItem::find($row['item_id']);
            if ($item && $item->live_sheet_id === $liveSheet->id) {
                $details = $item->product_details ?? [];
                $details['sap_code'] = $row['sap_code'];
                $item->update(['product_details' => $details]);

                // Also update product master if SAP code provided
                if (!empty($row['sap_code']) && $item->product) {
                    $item->product->update(['sap_code' => $row['sap_code']]);
                }
                $updated++;
            }
        }

        \App\Models\ActivityLog::log('updated', 'live_sheet', $liveSheet, null, ['sap_codes_updated' => $updated], 'SAP codes updated by Finance');

        return back()->with('success', "{$updated} SAP code(s) updated.");
    }

    // ─── SAP CODE EXCEL DOWNLOAD ─────────────────────────────────
    public function downloadSapTemplate(\App\Models\LiveSheet $liveSheet)
    {
        $liveSheet->load('items.product');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SAP Codes');

        // ── Header row ────────────────────────────────────────────
        $headers = ['Item ID', 'Vendor SKU', 'Product Name', 'SAP Code'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

        // Lock A–C columns (read-only hint via protection + grey fill)
        $lockedStyle = [
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                       'startColor' => ['argb' => 'FFF1F5F9']],
            'font' => ['color' => ['argb' => 'FF64748B']],
        ];

        // ── Data rows ─────────────────────────────────────────────
        foreach ($liveSheet->items as $idx => $item) {
            $d      = $item->product_details ?? [];
            $row    = $idx + 2;
            $values = [
                $item->id,
                $item->product->sku ?? '',
                $item->product->name ?? '',
                $d['sap_code'] ?? '',
            ];
            $sheet->fromArray($values, null, "A{$row}");

            // Grey out the read-only reference columns A–C
            $sheet->getStyle("A{$row}:C{$row}")->applyFromArray($lockedStyle);

            // Highlight SAP column D — blue tint if empty, green if already set
            $sapFill = !empty($d['sap_code']) ? 'FFD1FAE5' : 'FFDBEAFE';
            $sheet->getStyle("D{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($sapFill);
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(40);
        $sheet->getColumnDimension('D')->setWidth(22);

        // Freeze header row
        $sheet->freezePane('A2');

        $filename = "SAP-Codes-{$liveSheet->live_sheet_number}.xlsx";
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control'       => 'max-age=0',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ─── SAP CODE EXCEL UPLOAD ────────────────────────────────────
    public function uploadSapCodes(Request $request, \App\Models\LiveSheet $liveSheet)
    {
        $request->validate([
            'sap_file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $liveSheet->load('items');

        try {
            $reader      = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($request->file('sap_file')->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($request->file('sap_file')->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            $maxRow      = $sheet->getHighestRow();
        } catch (\Exception $e) {
            \Log::error('SAP upload parse error: ' . $e->getMessage());
            return back()->with('error', 'Could not read the uploaded file. Please use the downloaded template.');
        }

        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        // Build a lookup of valid item IDs for this live sheet
        $validItemIds = $liveSheet->items->pluck('id')->flip();

        for ($r = 2; $r <= $maxRow; $r++) {
            $itemId  = trim((string) $sheet->getCell("A{$r}")->getValue());
            $sapCode = trim((string) $sheet->getCell("D{$r}")->getValue());

            if (empty($itemId)) {
                continue;
            }

            // Reject item IDs that don't belong to this live sheet
            if (!isset($validItemIds[$itemId])) {
                $errors[] = "Row {$r}: Item ID <strong>{$itemId}</strong> does not belong to this live sheet — skipped.";
                $skipped++;
                continue;
            }

            if (empty($sapCode)) {
                $skipped++;
                continue; // Blank SAP — leave existing value untouched
            }

            $item = \App\Models\LiveSheetItem::find($itemId);
            if (!$item) {
                $skipped++;
                continue;
            }

            $details             = $item->product_details ?? [];
            $details['sap_code'] = $sapCode;
            $item->update(['product_details' => $details]);

            // Sync to products master table
            if ($item->product) {
                $item->product->update(['sap_code' => $sapCode]);
            }

            $updated++;
        }

        \App\Models\ActivityLog::log(
            'updated',
            'live_sheet',
            $liveSheet,
            null,
            ['sap_codes_uploaded' => $updated, 'skipped' => $skipped],
            'SAP codes updated via Excel upload by Finance'
        );

        $msg = "{$updated} SAP code(s) updated from uploaded file.";
        if ($skipped > 0) {
            $msg .= " {$skipped} row(s) skipped (blank or invalid).";
        }

        $flashKey = count($errors) > 0 ? 'warning' : 'success';

        return back()
            ->with($flashKey, $msg)
            ->with('upload_errors', $errors);
    }

}
