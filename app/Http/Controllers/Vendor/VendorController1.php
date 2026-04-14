<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\{Vendor, Consignment, VendorPayout, Chargeback, VendorDocument, Category};
use App\Services\{DashboardService, VendorService, SourcingService};
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected VendorService $vendorService,
        protected SourcingService $sourcingService
    ) {}

    public function dashboard()
    {
        $vendor = auth()->user()->vendor;
        if (!$vendor) return redirect()->route('vendor.kyc');
        $data = $this->dashboardService->getVendorDashboard($vendor->id);
        return view('vendor.dashboard', compact('data', 'vendor'));
    }

    public function kycForm()
    {
        $vendor = auth()->user()->vendor;
        return view('vendor.kyc', compact('vendor'));
    }

    public function submitKyc(Request $request)
    {
        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'bank_name' => 'required|string',
            'bank_account_number' => 'required|string',
        ]);
        $vendor = auth()->user()->vendor;
        $vendor->update($request->only(['gst_number', 'pan_number', 'bank_name', 'bank_account_number', 'bank_ifsc', 'bank_swift_code']));
        $docs = [];
        foreach ($request->file('documents') as $file) {
            $path = $file->store('vendor-kyc/' . $vendor->id, 'public');
            $docs[] = ['name' => $file->getClientOriginalName(), 'path' => $path, 'type' => $file->getMimeType(), 'size' => $file->getSize()];
        }
        $this->vendorService->submitKyc($vendor, $docs);
        return redirect()->route('vendor.dashboard')->with('success', 'KYC submitted.');
    }

    public function offerSheets()
    {
        $sheets = auth()->user()->vendor->offerSheets()->latest()->paginate(20);
        return view('vendor.offer-sheets.index', compact('sheets'));
    }

    public function storeOfferSheet(Request $request)
    {
        $request->validate(['products' => 'required|array|min:1', 'products.*.name' => 'required|string', 'products.*.price' => 'required|numeric|min:0']);
        $vendor = auth()->user()->vendor;
        $this->sourcingService->createOfferSheet($vendor->id, $vendor->company_code, $request->products);
        return redirect()->route('vendor.offer-sheets')->with('success', 'Offer sheet submitted.');
    }

    public function submitLiveSheet(Request $request, \App\Models\LiveSheet $liveSheet)
    {
        $request->validate(['items' => 'required|array|min:1']);
        $this->sourcingService->submitLiveSheet($liveSheet, $request->items);
        return redirect()->route('vendor.dashboard')->with('success', 'Live sheet submitted.');
    }

    public function consignments()
    {
        $consignments = auth()->user()->vendor->consignments()->with('liveSheet')->latest()->paginate(20);
        return view('vendor.consignments.index', compact('consignments'));
    }

    public function uploadInspection(Request $request, Consignment $consignment)
    {
        $request->validate(['inspection_type' => 'required|in:inline,midline,final', 'report' => 'required|file|max:20480']);
        $path = $request->file('report')->store('inspections/' . $consignment->id, 'public');
        \App\Models\InspectionReport::create([
            'consignment_id' => $consignment->id, 'inspection_type' => $request->inspection_type,
            'report_file' => $path, 'report_name' => $request->file('report')->getClientOriginalName(),
            'result' => $request->result, 'remarks' => $request->remarks, 'uploaded_by' => auth()->id(),
        ]);
        return back()->with('success', 'Inspection report uploaded.');
    }

    public function salesReport(Request $request)
    {
        $vendor = auth()->user()->vendor;
        $orders = \App\Models\Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))
            ->with('salesChannel')->latest('order_date')->paginate(25);
        return view('vendor.sales.index', compact('orders', 'vendor'));
    }

    public function payouts()
    {
        $payouts = VendorPayout::where('vendor_id', auth()->user()->vendor->id)->orderByDesc('payout_year')->orderByDesc('payout_month')->paginate(12);
        return view('vendor.payouts.index', compact('payouts'));
    }

    public function uploadInvoice(Request $request, VendorPayout $payout)
    {
        $request->validate(['invoice' => 'required|file|mimes:pdf|max:10240']);
        $path = $request->file('invoice')->store('vendor-invoices/' . $payout->vendor_id, 'public');
        $payout->update(['vendor_invoice_file' => $path, 'status' => 'invoice_received']);
        return back()->with('success', 'Invoice uploaded.');
    }
}
