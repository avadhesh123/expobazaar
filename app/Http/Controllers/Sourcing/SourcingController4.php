<?php

namespace App\Http\Controllers\Sourcing;

use App\Http\Controllers\Controller;
use App\Models\{Vendor, OfferSheet, OfferSheetItem, Consignment, LiveSheet, LiveSheetItem, Product};
use App\Services\{DashboardService, VendorService, SourcingService};
use Illuminate\Http\Request;

class SourcingController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected VendorService $vendorService,
        protected SourcingService $sourcingService
    ) {
    }

    public function dashboard()
    {
        $data = $this->dashboardService->getSourcingDashboard();
        return view('sourcing.dashboard', compact('data'));
    }

    // =====================================================================
    //  VENDOR ONBOARDING
    // =====================================================================

    public function createVendor()
    {
        return view('sourcing.vendors.create');
    }

    public function storeVendor(Request $request)
    {
        $request->validate([
            'company_name'   => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'company_code'   => 'required|in:2000,2100,2200',
        ]);
        $this->vendorService->createVendorRequest($request->all(), auth()->user());
        return redirect()->route('sourcing.dashboard')->with('success', 'Vendor request submitted to admin.');
    }

    public function vendors(Request $request)
    {
        $vendors = Vendor::with('user')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->latest()->paginate(25);
        return view('sourcing.vendors.index', compact('vendors'));
    }

    public function showVendor(Vendor $vendor)
    {
        $vendor->load('user', 'documents', 'creator');
        return view('sourcing.vendors.show', compact('vendor'));
    }
    // =====================================================================
    //  STEP 1: OFFER SHEET REVIEW & PRODUCT SELECTION
    // =====================================================================

    public function offerSheets(Request $request)
    {
        $sheets = OfferSheet::with('vendor', 'items')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()->paginate(20);
        return view('sourcing.offer-sheets.index', compact('sheets'));
    }

    /**
     * Review offer sheet — checkbox selection of products
     */
    public function reviewOfferSheet(OfferSheet $offerSheet)
    {
        $offerSheet->load('items.product', 'items.category', 'vendor');
        return view('sourcing.offer-sheets.review', compact('offerSheet'));
    }

    /**
     * Submit product selection — marks selected items
     * After selection, shows "Create Live Sheet" button
     */
    public function selectProducts(Request $request, OfferSheet $offerSheet)
    {
        $request->validate(['selected_items' => 'required|array|min:1']);
        $this->sourcingService->selectProducts($offerSheet, $request->selected_items, auth()->user());
        return redirect()->route('sourcing.offer-sheets')
            ->with('success', 'Products selected. You can now create a Live Sheet for this offer sheet.');
    }

    // =====================================================================
    //  STEP 2: CREATE LIVE SHEET (from selected offer sheet products)
    // =====================================================================

    /**
     * Create live sheet from selected offer sheet items
     * This is the step between selection and consignment
     */
    public function createLiveSheet(OfferSheet $offerSheet)
    {
        if ($offerSheet->status !== 'selection_done') {
            return back()->with('error', 'Products must be selected first before creating a live sheet.');
        }

        // Check if live sheet already exists for this offer sheet
        $existing = LiveSheet::where('offer_sheet_id', $offerSheet->id)->first();
        if ($existing) {
            return redirect()->route('sourcing.live-sheets.show', $existing)
                ->with('info', 'Live sheet already exists for this offer sheet.');
        }

        // Create live sheet with selected products
        $liveSheet = LiveSheet::create([
            'offer_sheet_id'    => $offerSheet->id,
            'vendor_id'         => $offerSheet->vendor_id,
            'company_code'      => $offerSheet->company_code,
            'live_sheet_number' => LiveSheet::generateNumber($offerSheet->offer_sheet_number),
            'status'            => 'draft',
            'total_cbm'         => 0,
        ]);

        // Pre-populate with selected items from offer sheet
        foreach ($offerSheet->selectedItems as $item) {
            // Ensure product exists
            $product = $item->product_id ? Product::find($item->product_id) : null;
            if (!$product) {
                $product = Product::create([
                    'sku'          => Product::generateSku($offerSheet->company_code, $item->category_id ?? 0),
                    'name'         => $item->product_name,
                    'category_id'  => $item->category_id,
                    'vendor_id'    => $offerSheet->vendor_id,
                    'company_code' => $offerSheet->company_code,
                    'vendor_price' => $item->vendor_price,
                    'currency'     => $item->currency,
                    'thumbnail'    => $item->thumbnail,
                    'status'       => 'selected',
                ]);
                $item->update(['product_id' => $product->id]);
            }

            $d = $item->product_details ?? [];
            LiveSheetItem::create([
                'live_sheet_id'   => $liveSheet->id,
                'product_id'      => $product->id,
                'quantity'        => 1,
                'unit_price'      => $item->vendor_price ?? 0,
                'total_price'     => $item->vendor_price ?? 0,
                'cbm_per_unit'    => 0,
                'total_cbm'       => 0,
                'weight_per_unit' => isset($d['weight_grams']) ? round($d['weight_grams'] / 1000, 2) : 0,
                'total_weight'    => isset($d['weight_grams']) ? round($d['weight_grams'] / 1000, 2) : 0,
                'product_details' => $d,
            ]);
        }

        $offerSheet->update(['status' => 'live_sheet_created']);

        \App\Models\ActivityLog::log('created', 'live_sheet', $liveSheet, null, null, 'Live sheet created from offer sheet');

        // Notify vendor to fill live sheet details
        $offerSheet->vendor->user->notify(new \App\Notifications\LiveSheetNotification($liveSheet, 'fill_required'));

        return redirect()->route('sourcing.live-sheets.show', $liveSheet)
            ->with('success', 'Live sheet created with ' . $offerSheet->selected_products . ' products. Vendor notified to fill details.');
    }

    // =====================================================================
    //  STEP 3: LIVE SHEET REVIEW & APPROVAL
    // =====================================================================

    public function liveSheets(Request $request)
    {
        $liveSheets = LiveSheet::with('vendor', 'offerSheet', 'consignment', 'items.product')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()->paginate(20);
        return view('sourcing.live-sheets.index', compact('liveSheets'));
    }

    /**
     * View live sheet detail
     */
    public function showLiveSheet(LiveSheet $liveSheet)
    {
        $liveSheet->load('vendor', 'offerSheet', 'consignment', 'items.product');
        return view('sourcing.live-sheets.show', compact('liveSheet'));
    }

    /**
     * Approve live sheet — locks it
     * After approval, shows "Create Consignment" button
     */
    public function approveLiveSheet(LiveSheet $liveSheet)
    {
        $this->sourcingService->approveLiveSheet($liveSheet, auth()->user());
        return redirect()->route('sourcing.live-sheets')
            ->with('success', 'Live sheet approved and locked. You can now create a Consignment.');
    }

    // =====================================================================
    //  STEP 4: CREATE CONSIGNMENT (from approved/locked live sheet)
    // =====================================================================

    /**
     * Create consignment from an approved live sheet
     */
    public function createConsignment(LiveSheet $liveSheet)
    {
        if (!$liveSheet->is_locked) {
            return back()->with('error', 'Live sheet must be approved and locked before creating a consignment.');
        }

        if ($liveSheet->consignment) {
            return redirect()->route('sourcing.consignments')
                ->with('info', 'Consignment already exists for this live sheet.');
        }

        $country = match ($liveSheet->company_code) {
            '2100' => 'US',
            '2200' => 'NL',
            default => 'IN',
        };

        $consignment = Consignment::create([
            'consignment_number' => Consignment::generateNumber($liveSheet->company_code, $country),
            'vendor_id'          => $liveSheet->vendor_id,
            'live_sheet_id'      => $liveSheet->id,
            'offer_sheet_id'     => $liveSheet->offer_sheet_id,
            'company_code'       => $liveSheet->company_code,
            'destination_country' => $country,
            'status'             => 'created',
            'total_items'        => $liveSheet->items->sum('quantity'),
            'total_cbm'          => $liveSheet->total_cbm,
            'total_value'        => $liveSheet->items->sum('total_price'),
            'created_by'         => auth()->id(),
        ]);

        // Link live sheet to consignment
        $liveSheet->update(['consignment_id' => $consignment->id]);

        // Update live sheet items with consignment
        $liveSheet->items()->update(['consignment_id' => $consignment->id]);

        // Update offer sheet
        if ($liveSheet->offerSheet) {
            $liveSheet->offerSheet->update(['status' => 'converted']);
        }

        \App\Models\ActivityLog::log('created', 'consignment', $consignment, null, null, 'Consignment created from approved live sheet');

        // Notify logistics
        $logistics = \App\Models\User::internal()->byDepartment('logistics')->active()->get();
        \Illuminate\Support\Facades\Notification::send($logistics, new \App\Notifications\ConsignmentNotification($consignment, 'ready_for_planning'));

        // Notify vendor
        $consignment->vendor->user->notify(new \App\Notifications\ConsignmentNotification($consignment, 'created'));

        $num = $consignment->consignment_number;
        return redirect()->route('sourcing.consignments')
            ->with('success', "Consignment {$num} created. Sent to Logistics for container planning.");
    }

    // =====================================================================
    //  CONSIGNMENTS
    // =====================================================================

    public function consignments(Request $request)
    {
        $consignments = Consignment::with('vendor', 'liveSheet')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->latest()->paginate(20);
        return view('sourcing.consignments.index', compact('consignments'));
    }

    // =====================================================================
    //  CHARGEBACK CONFIRMATION
    // =====================================================================

    public function pendingChargebacks()
    {
        $chargebacks = \App\Models\Chargeback::pending()->with('order', 'vendor')->latest()->paginate(20);
        return view('sourcing.chargebacks.index', compact('chargebacks'));
    }

    public function confirmChargeback(Request $request, \App\Models\Chargeback $chargeback)
    {
        $request->validate(['approved' => 'required|boolean']);
        app(\App\Services\FinanceService::class)->confirmChargeback($chargeback, auth()->user(), $request->boolean('approved'), $request->remarks);
        $status = $request->boolean('approved') ? 'confirmed' : 'rejected';
        return back()->with('success', "Chargeback {$status}.");
    }
}
