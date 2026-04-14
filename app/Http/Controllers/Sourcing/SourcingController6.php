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
   /**
     * Sourcing team updates: Target FOB, Final Qty, Final FOB, Freight Factor, WSP Factor, Comments
     */
    public function updateSourcingFields(Request $request, LiveSheet $liveSheet)
    {
        
        $request->validate([
                'items' => 'required|array',
                'change_reason' => 'nullable|string|max:500',
            ]);

        $updated = 0;
        $totalChanges = 0;
        $reason = $request->change_reason;

        foreach ($request->items as $row) {
            $item = LiveSheetItem::find($row['item_id']);
            if (!$item || $item->live_sheet_id !== $liveSheet->id) continue;
 
            $details = $item->product_details ?? [];
            $finalQty = $row['final_qty'] ?? $details['final_qty'] ?? $item->quantity;
            $finalFob = $row['final_fob'] ?? $details['final_fob'] ?? $item->unit_price;
            $freightFactor = $row['freight_factor'] ?? $details['freight_factor'] ?? null;
            $wspFactor = $row['wsp_factor'] ?? $details['wsp_factor'] ?? null;
 
            
            // Build new details for tracking comparison
            $newDetails = [
                'target_fob'     => $row['target_fob'] ?? $details['target_fob'] ?? null,
                'final_qty'      => $finalQty,
                'final_fob'      => $finalFob,
                'freight_factor' => $freightFactor,
                'wsp_factor'     => $wspFactor,
                'comments'       => $row['comments'] ?? $details['comments'] ?? null,
            ];

            // Track changes BEFORE updating
            try {
                $changes = \App\Models\LiveSheetItemChange::trackChanges($item, $newDetails, auth()->user(), 'sourcing', $reason);
                $totalChanges += $changes;
            } catch (\Exception $e) {
                \Log::warning('Change tracking failed: ' . $e->getMessage());
            }

            // Recalculate derived fields
            $masterL = $details['master_length'] ?? 0;
            $masterW = $details['master_width'] ?? 0;
            $masterH = $details['master_height'] ?? 0;
            $masterCbm = ($masterL && $masterW && $masterH) ? ($masterL * $masterW * $masterH) / 61023 : 0;
            $qtyMaster = $details['qty_master_pack'] ?? 1;
            $totalCartons = $qtyMaster > 0 ? ceil($finalQty / $qtyMaster) : 0;
            $cbmShipment = $totalCartons * $masterCbm;
 
            $details['target_fob'] = $row['target_fob'] ?? $details['target_fob'] ?? null;
            $details['final_qty'] = $finalQty;
            $details['final_fob'] = $finalFob;
            $details['freight_factor'] = $freightFactor;
            $details['wsp_factor'] = $wspFactor;
            $details['comments'] = $row['comments'] ?? $details['comments'] ?? null;
            $details['total_master_cartons'] = $totalCartons;
            $details['master_cbm'] = round($masterCbm, 6);
            $details['cbm_shipment'] = round($cbmShipment, 4);
 
            $item->update([
                'quantity'        => $finalQty,
                'unit_price'      => $finalFob ?: $item->unit_price,
                'total_price'     => ($finalFob ?: $item->unit_price) * $finalQty,
                'total_cbm'       => round($cbmShipment, 4),
                'product_details' => $details,
            ]);
 
            $updated++;
        }
 
        $liveSheet->update(['total_cbm' => $liveSheet->items()->sum('total_cbm')]);
        \App\Models\ActivityLog::log('updated', 'live_sheet', $liveSheet, null, ['fields_updated' => $updated], 'Sourcing fields updated');
 
        return back()->with('success', "{$updated} item(s) updated with sourcing data.");
    }
 /**
     * View change history/audit log for a live sheet
     */
    public function liveSheetHistory(LiveSheet $liveSheet)
    {
        
            $liveSheet->load('vendor', 'items.product');

            try {
                $changes = \App\Models\LiveSheetItemChange::where('live_sheet_id', $liveSheet->id)
                    ->with('user', 'product')
                    ->orderByDesc('created_at')
                    ->paginate(50);
            } catch (\Exception $e) {
                $changes = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
            }

            // Group by revision for summary
            $revisions = $changes->getCollection()->groupBy('revision_number')->map(function ($group) {
                $fullRole = $group->first()->changed_by_role;
                $parts = explode(':', $fullRole, 2);
                return [
                    'revision'   => $group->first()->revision_number,
                    'user'       => $group->first()->user,
                    'role'       => $parts[0] ?? $fullRole,
                    'email'      => $parts[1] ?? null,
                    'reason'     => $group->first()->change_reason,
                    'date'       => $group->first()->created_at,
                    'count'      => $group->count(),
                    'fields'     => $group->pluck('field_name')->unique()->values(),
                    'changes'    => $group,
                ];
            })->sortByDesc('revision');

            return view('sourcing.live-sheets.history', compact('liveSheet', 'changes', 'revisions'));

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

        // Only include selected items
        $selectedItems = $liveSheet->items()->where('is_selected', 1)->get();

        if ($selectedItems->isEmpty()) {
            return back()->with('error', 'No items selected. Please select at least one item from the live sheet before creating a consignment.');
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
            'total_items'        => $selectedItems->sum('quantity'),
            'total_cbm'          => $selectedItems->sum('total_cbm'),
            'total_value'        => $selectedItems->sum('total_price'),
            'created_by'         => auth()->id(),
        ]);

        $liveSheet->update(['consignment_id' => $consignment->id]);

        // Only link SELECTED items to the consignment
        $liveSheet->items()->where('is_selected', 1)->update(['consignment_id' => $consignment->id]);

        if ($liveSheet->offerSheet) {
            $liveSheet->offerSheet->update(['status' => 'converted']);
        }

        \App\Models\ActivityLog::log('created', 'consignment', $consignment, null, ['selected_items' => $selectedItems->count()], 'Consignment created with ' . $selectedItems->count() . ' selected items');

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
        $consignments = Consignment::with('vendor', 'liveSheet', 'inspectionReports')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->latest()->paginate(20);
        return view('sourcing.consignments.index', compact('consignments'));
    }

    public function showConsignment(Consignment $consignment)
    {
        $consignment->load('vendor', 'liveSheet.items.product', 'inspectionReports.uploader');
        return view('sourcing.consignments.show', compact('consignment'));
    }

    // =====================================================================
    //  QUALITY INSPECTIONS
    // =====================================================================

    public function inspections(Request $request)
    {
        $inspections = \App\Models\InspectionReport::with('consignment.vendor', 'uploader')
            ->when($request->type, fn ($q, $v) => $q->where('inspection_type', $v))
            ->when($request->result, fn ($q, $v) => $q->where('result', $v))
            ->latest()->paginate(20);

        $consignments = Consignment::with('vendor')
            ->whereIn('status', ['created', 'in_shipment', 'live_sheet_locked'])
            ->latest()->get();

        $stats = [
            'total'    => \App\Models\InspectionReport::count(),
            'inline'   => \App\Models\InspectionReport::where('inspection_type', 'inline')->count(),
            'midline'  => \App\Models\InspectionReport::where('inspection_type', 'midline')->count(),
            'final'    => \App\Models\InspectionReport::where('inspection_type', 'final')->count(),
            'passed'   => \App\Models\InspectionReport::where('result', 'passed')->count(),
            'failed'   => \App\Models\InspectionReport::where('result', 'failed')->count(),
        ];

        return view('sourcing.inspections.index', compact('inspections', 'consignments', 'stats'));
    }

    public function uploadInspection(Consignment $consignment)
    {
        $consignment->load('vendor', 'liveSheet.items.product', 'inspectionReports.uploader');
        return view('sourcing.inspections.upload', compact('consignment'));
    }

    public function storeInspection(Request $request, Consignment $consignment)
    {
        $request->validate([
            'inspection_type' => 'required|in:inline,midline,final',
            'report_file'     => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx|max:20480',
            'result'          => 'required|in:passed,failed,conditional',
            'remarks'         => 'nullable|string|max:1000',
        ]);

        $file = $request->file('report_file');
        $path = $file->store('inspection-reports/' . $consignment->id, 'public');

        \App\Models\InspectionReport::create([
            'consignment_id'  => $consignment->id,
            'product_id'      => $request->product_id,
            'inspection_type' => $request->inspection_type,
            'report_file'     => $path,
            'report_name'     => $file->getClientOriginalName(),
            'result'          => $request->result,
            'remarks'         => $request->remarks,
            'findings'        => $request->findings ? json_decode($request->findings, true) : null,
            'uploaded_by'     => auth()->id(),
        ]);

        \App\Models\ActivityLog::log('uploaded', 'inspection', $consignment, null, [
            'type' => $request->inspection_type, 'result' => $request->result
        ], ucfirst($request->inspection_type) . ' inspection uploaded');

        return redirect()->route('sourcing.inspections.upload', $consignment)
            ->with('success', ucfirst($request->inspection_type) . ' inspection report uploaded — Result: ' . ucfirst($request->result));
    }

    public function showInspection(\App\Models\InspectionReport $inspection)
    {
        $inspection->load('consignment.vendor', 'product', 'uploader');
        return view('sourcing.inspections.show', compact('inspection'));
    }

    public function deleteInspection(\App\Models\InspectionReport $inspection)
    {
        $consignment = $inspection->consignment;
        \Storage::disk('public')->delete($inspection->report_file);
        $inspection->delete();
        return redirect()->route('sourcing.inspections.upload', $consignment)->with('success', 'Inspection report deleted.');
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
