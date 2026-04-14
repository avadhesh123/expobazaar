<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\{Vendor, Consignment, VendorPayout, Chargeback, VendorDocument, Category, LiveSheet, OfferSheet, Order, WarehouseCharge};
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

        $data['stats'] = [
            'offer_sheets'  => OfferSheet::where('vendor_id', $vendor->id)->count(),
            'consignments'  => Consignment::where('vendor_id', $vendor->id)->count(),
            'total_sales'   => Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))->sum('total_amount'),
            'chargebacks'   => Chargeback::where('vendor_id', $vendor->id)->where('status', 'confirmed')->sum('amount'),
            'pending_payout' => VendorPayout::where('vendor_id', $vendor->id)->whereIn('status', ['calculated', 'approved'])->sum('net_payout'),
        ];

        $data['recent_orders'] = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))
            ->with('salesChannel')->latest('order_date')->take(5)->get();

        $data['active_consignments'] = Consignment::where('vendor_id', $vendor->id)
            ->whereNotIn('status', ['delivered', 'cancelled'])->with('liveSheet')->latest()->take(5)->get();

        return view('vendor.dashboard', compact('data', 'vendor'));
    }

    // =====================================================================
    //  KYC
    // =====================================================================

    public function kycForm()
    {
        $vendor = auth()->user()->vendor;
        $documents = $vendor ? $vendor->documents : collect();
        return view('vendor.kyc', compact('vendor', 'documents'));
    }

    public function submitKyc(Request $request)
    {
        $request->validate([
            'documents'            => 'required|array|min:1',
            'documents.*'          => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'bank_name'            => 'required|string',
            'bank_account_number'  => 'required|string',
            'bank_ifsc'            => 'nullable|string',
            'bank_swift_code'      => 'nullable|string',
            'gst_number'           => 'nullable|string',
            'pan_number'           => 'nullable|string',
        ]);

        $vendor = auth()->user()->vendor;
        $vendor->update($request->only(['gst_number', 'pan_number', 'bank_name', 'bank_account_number', 'bank_ifsc', 'bank_swift_code']));

        $docs = [];
        foreach ($request->file('documents') as $file) {
            $path = $file->store('vendor-kyc/' . $vendor->id, 'public');
            $docs[] = ['name' => $file->getClientOriginalName(), 'path' => $path, 'type' => $file->getMimeType(), 'size' => $file->getSize()];
        }
        $this->vendorService->submitKyc($vendor, $docs);
        return redirect()->route('vendor.dashboard')->with('success', 'KYC documents submitted for Finance review.');
    }

    // =====================================================================
    //  OFFER SHEETS
    // =====================================================================

    public function offerSheets()
    {
        $vendor = auth()->user()->vendor;
        $sheets = $vendor->offerSheets()->with('items')->latest()->paginate(20);
        return view('vendor.offer-sheets.index', compact('sheets', 'vendor'));
    }

    public function createOfferSheet()
    {
        $categories = Category::whereNull('parent_id')->orderBy('name')->get();
        return view('vendor.offer-sheets.create', compact('categories'));
    }

    public function storeOfferSheet(Request $request)
    {
        $request->validate([
            'products'              => 'required|array|min:1',
            'products.*.name'       => 'required|string|max:255',
            'products.*.price'      => 'required|numeric|min:0',
            'products.*.category_id' => 'nullable|exists:categories,id',
            'products.*.thumbnail'  => 'nullable|image|max:5120',
        ]);

        $vendor = auth()->user()->vendor;
        $products = $request->products;

        // Handle thumbnail uploads
        foreach ($products as $idx => &$product) {
            if ($request->hasFile("products.{$idx}.thumbnail")) {
                $product['thumbnail'] = $request->file("products.{$idx}.thumbnail")->store('offer-thumbnails/' . $vendor->id, 'public');
            }
        }

        $this->sourcingService->createOfferSheet($vendor->id, $vendor->company_code, $products);
        return redirect()->route('vendor.offer-sheets')->with('success', 'Offer sheet submitted for Sourcing team review.');
    }

    // =====================================================================
    //  CONSIGNMENTS & LIVE SHEETS
    // =====================================================================

    public function consignments()
    {
        $vendor = auth()->user()->vendor;
        $consignments = $vendor->consignments()->with('liveSheet', 'grn', 'shipment')->latest()->paginate(20);
        return view('vendor.consignments.index', compact('consignments', 'vendor'));
    }

    public function liveSheets()
    {
        $vendor = auth()->user()->vendor;
        $liveSheets = LiveSheet::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))
            ->with('consignment', 'items.product')->latest()->paginate(20);
        return view('vendor.live-sheets.index', compact('liveSheets', 'vendor'));
    }

    public function editLiveSheet(LiveSheet $liveSheet)
    {
        $liveSheet->load('consignment.vendor', 'items.product');
        $vendor = auth()->user()->vendor;
        if ($liveSheet->consignment->vendor_id !== $vendor->id) abort(403);
        if ($liveSheet->is_locked) return back()->with('error', 'Live sheet is locked. Contact admin to unlock.');
        return view('vendor.live-sheets.edit', compact('liveSheet', 'vendor'));
    }

    public function submitLiveSheet(Request $request, LiveSheet $liveSheet)
    {
        $request->validate([
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.quantity'         => 'required|integer|min:1',
            'items.*.unit_price'       => 'required|numeric|min:0',
            'items.*.cbm_per_unit'     => 'required|numeric|min:0',
            'items.*.weight_per_unit'  => 'nullable|numeric|min:0',
        ]);

        $this->sourcingService->submitLiveSheet($liveSheet, $request->items);
        return redirect()->route('vendor.consignments')->with('success', 'Live sheet submitted for Sourcing approval.');
    }

    public function uploadInspection(Request $request, Consignment $consignment)
    {
        $request->validate([
            'inspection_type' => 'required|in:inline,midline,final',
            'report'          => 'required|file|max:20480',
        ]);
        $path = $request->file('report')->store('inspections/' . $consignment->id, 'public');
        \App\Models\InspectionReport::create([
            'consignment_id' => $consignment->id,
            'inspection_type' => $request->inspection_type,
            'report_file' => $path,
            'report_name' => $request->file('report')->getClientOriginalName(),
            'result' => $request->result,
            'remarks' => $request->remarks,
            'uploaded_by' => auth()->id(),
        ]);
        return back()->with('success', 'Inspection report uploaded.');
    }

    // =====================================================================
    //  SALES & CHARGEBACKS
    // =====================================================================

    public function salesReport(Request $request)
    {
        $vendor = auth()->user()->vendor;
        $orders = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))
            ->with('salesChannel', 'items')
            ->when($request->month, fn($q, $v) => $q->whereMonth('order_date', $v))
            ->when($request->year, fn($q, $v) => $q->whereYear('order_date', $v))
            ->latest('order_date')->paginate(25);

        $totalSales = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))->sum('total_amount');
        return view('vendor.sales.index', compact('orders', 'vendor', 'totalSales'));
    }

    public function chargebacks()
    {
        $vendor = auth()->user()->vendor;
        $chargebacks = Chargeback::where('vendor_id', $vendor->id)
            ->with('order.salesChannel')->latest()->paginate(20);
        return view('vendor.chargebacks.index', compact('chargebacks', 'vendor'));
    }

    // =====================================================================
    //  PAYOUTS & INVOICES
    // =====================================================================

    public function payouts()
    {
        $vendor = auth()->user()->vendor;
        $payouts = VendorPayout::where('vendor_id', $vendor->id)
            ->orderByDesc('payout_year')->orderByDesc('payout_month')->paginate(12);

        $warehouseCharges = WarehouseCharge::where('vendor_id', $vendor->id)
            ->latest()->take(10)->get();

        return view('vendor.payouts.index', compact('payouts', 'vendor', 'warehouseCharges'));
    }

    public function uploadInvoice(Request $request, VendorPayout $payout)
    {
        $request->validate([
            'invoice'               => 'required|file|mimes:pdf|max:10240',
            'vendor_invoice_number' => 'required|string|max:100',
        ]);
        $path = $request->file('invoice')->store('vendor-invoices/' . $payout->vendor_id, 'public');
        $payout->update([
            'vendor_invoice_file' => $path,
            'vendor_invoice_number' => $request->vendor_invoice_number,
            'vendor_invoice_date' => now(),
            'status' => 'invoice_received',
        ]);
        return back()->with('success', 'Invoice uploaded successfully. Invoice #: ' . $request->vendor_invoice_number);
    }
}
