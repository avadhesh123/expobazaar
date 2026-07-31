<?php

namespace App\Http\Controllers\Sourcing;

use App\Http\Controllers\Controller;
use App\Models\{Vendor, OfferSheet, OfferSheetItem, Consignment, LiveSheet, LiveSheetItem, Product};
use App\Services\{DashboardService, VendorService, SourcingService};
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use App\Helpers\FileStorage;

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
            'company_code'   => 'required|in:2000,2100,2200,2400',
        ], [
            'email.unique' => 'This email is already registered in the system. Please use a different email address.',
        ]);
        $this->vendorService->createVendorRequest($request->all(), auth()->user());
        return redirect()->route('sourcing.dashboard')->with('success', 'Vendor request submitted to admin.');
    }

    public function vendors(Request $request)
    {
        $user = auth()->user();
        // User's allowed company codes
        $userCompanyCodes = $user->company_codes ?? [];
        if (is_string($userCompanyCodes)) {
            $userCompanyCodes = json_decode($userCompanyCodes, true) ?? [];
        }
        $userCompanyCodes = array_filter(array_map('strval', $userCompanyCodes));

        $vendors = Vendor::with('user')
            ->when(!$user->isAdmin() && !empty($userCompanyCodes), fn ($q) => $q->whereIn('company_code', $userCompanyCodes))
            ->when($request->company_code, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            //     ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
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
        $activeCompany = session('active_company');

        $sheets = OfferSheet::with('vendor', 'items')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->where('company_code', $activeCompany)
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
        $activeCompany = session('active_company');

        $liveSheets = LiveSheet::with('vendor', 'offerSheet', 'consignment', 'items.product')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->where('company_code', $activeCompany)
            ->latest()->paginate(20);
        return view('sourcing.live-sheets.index', compact('liveSheets'));
    }

    /**
     * View live sheet detail
     */
    public function showLiveSheet(LiveSheet $liveSheet)
    {
        $liveSheet->load('vendor', 'offerSheet', 'consignment', 'items.product');

        // Get thumbnails from offer_sheet_items by product_id
        $productIds = $liveSheet->items->pluck('product_id')->filter();
        $offerThumbnails = \App\Models\OfferSheetItem::whereIn('product_id', $productIds)
            ->whereNotNull('thumbnail')
            ->pluck('thumbnail', 'product_id');

        // $liveSheet->load('vendor', 'offerSheet', 'consignment', 'items.product');
        return view('sourcing.live-sheets.show', compact('liveSheet', 'offerThumbnails'));
    }

    /**
     * Approve live sheet — locks it
     * After approval, shows "Create Consignment" button
     */
    public function approveLiveSheet(LiveSheet $liveSheet)
    {
        try {
            // Step 1: Validate at least one item is selected
            $selectedCount = $liveSheet->items()->where('is_selected', 1)->count();
            if ($selectedCount === 0) {
                return back()->with('error', 'Cannot approve: No items are selected. Please select at least one item before approving.');
            }

            // Step 2: Validate WSP for all selected items
            $selectedItems = $liveSheet->items()->where('is_selected', 1)->get();
            $missingWsp = [];
            foreach ($selectedItems as $item) {
                $d = $item->product_details ?? [];
                file_put_contents('storage/logs/sourcing.approve.livesheet.log', "Debug: Item ID {$item->id}, Product ID {$item->product_id}, Details: " . json_encode($d) . "\n", FILE_APPEND);

                $finalFob = (float)($d['final_fob'] ?? $item->unit_price);
                $dutyPercent = (float)($d['duty_percent'] ?? 0);
                $freightFactor = (float)($d['freight_factor'] ?? 0);
                $wspFactor = (float)($d['wsp_factor'] ?? 0);

                $dutyAmt = $finalFob * ($dutyPercent / 100);
                $freightAmt = $freightFactor * $finalFob;
                $landedCost = $finalFob + $dutyAmt + $freightAmt;
                $wsp = $landedCost * $wspFactor;

                // WSP can be directly set or calculated from landed_cost × wsp_factor
                if ($wsp <= 0 || $wspFactor <= 0 || $landedCost <= 0) {
                    $sku = $item->product->sku ?? "Item #{$item->id}";
                    $missingWsp[] = $sku;
                }
            }

            if (!empty($missingWsp)) {
                $skuList = implode(', ', array_slice($missingWsp, 0, 10));
                $more = count($missingWsp) > 10 ? ' and ' . (count($missingWsp) - 10) . ' more' : '';
                return back()->with('error', "Cannot approve: WSP is missing or zero for " . count($missingWsp) . " item(s): {$skuList}{$more}. Please set WSP Factor and ensure Landed Cost is calculated for all selected items.");
            }


            // Step 3: Approve and lock the live sheet
            $this->sourcingService->approveLiveSheet($liveSheet, auth()->user());

            return redirect()->route('sourcing.live-sheets')
                ->with('success', 'Live sheet approved and locked. You can now create a Consignment.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error approving live sheet: ' . $e->getMessage());
        }
    }
    /**
     * Sourcing team updates: Target FOB, Final Qty, Final FOB, Freight Factor, WSP Factor, Comments
     */
    public function updateSourcingFields(Request $request, LiveSheet $liveSheet)
    {
        if ($liveSheet->isLocked()) {
            return back()->with('error', 'This Live Sheet is locked. SAP codes and other fields can no longer be modified.');
        }

        $request->validate([
            'items'        => 'required|array',
            'change_reason' => 'nullable|string|max:500',
        ]);

        $service = new \App\Services\LiveSheetService();
        $result = $service->updateItems($liveSheet, $request->items, 'sourcing', $request->change_reason);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }
    public function updateSourcingFields27072026(Request $request, LiveSheet $liveSheet)
    {
        if ($liveSheet->isLocked()) {
            return back()->with('error', 'This Live Sheet is locked. SAP codes and other fields can no longer be modified.');
        }

        $request->validate([
            'items' => 'required|array',
            'change_reason' => 'nullable|string|max:500',
        ]);

        $activeCompany = session('active_company') ?? '2100';

        $updated = 0;
        $totalChanges = 0;
        $reason = $request->change_reason;

        foreach ($request->items as $row) {
            $item = LiveSheetItem::find($row['item_id']);
            if (!$item || $item->live_sheet_id !== $liveSheet->id) {
                continue;
            }

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
            $masterL = $details['master_length'] ?? $details['master_carton_length'] ?? 0;
            $masterW = $details['master_width'] ?? $details['master_carton_width'] ?? 0;
            $masterH = $details['master_height'] ?? $details['master_carton_height'] ?? 0;

            if ($activeCompany === '2100') {
                //USA
                $masterCbm = ($masterL > 0 && $masterW > 0 && $masterH > 0)
                    ? ($masterL * $masterW * $masterH) / 61023
                    : 0;
            } else {
                //EU & UK
                $masterCbm = ($masterL > 0 && $masterW > 0 && $masterH > 0)
                    ? ($masterL * $masterW * $masterH) / 1000000
                    : 0;
            }


            // $masterCbm = ($masterL && $masterW && $masterH) ? ($masterL * $masterW * $masterH) / 61023 : 0;
            $qtyMaster = $details['qty_master_pack'] ?? 1;
            $totalCartons = $row['no_of_master_carton'] ?? ($qtyMaster > 0 ? ceil($finalQty / $qtyMaster) : 0);
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
            // echo ';totalCartons:'.$totalCartons;
            //      echo ';qtyMaster:'  . $qtyMaster ;
            //      echo ';finalQty:'.$finalQty ;
            //      echo ';masterCbm:'.$masterCbm;
            //      echo '<pre>';
            //      print_r($details);
            // exit;
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
    public function createConsignment(LiveSheet $liveSheet, Request $request)
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
            '2200' => 'EU',
            '2400' => 'GB',
            default => 'IN',
        };

        $totalValue = $selectedItems->sum(fn ($i) => floatval($i->total_price));
        \Log::info("Consignment total_value before save: {$totalValue}");

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
            'total_value'        => $totalValue, //$selectedItems->sum('total_price'),
            'created_by'         => auth()->id(),
            'created_at' => $request->custom_date ? \Carbon\Carbon::parse($request->custom_date) : now(),

        ]);

        \Log::info("Consignment total_value after save: {$consignment->total_value}");

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
        $activeCompany = session('active_company');
        $consignments = Consignment::with('vendor', 'liveSheet', 'inspectionReports')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->where('company_code', $activeCompany)
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
        $activeCompany = session('active_company');

        $inspections = \App\Models\InspectionReport::with('consignment.vendor', 'uploader')
            ->when($request->type, fn ($q, $v) => $q->where('inspection_type', $v))
            ->when($request->consignment_id, fn ($q, $v) => $q->where('consignment_id', $v))
            ->when($request->result, fn ($q, $v) => $q->where('result', $v))
            ->when(!empty($activeCompany), function ($q) use ($activeCompany) {
                $q->whereHas('consignment', function ($cq) use ($activeCompany) {
                    $cq->where('company_code', $activeCompany);
                });
            })
            ->latest()->paginate(20);

        $consignments = Consignment::with('vendor')
            ->where('company_code', $activeCompany)
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
        $validated = $request->validate([
            'inspection_type' => 'required|in:inline,midline,final',
            'report_file'     => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx|max:20480',
            'commercial_invoice' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx|max:20480',
            'packing_list'       => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx|max:20480',
            'result'          => 'required|in:passed,failed,conditional',
            'remarks'         => 'nullable|string|max:1000',
        ]);

        $folder = 'inspection-reports/' . $consignment->id;

        $data = [
            'consignment_id'     => $consignment->id,
            'product_id'         => $request->product_id,
            'inspection_type'    => $validated['inspection_type'],
            'report_file'        => $request->file('report_file')->store($folder, FileStorage::disk()),
            'report_name'        => $request->file('report_file')->getClientOriginalName(),
            'result'             => $validated['result'] ?? null,
            'remarks'            => $validated['remarks'] ?? null,
            'findings'          => $request->findings ? json_decode($request->findings, true) : null,
            'uploaded_by'        => auth()->id(),
        ];

        // Commercial Invoice
        if ($request->hasFile('commercial_invoice')) {
            $data['commercial_invoice_file'] = $request->file('commercial_invoice')->store($folder, FileStorage::disk());
            $data['commercial_invoice_name'] = $request->file('commercial_invoice')->getClientOriginalName();
        }

        // Packing List
        if ($request->hasFile('packing_list')) {
            $data['packing_list_file'] = $request->file('packing_list')->store($folder, FileStorage::disk());
            $data['packing_list_name'] = $request->file('packing_list')->getClientOriginalName();
        }

        \App\Models\InspectionReport::create($data);

        \App\Models\ActivityLog::log('uploaded', 'inspection', $consignment, null, [
            'type' => $request->inspection_type,
            'result' => $request->result
        ], ucfirst($request->inspection_type) . ' inspection uploaded');

        return redirect()->route('sourcing.inspections.upload', $consignment)
            ->with('success', 'Inspection report uploaded successfully.');
    }

    public function showInspection(\App\Models\InspectionReport $inspection)
    {
        $inspection->load('consignment.vendor', 'product', 'uploader');
        return view('sourcing.inspections.show', compact('inspection'));
    }

    public function deleteInspection(\App\Models\InspectionReport $inspection)
    {
        $consignment = $inspection->consignment;
        \FileStorage::storage()->delete($inspection->report_file);
        $inspection->delete();
        return redirect()->route('sourcing.inspections.upload', $consignment)->with('success', 'Inspection report deleted.');
    }

    // =====================================================================
    //  CHARGEBACK CONFIRMATION
    // =====================================================================

    public function pendingChargebacks()
    {
        $chargebacks = \App\Models\Chargeback::where('company_code', session('active_company'))
            ->pending()->with('order', 'vendor')->latest()->paginate(20);
        return view('sourcing.chargebacks.index', compact('chargebacks'));
    }

    public function confirmChargeback(Request $request, \App\Models\Chargeback $chargeback)
    {
        $request->validate(['approved' => 'required|boolean']);
        app(\App\Services\FinanceService::class)->confirmChargeback($chargeback, auth()->user(), $request->boolean('approved'), $request->remarks);
        $status = $request->boolean('approved') ? 'confirmed' : 'rejected';
        return back()->with('success', "Chargeback {$status}.");
    }

    /**
     * Download Barcodes Template / Current Data
     */
    public function downloadBarcodes(LiveSheet $liveSheet)
    {
        $liveSheet->load('items.product');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Barcodes');

        // Headers
        $sheet->setCellValue('A1', 'Vendor SKU');
        $sheet->setCellValue('B1', 'Barcode');
        $sheet->setCellValue('C1', 'Product Name');
        $sheet->setCellValue('D1', 'Weight (Kg)');
        $sheet->setCellValue('E1', 'HSN/HTS');

        $row = 2;
        foreach ($liveSheet->items as $item) {
            $d = $item->product_details ?? [];
            $sheet->setCellValue('A' . $row, $item->product->sku ?? '');
            $sheet->setCellValue('B' . $row, $d['barcode'] ?? '');
            $sheet->setCellValue('C' . $row, $item->product->name ?? '');
            $sheet->setCellValue('D' . $row, $d['weight_grams'] ?? '');
            $sheet->setCellValue('E' . $row, $d['hsn_hts_code'] ?? '');
            $row++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'Barcodes_LiveSheet_' . $liveSheet->live_sheet_number . '_' . date('Ymd') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        $writer->save('php://output');
        exit;
    }

    /**
     * Upload and Update Barcodes
     */
    public function uploadBarcodes(Request $request, LiveSheet $liveSheet)
    {
        $request->validate([
            'barcode_file' => 'required|file|mimes:csv,xlsx,xls|max:5120',
        ]);


        $file = $request->file('barcode_file');
        $filePath = $file->getPathname();
        $extension = strtolower($file->getClientOriginalExtension());


        // Get current user name (safe filename format)
        $userName = strtolower(str_replace(
            [' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'],
            '_',
            auth()->user()->name ?? 'unknown_user'
        ));

        // Store file in public/barcodes directory
        //  $storedPath = $file->storeAs('barcodes', $filename, 'public');

        $path = $file->store('barcodes/' . $userName, FileStorage::disk());

        try {
            if ($extension === 'csv') {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');
            } else {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            }

            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();

            $updated = 0;
            $errors = [];

            foreach ($data as $idx => $row) {

                if ($idx === 0) {
                    continue;
                } // Skip header

                $sku     = trim($row[0] ?? '');   // Column A - SKU
                $barcode = trim($row[1] ?? '');   // Column B - Barcode

                if (empty($sku)) {
                    $errors[] = "Row " . ($idx) . ": SKU is empty in this live sheet.";
                    continue;
                }
                if (empty($barcode)) {
                    $errors[] = "Row " . ($idx) . ": BARCODE is empty in this live sheet.";
                }

                // Find matching live sheet item by SKU
                $item = $liveSheet->items()->whereHas('product', fn ($q) => $q->where('sku', $sku))->first();

                if (!$item) {
                    $errors[] = "Row " . ($idx) . ": SKU '{$sku}' not found in this live sheet.";
                    continue;
                }
                // ── Barcode validation ──
                if (!empty($barcode)) {
                    // Check uniqueness against other products (exclude current product)
                    $dupBarcode = \App\Models\Product::withoutGlobalScopes()->where('barcode', $barcode)
                        ->where('id', '!=', $item->product_id)
                        ->first();
                    if ($dupBarcode) {
                        $errors[] = "Row " . ($idx) . ": Barcode '{$barcode}' already assigned to SKU '{$dupBarcode->sku}'.";
                        continue;
                    }
                }

                if ($item) {
                    $details = $item->product_details ?? [];
                    $details['barcode'] = $barcode;

                    $item->update(['product_details' => $details]);

                    if ($item->product) {
                        $item->product->update(['barcode' => $barcode]);
                    }
                    $updated++;
                }
            }
            $msg = "{$updated} item(s) updated from uploaded file.";
            if (count($errors) > 0) {
                $msg .=  'Error:' . implode(",", $errors);
            }
            return back()->with((count($errors) > 0) ? 'error' : 'success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Error processing file: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════
    // 1. DOWNLOAD OFFER SHEET (Sourcing)
    // ═══════════════════════════════════════════════════════════

    public function downloadOfferSheet(\App\Models\OfferSheet $offerSheet)
    {
        $items = $offerSheet->items()->with(['product' => fn ($q) => $q->withoutGlobalScopes()])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Offer Sheet');

        $activeCompany = session('active_company') ?? '2100';
        $isUS = ($activeCompany === '2100');
        $currency = trim(config('app.active_currency_symbol')); // $, ₹, €
        $lwhUnit = $isUS ? 'Inches' : 'CM';
        $weightUnit = $isUS ? 'LBS' : 'KG';

        // Header
        $headers = [
            'S.No',
            'SKU',
            'Product Name',
            'Image',
            "Length ({$lwhUnit})",
            "Width ({$lwhUnit})",
            "Height ({$lwhUnit})",
            "Weight ({$weightUnit})",
            'Material',
            'Color',
            'Finish',
            'Category',
            'Sub Category',
            "Vendor FOB({$currency})",
            'Selected',
            'Comments'
        ];

        foreach ($headers as $col => $h) {
            $sheet->setCellValue([$col + 1, 1], $h);
        }

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial', 'size' => 10],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle([1, 1, count($headers), 1])->applyFromArray($headerStyle);

        // Data rows
        $row = 2;
        foreach ($items as $idx => $item) {
            $d = $item->product_details ?? [];
            $product = $item->product;

            $sheet->setCellValue([1, $row], $idx + 1);
            $sheet->setCellValue([2, $row], $product->sku ?? $item->product_sku ?? '');
            $sheet->setCellValue([3, $row], $product->name ?? $item->product_name ?? '');
            // Column 4 (Image) — insert image if available
            if ($item->thumbnail || $product->thumbnail) {
                $imgPath = $item->thumbnail ?: $product->thumbnail;
                try {
                    if (\App\Helpers\FileStorage::disk() === 's3') {
                        $tempPath = tempnam(sys_get_temp_dir(), 'img_');
                        file_put_contents($tempPath, \App\Helpers\FileStorage::get($imgPath));
                        $localPath = $tempPath;
                    } else {
                        $localPath = storage_path('app/public/' . $imgPath);
                    }

                    if (file_exists($localPath)) {
                        $drawing = new Drawing();
                        $drawing->setPath($localPath);
                        $drawing->setCoordinates(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4) . $row);
                        $drawing->setWidth(50);
                        $drawing->setHeight(50);
                        $drawing->setOffsetX(5);
                        $drawing->setOffsetY(5);
                        $drawing->setWorksheet($sheet);
                    }
                } catch (\Exception $e) {
                    $sheet->setCellValue([4, $row], 'Image N/A');
                }
            }

            $sheet->setCellValue([5, $row], $d['length'] ?? $product->length ?? '');
            $sheet->setCellValue([6, $row], $d['width'] ?? $product->width ?? '');
            $sheet->setCellValue([7, $row], $d['height'] ?? $product->height ?? '');
            $sheet->setCellValue([8, $row], $d['weight'] ?? $product->weight ?? '');
            $sheet->setCellValue([9, $row], $d['material'] ?? $product->material ?? '');
            $sheet->setCellValue([10, $row], $d['color'] ?? '');
            $sheet->setCellValue([11, $row], $d['finish'] ?? '');
            $sheet->setCellValue([12, $row], $d['category'] ?? '');
            $sheet->setCellValue([13, $row], $d['sub_category'] ?? '');
            $sheet->setCellValue([14, $row], $item->vendor_price ?? $product->vendor_price ?? '');
            $sheet->setCellValue([15, $row], $item->is_selected ? 'Yes' : 'No');
            $sheet->setCellValue([16, $row], $d['comments'] ?? '');

            $sheet->getRowDimension($row)->setRowHeight($item->thumbnail ? 45 : 20);
            $row++;
        }

        // Auto-size columns (except image column)
        foreach (range(1, count($headers)) as $col) {
            if ($col === 4) {
                $sheet->getColumnDimensionByColumn($col)->setWidth(10);
            } else {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }
        }

        $filename = "{$offerSheet->offer_sheet_number}.xlsx";
        $path = storage_path("app/temp/{$filename}");
        @mkdir(dirname($path), 0775, true);

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }


    // ═══════════════════════════════════════════════════════════
    // 2. DOWNLOAD LIVE SHEET WITH IMAGES (Sourcing/Finance)
    // ═══════════════════════════════════════════════════════════

    public function downloadLiveSheet(\App\Models\LiveSheet $liveSheet)
    {
        $items = $liveSheet->items()->with(['product' => fn ($q) => $q->withoutGlobalScopes()])->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Live Sheet');

        $activeCompany = session('active_company') ?? '2100';
        $isUS = ($activeCompany === '2100');
        $currency = trim(config('app.active_currency_symbol')); // $, ₹, €
        $lwhUnit = $isUS ? 'Inches' : 'CM';
        $weightUnit = $isUS ? 'LBS' : 'KG';

        $headers = [
            'S.No',
            'SKU',
            'SAP Code',
            'Barcode',
            'Product Name',
            'Image',
            'Description',
            'HSN/HTS',
            'Qty',
            "Vendor FOB({$currency})",
            "Vendor WSP({$currency})",
            "Total Price({$currency})",
            "Length ({$lwhUnit})",
            "Width ({$lwhUnit})",
            "Height ({$lwhUnit})",
            "Weight ({$weightUnit})",
            'Material',
            'Color',
            'Finish',
            'Category',
            'Qty/Inner Pack',
            'Inner Carton Length',
            'Inner Carton Width',
            'Inner Carton Height',
            'Inner Carton Weight',
            'Qty/Master Pack',
            'Master Carton Length',
            'Master Carton Width',
            'Master Carton Height',
            'Master Carton Weight',
            'CBM/Unit',
            'Total CBM'
        ];

        foreach ($headers as $col => $h) {
            $sheet->setCellValue([$col + 1, 1], $h);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial', 'size' => 9],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        ];
        $sheet->getStyle([1, 1, count($headers), 1])->applyFromArray($headerStyle);

        $row = 2;
        foreach ($items as $idx => $item) {
            $d = $item->product_details ?? [];
            $product = $item->product;

            $sheet->setCellValue([1, $row], $idx + 1);
            $sheet->setCellValue([2, $row], $product->sku ?? '');
            $sheet->setCellValue([3, $row], $d['sap_code'] ?? $product->sap_code ?? '');
            $sheet->setCellValue([4, $row], $d['barcode'] ?? $product->barcode ?? '');
            $sheet->setCellValue([5, $row], $product->name ?? '');

            // Column 6 — Image
            $imgPath = $product->thumbnail ?? null;
            if ($imgPath) {
                try {
                    if (\App\Helpers\FileStorage::disk() === 's3') {
                        $tempPath = tempnam(sys_get_temp_dir(), 'lsimg_');
                        file_put_contents($tempPath, \App\Helpers\FileStorage::get($imgPath));
                        $localPath = $tempPath;
                    } else {
                        $localPath = storage_path('app/public/' . $imgPath);
                    }

                    if (file_exists($localPath)) {
                        $drawing = new Drawing();
                        $drawing->setPath($localPath);
                        $drawing->setCoordinates(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6) . $row);
                        $drawing->setWidth(45);
                        $drawing->setHeight(45);
                        $drawing->setOffsetX(3);
                        $drawing->setOffsetY(3);
                        $drawing->setWorksheet($sheet);
                    }
                } catch (\Exception $e) {
                    // Skip image
                }
            }

            $sheet->setCellValue([7, $row], $d['description'] ?? $product->description ?? '');
            $sheet->setCellValue([8, $row], $d['hsn_hts_code'] ?? $product->hsn_code ?? '');
            $sheet->setCellValue([9, $row], $item->quantity ?? 0);
            $sheet->setCellValue([10, $row], $d['vendor_fob'] ?? $item->unit_price ?? '');
            $sheet->setCellValue([11, $row], $d['vendor_wsp'] ?? $d['wsp'] ?? '');
            $sheet->setCellValue([12, $row], $item->total_price ?? 0);
            $sheet->setCellValue([13, $row], $d['length'] ?? $product->length ?? '');
            $sheet->setCellValue([14, $row], $d['width'] ?? $product->width ?? '');
            $sheet->setCellValue([15, $row], $d['height'] ?? $product->height ?? '');
            $sheet->setCellValue([16, $row], $d['weight'] ?? $product->weight ?? '');
            $sheet->setCellValue([17, $row], $d['material'] ?? $product->material ?? '');
            $sheet->setCellValue([18, $row], $d['color'] ?? '');
            $sheet->setCellValue([19, $row], $d['finish'] ?? '');
            $sheet->setCellValue([20, $row], $d['category'] ?? '');
            $sheet->setCellValue([21, $row], $d['qty_inner_pack'] ?? '');
            $sheet->setCellValue([22, $row], $d['inner_carton_length'] ?? '');
            $sheet->setCellValue([23, $row], $d['inner_carton_width'] ?? '');
            $sheet->setCellValue([24, $row], $d['inner_carton_height'] ?? '');
            $sheet->setCellValue([25, $row], $d['inner_carton_weight'] ?? '');
            $sheet->setCellValue([26, $row], $d['qty_master_pack'] ?? '');
            $sheet->setCellValue([27, $row], $d['master_carton_length'] ?? '');
            $sheet->setCellValue([28, $row], $d['master_carton_width'] ?? '');
            $sheet->setCellValue([29, $row], $d['master_carton_height'] ?? '');
            $sheet->setCellValue([30, $row], $d['master_carton_weight'] ?? '');
            $sheet->setCellValue([31, $row], $item->cbm_per_unit ?? '');
            $sheet->setCellValue([32, $row], $item->total_cbm ?? '');

            $sheet->getRowDimension($row)->setRowHeight($imgPath ? 40 : 18);
            $row++;
        }

        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setWidth($col === 6 ? 9 : ($col <= 5 ? 14 : 12));
        }

        $filename = "{$liveSheet->live_sheet_number}.xlsx";
        $path = storage_path("app/temp/{$filename}");
        @mkdir(dirname($path), 0775, true);

        (new Xlsx($spreadsheet))->save($path);
        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    // ═══════════════════════════════════════════════════════════
    // 3. LIVE SHEET INLINE UPDATE (Sourcing)
    // ═══════════════════════════════════════════════════════════

    public function updateLiveSheetItems(Request $request, \App\Models\LiveSheet $liveSheet)
    {
        if ($liveSheet->is_locked) {
            return response()->json(['error' => 'Cannot edit locked live sheet.'], 422);
        }

        $request->validate([
            'items'               => 'required|array',
            'items.*.id'          => 'required|exists:live_sheet_items,id',
            'items.*.quantity'    => 'nullable|integer|min:0',
            'items.*.unit_price'  => 'nullable|numeric|min:0',
            'items.*.vendor_wsp'  => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string|max:500',
            'items.*.hsn_hts_code'    => 'nullable|string|max:50',
            'items.*.barcode'     => 'nullable|string|max:100',
            'items.*.length'      => 'nullable|numeric',
            'items.*.width'       => 'nullable|numeric',
            'items.*.height'      => 'nullable|numeric',
            'items.*.weight'      => 'nullable|numeric',
            'items.*.material'    => 'nullable|string|max:200',
            'items.*.color'       => 'nullable|string|max:100',
            'items.*.qty_inner_pack'  => 'nullable|integer|min:0',
            'items.*.qty_master_pack' => 'nullable|integer|min:0',
        ]);

        $updated = 0;
        foreach ($request->items as $data) {
            $item = \App\Models\LiveSheetItem::where('id', $data['id'])
                ->where('live_sheet_id', $liveSheet->id)->first();
            if (!$item) {
                continue;
            }

            $details = $item->product_details ?? [];

            // Update product_details JSON fields
            $detailFields = [
                'vendor_wsp',
                'description',
                'hsn_hts_code',
                'barcode',
                'length',
                'width',
                'height',
                'weight',
                'material',
                'color',
                'qty_inner_pack',
                'qty_master_pack'
            ];
            foreach ($detailFields as $field) {
                $inputKey = $field;//str_replace('hsn_hts_code', 'hsn_code', $field);
                if (isset($data[$inputKey])) {
                    $details[$field] = $data[$inputKey];
                }
            }

            $qty = intval($data['quantity'] ?? $item->quantity);
            $unitPrice = floatval($data['unit_price'] ?? $item->unit_price);

            $item->update([
                'quantity'         => $qty,
                'unit_price'       => $unitPrice,
                'total_price'      => round($qty * $unitPrice, 2),
                'product_details'  => $details,
            ]);
            $updated++;
        }

        return response()->json(['success' => true, 'message' => "{$updated} item(s) updated.", 'details' => $details]);
    }
}
