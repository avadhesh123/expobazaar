<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\{Vendor, Consignment, VendorPayout, Chargeback, VendorMonthlyCharge, VendorDocument, Category, LiveSheet, OfferSheet, OfferSheetItem, Order, WarehouseCharge, Product, SalesChannel, OrderItem};
use App\Services\{DashboardService, VendorService, SourcingService};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\ActivityLog;
use App\Helpers\ActiveCompany;
use Illuminate\Support\Facades\Storage;
use App\Helpers\FileStorage;

class VendorController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected VendorService $vendorService,
        protected SourcingService $sourcingService
    ) {}

    public function dashboard()
    {
        // phpinfo();
        $vendor = auth()->user()->vendor;
        if (!$vendor) {
            return redirect()->route('vendor.kyc');
        }

        $activeCompany = session('active_company');
        $data = $this->dashboardService->getVendorDashboard($vendor->id);

        // Total sales based on shipped_qty × vendor WSP
        $totalSales = OrderItem::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('shipped_qty', '>', 0)
            ->whereHas('order', fn($q) => $q->withoutGlobalScopes()
                ->where('company_code', $activeCompany)
                ->whereIn('status', ['shipped', 'delivered']))
            ->get()
            ->sum(function ($item) {
                $wsp = floatval($item->product?->vendor_wsp ?? $item->product?->fob_price ?? $item->unit_price ?? 0);
                return round($wsp * intval($item->shipped_qty), 2);
            });

        $totalShippedQty = OrderItem::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('shipped_qty', '>', 0)
            ->whereHas('order', fn($q) => $q->withoutGlobalScopes()
                ->where('company_code', $activeCompany)
                ->whereIn('status', ['shipped', 'delivered']))
            ->sum('shipped_qty');

        // ── Pending payout calculation (gross sales - warehouse charges - chargebacks) ──
        $netPayout = 0;
        $finalPayout = 0;
        $payouts = VendorPayout::where('vendor_id', $vendor->id)
            ->where('company_code', $activeCompany)
            ->whereIn('status', ['calculated', 'approved', 'payment_pending'])
            ->get();

        foreach ($payouts as $payout) {
            $snapshot = $payout->calculation_snapshot;

            // ── Read from saved snapshot ──
            $lineItems = collect($snapshot['line_items'] ?? [])->map(fn($i) => (object) $i);

            $payoutSummary = $snapshot['summary'] ?? [
                'total_qty' => $lineItems->sum('qty'),
                'total_sales' => $lineItems->sum('sale_amount'),
                'total_commission' => $lineItems->sum('commission'),
                'total_payout' => $lineItems->sum('net_payout'),
                'total_warehouse_charges' => $payout->total_warehouse_charges ?? 0,
                'total_chargebacks' => $payout->total_chargebacks ?? 0,
                'net_payout' => $payout->net_payout ?? 0,
            ];



            $warehouseCharges = \App\Models\VendorMonthlyCharge::withoutGlobalScopes()
                ->where('vendor_id', $payout->vendor_id)
                ->where('company_code', $payout->company_code)
                ->where('charge_month', $payout->payout_month)
                ->where('charge_year', $payout->payout_year)
                ->with('warehouse')
                ->get();

            $chargebacks = Chargeback::withoutGlobalScopes()
                ->where('vendor_id', $payout->vendor_id)
                ->whereHas('order', fn($q) => $q->withoutGlobalScopes()->where('company_code', $payout->company_code))
                ->where('status', 'confirmed')
                ->whereMonth('confirmed_at', $payout->payout_month)
                ->whereYear('confirmed_at', $payout->payout_year)
                ->with(['order' => fn($q) => $q->withoutGlobalScopes()])
                ->get();


            $totalPayout = $payoutSummary['total_payout'] ?? 0;
            $totalWhCharges = $warehouseCharges->sum(fn($c) => floatval($c->total_charges ?? $c->amount ?? 0));

            $totalChargebacks = $chargebacks->sum('amount');

            $netPayout += round($totalPayout - $totalWhCharges - $totalChargebacks, 2);
            $finalPayout += $payoutSummary['total_payout'] - $totalWhCharges - $totalChargebacks;
        }

        $data['stats'] = [
            'offer_sheets'   => OfferSheet::where('vendor_id', $vendor->id)->where('company_code', $activeCompany)->count(),
            'consignments'   => Consignment::where('vendor_id', $vendor->id)->where('company_code', $activeCompany)->count(),
            'total_sales'    => $totalSales,
            'total_shipped'  => intval($totalShippedQty),
            'chargebacks'    => Chargeback::where('vendor_id', $vendor->id)->where('company_code', $activeCompany)->where('status', 'confirmed')->sum('amount'),
            'pending_payout' => $finalPayout
            //VendorPayout::where('vendor_id', $vendor->id)->where('company_code', $activeCompany)->whereIn('status', ['calculated', 'approved'])->sum('net_payout'),
        ];

        $data['recent_orders'] = Order::withoutGlobalScopes()
            ->where('company_code', $activeCompany)
            ->whereIn('status', ['shipped', 'delivered'])
            ->whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id)->where('shipped_qty', '>', 0))
            ->with(['salesChannel', 'items' => fn($q) => $q->where('vendor_id', $vendor->id)])
            ->latest('order_date')->take(5)->get();

        $data['active_consignments'] = Consignment::where('vendor_id', $vendor->id)
            ->where('company_code', $activeCompany)
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->with('liveSheet')->latest()->take(5)->get();

        //  print_r($data['recent_orders']->toArray());exit;
        // Deduct prior sold using shipped_qty
        $priorSold = \App\Models\OrderItem::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('shipped_qty', '>', 0)
            ->whereHas('order', fn($q) => $q->withoutGlobalScopes()
                ->where('company_code', $activeCompany)
                ->whereIn('status', ['shipped', 'delivered']))
            ->select('product_id', \DB::raw('SUM(shipped_qty) as shipped'))
            ->groupBy('product_id')
            ->pluck('shipped', 'product_id');

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

        // Build line items per order
        $orderLineItems = [];
        foreach ($data['recent_orders'] as $order) {
            $orderTotal = 0;
            $orderShippedQty = 0;
            foreach ($order->items as $item) {
                $product = $item->product;
                if (!$product) {
                    continue;
                }
                $pid = $product->id;
                $shippedQty = intval($item->shipped_qty);
                if ($shippedQty <= 0) {
                    continue;
                }

                $qtyToAllocate = $shippedQty;
                $itemSale = 0;
                $itemComm = 0;

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
                        $itemSale += $batchSale;
                        $itemComm += round(($batch['commission'] / 100) * $batchSale, 2);
                        $batch['remaining_qty'] -= $allocate;
                        $qtyToAllocate -= $allocate;
                    }
                    unset($batch);
                }

                if ($qtyToAllocate > 0) {
                    $fallbackWsp = floatval($product->vendor_wsp ?? $product->fob_price ?? 0);
                    $itemSale += round($fallbackWsp * $qtyToAllocate, 2);
                }

                $orderTotal += $itemSale;
                $orderShippedQty += $shippedQty;
            }
            $orderLineItems[$order->id] = [
                'sale_amount'  => round($orderTotal, 2),
                'shipped_qty'  => $orderShippedQty,
            ];
        }

        return view('vendor.dashboard', compact('data', 'vendor', 'orderLineItems'));
    }
    public function dashboardBAK()
    {
        $vendor = auth()->user()->vendor;
        if (!$vendor) {
            return redirect()->route('vendor.kyc');
        }

        $activeCompany = session('active_company');

        $data = $this->dashboardService->getVendorDashboard($vendor->id);

        $data['stats'] = [
            'offer_sheets'  => OfferSheet::where('vendor_id', $vendor->id)->where('company_code', $activeCompany)->count(),
            'consignments'  => Consignment::where('vendor_id', $vendor->id)->where('company_code', $activeCompany)->count(),
            'total_sales'   => Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))->where('company_code', $activeCompany)->sum('total_amount'),
            'chargebacks'   => Chargeback::where('vendor_id', $vendor->id)->where('company_code', $activeCompany)->where('status', 'confirmed')->sum('amount'),
            'pending_payout' => VendorPayout::where('vendor_id', $vendor->id)->where('company_code', $activeCompany)->whereIn('status', ['calculated', 'approved'])->sum('net_payout'),
        ];

        $data['recent_orders'] = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))
            ->where('company_code', $activeCompany)
            ->with('salesChannel')->latest('order_date')->take(5)->get();

        $data['active_consignments'] = Consignment::where('vendor_id', $vendor->id)
            ->where('company_code', $activeCompany)
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
        $vendor = auth()->user()->vendor;

        // Block changes after finance approval
        if ($vendor && $vendor->kyc_status === 'approved') {
            return back()->with('error', 'KYC has already been approved by Finance. No changes allowed.');
        }

        $request->validate([
            'gst_number'              => 'required|string|size:15|regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/',
            'rex_number'              => 'nullable|string|size:20|regex:/^[A-Za-z0-9]{20}$/',
            'company_name'            => 'required|string|max:255',
            'address'                 => 'required|string|max:500',
            'street_address'          => 'required|string|max:500',
            'city'                    => 'required|string|max:100',
            'province_state'          => 'required|string|max:100',
            'pincode'                 => 'required|string|max:10',
            'country'                 => 'required|string|max:100',
            'contact_person'          => 'required|string|max:255',
            'phone'                   => 'required|string|max:20',
            'email'                   => 'required|email|max:255',
            'bank_name'               => 'required|string|max:255',
            'bank_ifsc'               => 'nullable|string|max:20',
            'bank_swift_code'         => 'required|string|min:8|max:11',
            'bank_account_number'     => 'required|string|max:50',
            'msme_number'             => 'nullable|string|max:50',
            'documents.gst_certificate'   => $this->docRequired($vendor, 'gst_certificate'),
            'documents.cancelled_cheque'  => $this->docRequired($vendor, 'cancelled_cheque'),
            'documents.signed_contract'   => $this->docRequired($vendor, 'signed_contract'),
            'documents.iec_certificate'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.other.*'           => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'gst_number.regex'                    => 'Invalid GST format. Example: 22AAAAA0000A1Z5 (15 characters).',
            'gst_number.size'                     => 'GST number must be exactly 15 characters.',
            'rex_number.size'                     => 'REX number must be exactly 20 characters.',
            'rex_number.regex'                    => 'REX must contain only letters and digits (20 characters).',
            'bank_swift_code.required'            => 'SWIFT / BIC code is mandatory.',
            'bank_swift_code.min'                 => 'SWIFT code must be at least 8 characters.',
            'documents.cancelled_cheque.required'  => 'Cancelled cheque / bank proof is mandatory.',
            'documents.signed_contract.required'   => 'Signed consignment contract is mandatory.',
            'documents.gst_certificate.required'   => 'GST certificate is mandatory.',
            'address.required'                      => 'Registered address is required.',
            'street_address.required'              => 'Street address is required.',
            'province_state.required'              => 'Province / State is required.',
        ]);

        // Update vendor fields
        try {
            $vendor->update($request->only([
                'gst_number',
                'company_name',
                'address',
                'street_address',
                'city',
                'province_state',
                'pincode',
                'country',
                'contact_person',
                'finance_contact_person',
                'phone',
                'email',
                'iec_code',
                'msme_number',
                'rex_number',
                'landline',
                'official_website',
                'bank_name',
                'bank_ifsc',
                'bank_swift_code',
                'bank_account_number',
            ]));
        } catch (\Exception $e) {
            \Log::error('KYC vendor update failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update vendor info: ' . $e->getMessage())->withInput();
        }

        // Process document uploads
        $docs = [];
        $docTypes = ['gst_certificate', 'cancelled_cheque', 'iec_certificate', 'msme_certificate', 'signed_contract'];
        foreach ($docTypes as $type) {
            if ($request->hasFile("documents.{$type}")) {
                try {
                    $file = $request->file("documents.{$type}");
                    $path = $file->store('vendor-kyc/' . $vendor->id, FileStorage::disk());
                    $docs[] = ['name' => ucfirst(str_replace('_', ' ', $type)), 'path' => $path, 'type' => $file->getMimeType(), 'size' => $file->getSize()];

                    \App\Models\VendorDocument::updateOrCreate(
                        ['vendor_id' => $vendor->id, 'document_type' => $type],
                        ['document_name' => ucfirst(str_replace('_', ' ', $type)), 'file_path' => $path, 'file_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'uploaded_by' => auth()->id(), 'status' => 'uploaded']
                    );
                } catch (\Exception $e) {
                    \Log::error("KYC doc upload failed ({$type}): " . $e->getMessage());
                    return back()->with('error', "Failed to upload {$type}: " . $e->getMessage())->withInput();
                }
            }
        }

        // Handle additional documents
        if ($request->hasFile('documents.other')) {
            foreach ($request->file('documents.other') as $file) {
                try {
                    $path = $file->store('vendor-kyc/' . $vendor->id, FileStorage::disk());
                    $docs[] = ['name' => $file->getClientOriginalName(), 'path' => $path, 'type' => $file->getMimeType(), 'size' => $file->getSize()];

                    \App\Models\VendorDocument::create([
                        'vendor_id' => $vendor->id,
                        'document_type' => 'other',
                        'document_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => auth()->id(),
                        'status' => 'uploaded',
                    ]);
                } catch (\Exception $e) {
                    \Log::error('KYC other doc upload failed: ' . $e->getMessage());
                }
            }
        }

        // Update KYC status
        try {
            if ($vendor->kyc_status !== 'submitted') {
                $this->vendorService->submitKyc($vendor, $docs);
            }
        } catch (\Exception $e) {
            \Log::error('KYC status update failed: ' . $e->getMessage());
            return back()->with('error', 'KYC submission failed: ' . $e->getMessage())->withInput();
        }

        return redirect()->route('vendor.dashboard')->with('success', 'KYC documents submitted for Finance review.');
    }


    /**
     * Check if document upload is required (required if not already uploaded)
     */
    private function docRequired($vendor, string $docType): string
    {
        if ($vendor && $vendor->documents()->where('document_type', $docType)->exists()) {
            return 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240';
        }
        return 'required|file|mimes:pdf,jpg,jpeg,png|max:10240';
    }

    // =====================================================================
    //  OFFER SHEETS — Excel Upload + Tabular Display + Checkbox Selection
    // =====================================================================

    /**
     * List vendor's offer sheets
     */
    public function offerSheets()
    {
        $user = auth()->user();

        $activeCompany = session('active_company');

        $vendor = $user->vendor;
        $sheets = $vendor->offerSheets()->with('items')
            ->when($activeCompany, function ($q) use ($activeCompany) {
                $q->where('company_code', $activeCompany);
            })
            ->latest()->paginate(20);
        return view('vendor.offer-sheets.index', compact('sheets', 'vendor'));
    }

    /**
     * Show upload form
     */
    public function createOfferSheet()
    {
        $categories = Category::whereNull('parent_id')->orderBy('name')->get();
        return view('vendor.offer-sheets.create', compact('categories'));
    }

    /**
     * Parse uploaded Excel and store offer sheet with all template columns
     */
    public function storeOfferSheet(Request $request)
    {
        $request->validate([
            'offer_file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        $activeCompany = session('active_company');
        $vendor = auth()->user()->vendor;
        $file = $request->file('offer_file');
        $path = $file->store('offer-sheets/' . $vendor->id, FileStorage::disk());

        $products = $this->parseOfferSheetExcel($file->getRealPath());

        if (empty($products)) {
            return back()->with('error', 'No valid products found. Please use the provided template.');
        }

        // ── Step 1: Validate ALL rows first before creating anything ──
        $validatedItems = [];
        $errors = [];

        foreach ($products as $idx => $p) {
            $sku = trim($p['vendor_sku']) ?? '';
            if (empty($sku)) {
                $errors[] = "Row " . ($idx + 1) . ": SKU is empty.";
                continue;
            }

            // Check if SKU belongs to another vendor
            $existingProduct = Product::withoutGlobalScopes()->where('sku', $sku)->first();
            if ($existingProduct && $existingProduct->vendor_id !== $vendor->id) {
                $errors[] = "Row " . ($idx + 1) . ": SKU '{$sku}' already exists under another vendor.";
                continue;
            }

            // Check barcode uniqueness if provided
            $barcode = trim($p['barcode'] ?? '');
            if (!empty($barcode)) {
                $dupBarcode = Product::withoutGlobalScopes()->where('barcode', $barcode)
                    ->when($existingProduct, fn($q) => $q->where('id', '!=', $existingProduct->id))
                    ->first();
                if ($dupBarcode) {
                    $errors[] = "Row " . ($idx + 1) . ": Barcode '{$barcode}' already assigned to SKU '{$dupBarcode->sku}'.";
                    continue;
                }
            }

            $validatedItems[] = $p;
        }

        if (!empty($errors) && empty($validatedItems)) {
            return back()->with('error', 'All rows had errors: ' . implode('; ', array_slice($errors, 0, 5)));
        }

        // ── Step 2: Create everything in a transaction ──
        try {
            \DB::beginTransaction();

            $offerSheet = OfferSheet::create([
                'offer_sheet_number' => OfferSheet::generateNumber($activeCompany ?? $vendor->company_code),
                'vendor_id'          => $vendor->id,
                'company_code'       => $activeCompany ?? $vendor->company_code,
                'status'             => 'submitted',
                'total_products'     => count($validatedItems),
                'selected_products'  => 0,
            ]);

            foreach ($validatedItems as $p) {
                // Find or create category
                $categoryId = null;
                if (!empty($p['category'])) {
                    $cat = Category::firstOrCreate(
                        ['slug' => Str::slug($p['category'])],
                        ['name' => $p['category'], 'sort_order' => 0]
                    );
                    $categoryId = $cat->id;

                    if (!empty($p['sub_category'])) {
                        $subCat = Category::firstOrCreate(
                            ['slug' => Str::slug($p['sub_category'])],
                            ['name' => $p['sub_category'], 'parent_id' => $cat->id, 'sort_order' => 0]
                        );
                        $categoryId = $subCat->id;
                    }
                }

                $thumbnail = $p['image_path'] ?? null;
                $vendorSku = trim($p['vendor_sku'] ?? '');

                // Find or create product
                $product = Product::withoutGlobalScopes()->where('sku', $vendorSku)->where('vendor_id', $vendor->id)->first();
                if (!$product) {
                    $productData = [
                        'sku'          => $vendorSku,
                        'category_id'  => $categoryId,
                        'vendor_id'    => $vendor->id,
                        'name'         => $p['product_name'],
                        'company_code' => $activeCompany ?? $vendor->company_code,
                        'length'       => $p['length'] ?? null,
                        'width'        => $p['width'] ?? null,
                        'height'       => $p['height'] ?? null,
                        'weight'       => $p['weight'] ?? null,
                        'vendor_price' => $p['vendor_fob'] ?? 0,
                        'status'       => 'draft',
                    ];
                    $barcode = trim($p['barcode'] ?? '');
                    if (!empty($barcode)) {
                        $productData['barcode'] = $barcode;
                    }
                    $product = Product::create($productData);

                    \Log::channel('daily')->info("Product #{$product->id} created via offer sheet", $productData);
                } else {
                    // Update existing product with new data
                    $updateData = array_filter([
                        'name'         => $p['product_name'],
                        'category_id'  => $categoryId,
                        'vendor_price' => $p['vendor_fob'] ?? null,
                    ]);
                    $barcode = trim($p['barcode'] ?? '');
                    if (!empty($barcode)) {
                        $updateData['barcode'] = $barcode;
                    }
                    $product->update($updateData);
                    \Log::channel('daily')->info("Product #{$product->id} updated via offer sheet", $updateData);
                }

                OfferSheetItem::create([
                    'offer_sheet_id'  => $offerSheet->id,
                    'product_id'      => $product->id,
                    'product_name'    => $p['product_name'],
                    'product_sku'     => $p['vendor_sku'],
                    'category_id'     => $categoryId,
                    'vendor_price'    => $p['vendor_fob'] ?? 0,
                    'currency'        => $activeCurrencySymbol ?? 'USD',
                    'thumbnail'       => $thumbnail,
                    'product_details' => [
                        'sno'           => $p['sno'] ?? null,
                        'barcode'       => $barcode ?? null,
                        'length'        => $p['length'] ?? null,
                        'width'         => $p['width'] ?? null,
                        'height'        => $p['height'] ?? null,
                        'weight'        => $p['weight'] ?? null,
                        'material'      => $p['material'] ?? null,
                        'color'         => $p['color'] ?? null,
                        'finish'        => $p['finish'] ?? null,
                        'category'      => $p['category'] ?? null,
                        'sub_category'  => $p['sub_category'] ?? null,
                        'comments'      => $p['comments'] ?? null,
                    ],
                    'is_selected' => false,
                ]);
            }

            \DB::commit();

            $msg = "Offer sheet uploaded with " . count($validatedItems) . " products.";
            if (!empty($errors)) {
                $msg .= ' ' . count($errors) . ' row(s) skipped.';
            }

            return redirect()->route('vendor.offer-sheets.show', $offerSheet)
                ->with('success', $msg)
                ->with('upload_errors', $errors);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Offer sheet creation failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Parse Excel file matching the Offer_Sheet-US template columns
     * Extracts both data and embedded images
     */
    protected function parseOfferSheetExcel(string $filePath): array
    {
        $products = [];

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            // Do NOT setReadDataOnly — we need images
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            // Extract embedded images mapped by row
            $imageMap = $this->extractExcelImages($sheet, $filePath);

            $headers = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $val = trim((string) $cell->getValue());
                    $col = $cell->getColumn();
                    if ($val) {
                        $headers[$col] = strtolower(preg_replace('/\s+/', '_', preg_replace('/[^a-zA-Z0-9\s]/', '', $val)));
                    }
                }
            }

            // Map template columns to our fields
            $colMap = [];
            foreach ($headers as $col => $header) {
                if (str_contains($header, 'sno') || $header === 'sno') {
                    $colMap['sno'] = $col;
                } elseif (str_contains($header, 'vendor_sku') || str_contains($header, 'sku')) {
                    $colMap['vendor_sku'] = $col;
                } elseif (str_contains($header, 'product_name') || str_contains($header, 'name')) {
                    $colMap['product_name'] = $col;
                } elseif (str_contains($header, 'product_image') || str_contains($header, 'image')) {
                    $colMap['image'] = $col;
                } elseif (str_contains($header, 'length')) {
                    $colMap['length'] = $col;
                } elseif (str_contains($header, 'width')) {
                    $colMap['width'] = $col;
                } elseif (str_contains($header, 'height')) {
                    $colMap['height'] = $col;
                } elseif (str_contains($header, 'weight')) {
                    $colMap['weight'] = $col;
                } elseif (str_contains($header, 'material')) {
                    $colMap['material'] = $col;
                } elseif (str_contains($header, 'color')) {
                    $colMap['color'] = $col;
                } elseif (str_contains($header, 'finish')) {
                    $colMap['finish'] = $col;
                } elseif (str_contains($header, 'sub_category')) {
                    $colMap['sub_category'] = $col;
                } elseif (str_contains($header, 'category')) {
                    $colMap['category'] = $col;
                } elseif (str_contains($header, 'fob') || str_contains($header, 'vendor_fob')) {
                    $colMap['vendor_fob'] = $col;
                } elseif (str_contains($header, 'comment')) {
                    $colMap['comments'] = $col;
                } elseif (str_contains($header, 'selection')) {
                    $colMap['selection'] = $col;
                }
            }

            // Parse data rows
            $maxRow = $sheet->getHighestRow();

            for ($rowIdx = 2; $rowIdx <= $maxRow; $rowIdx++) {
                $sku = $sheet->getCell(($colMap['vendor_sku'] ?? 'B') . $rowIdx)->getValue();
                $name = $sheet->getCell(($colMap['product_name'] ?? 'C') . $rowIdx)->getValue();

                if (empty($sku) && empty($name)) {
                    continue;
                } // Skip empty rows

                $products[] = [
                    'sno'          => $sheet->getCell(($colMap['sno'] ?? 'A') . $rowIdx)->getValue(),
                    'vendor_sku'   => trim((string) $sku),
                    'product_name' => trim((string) $name),
                    'image_path'   => $imageMap[$rowIdx] ?? null,
                    'length'       => $sheet->getCell(($colMap['length'] ?? 'E') . $rowIdx)->getValue(),
                    'width'        => $sheet->getCell(($colMap['width'] ?? 'F') . $rowIdx)->getValue(),
                    'height'       => $sheet->getCell(($colMap['height'] ?? 'G') . $rowIdx)->getValue(),
                    'weight'       => $sheet->getCell(($colMap['weight'] ?? 'H') . $rowIdx)->getValue(),
                    'material'     => $sheet->getCell(($colMap['material'] ?? 'I') . $rowIdx)->getValue(),
                    'color'        => $sheet->getCell(($colMap['color'] ?? 'J') . $rowIdx)->getValue(),
                    'finish'       => $sheet->getCell(($colMap['finish'] ?? 'K') . $rowIdx)->getValue(),
                    'category'     => $sheet->getCell(($colMap['category'] ?? 'L') . $rowIdx)->getValue(),
                    'sub_category' => $sheet->getCell(($colMap['sub_category'] ?? 'M') . $rowIdx)->getValue(),
                    'vendor_fob'   => $sheet->getCell(($colMap['vendor_fob'] ?? 'N') . $rowIdx)->getValue(),
                    'comments'     => $sheet->getCell(($colMap['comments'] ?? 'O') . $rowIdx)->getValue(),
                ];
            }
            // print_r($products);exit;
            \Log::channel('daily')->info('Products CSV upload data', ['count' => count($products)]);
        } catch (\Exception $e) {
            \Log::error('Offer sheet parse error: ' . $e->getMessage());
        }

        return $products;
    }

    /**
     * Extract embedded images from Excel sheet and save to storage
     * Returns array keyed by row number => saved file path
     */
    /**
     * Extract embedded images from Excel sheet and save to storage.
     * Returns array keyed by spreadsheet row number => saved file path.
     */
    protected function extractExcelImages($sheet, string $excelFilePath = ''): array
    {
        $imageMap = [];
        $vendorId = auth()->user()->vendor->id ?? 0;

        try {
            $drawings = $sheet->getDrawingCollection();

            foreach ($drawings as $drawing) {
                // getCoordinates() returns the top-left anchor cell (e.g. "D2")
                // This is reliable for row detection regardless of image size/offset
                $coordinates = $drawing->getCoordinates();
                preg_match('/([A-Z]+)(\d+)/', $coordinates, $matches);
                $row = (int) ($matches[2] ?? 0);

                if ($row < 2) {
                    continue; // Skip header row images (logos etc.)
                }

                $destDir  = 'offer-thumbnails/' . $vendorId;

                $skuRaw = trim((string) $sheet->getCell('B' . $row)->getValue());
                $cleanSku = preg_replace('/[^A-Za-z0-9\-_]/', '', $skuRaw);

                // Fallback if cleaning removes everything
                if (empty($cleanSku)) {
                    $cleanSku = 'product-row' . $row;
                }

                // $filename = 'offer-img-' . $vendorId . '-row' . $row . '-' . time() . '-' . mt_rand(1000, 9999);
                $filename = 'offer-img-' . $vendorId . '-' . $cleanSku;
                $imagePath = null;

                $logFile = 'logs/ExcelImages' . date('Y-m-d') . '.log';

                //file_put_contents("storage/logs/ExcelImages" . date('Y-m-d') . ".log", "Processing drawing at row {$row} with coordinates {$coordinates} \t {$filename}\n", FILE_APPEND);

                FileStorage::append(
                    $logFile,
                    "Processing drawing at row {$row} with coordinates {$coordinates}\t{$filename}"
                );

                if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                    $sourcePath = $drawing->getPath();
                    $ext        = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'png');
                    $destPath   = $destDir . '/' . $filename . '.' . $ext;
                    $imageData  = null;

                    // Method 1: Direct filesystem reference
                    if (!str_starts_with($sourcePath, 'zip://') && file_exists($sourcePath)) {
                        $imageData = file_get_contents($sourcePath);
                    }

                    // Method 2: zip:// stream wrapper (PhpSpreadsheet's internal path)
                    if (!$imageData && str_starts_with($sourcePath, 'zip://')) {
                        $imageData = @file_get_contents($sourcePath);
                    }

                    // Method 3: Open the xlsx zip directly and locate by basename
                    if (!$imageData && $excelFilePath && file_exists($excelFilePath)) {
                        $zip = new \ZipArchive();
                        if ($zip->open($excelFilePath) === true) {
                            $base = basename($sourcePath);
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $entry = $zip->getNameIndex($i);
                                if (str_contains($entry, 'media/') && basename($entry) === $base) {
                                    $imageData = $zip->getFromIndex($i);
                                    $ext       = strtolower(pathinfo($entry, PATHINFO_EXTENSION) ?: 'png');
                                    $destPath  = $destDir . '/' . $filename . '.' . $ext;
                                    break;
                                }
                            }
                            $zip->close();
                        }
                    }

                    if ($imageData) {
                        FileStorage::put($destPath, $imageData);
                        $imagePath = $destPath;
                    }
                } elseif ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing) {
                    $ext = match ($drawing->getMimeType()) {
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_PNG  => 'png',
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_GIF  => 'gif',
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_JPEG => 'jpg',
                        default                                                           => 'png',
                    };
                    $destPath     = $destDir . '/' . $filename . '.' . $ext;
                    $imageResource = $drawing->getImageResource();

                    if ($imageResource) {
                        ob_start();
                        match ($ext) {
                            'png' => imagepng($imageResource),
                            'gif' => imagegif($imageResource),
                            'jpg' => imagejpeg($imageResource),
                            default => imagepng($imageResource),
                        };
                        $imageData = ob_get_clean();

                        if ($imageData) {
                            FileStorage::put($destPath, $imageData);
                            $imagePath = $destPath;
                        }
                    }
                }

                if ($imagePath) {
                    // If multiple images anchor to the same row, keep the first one
                    if (!isset($imageMap[$row])) {
                        $imageMap[$row] = $imagePath;
                    }
                }
            }

            // Fallback: PhpSpreadsheet returned no drawings — parse zip directly
            // using the drawing XML relationship IDs to correctly pair images with rows
            if (empty($imageMap) && $excelFilePath && file_exists($excelFilePath)) {
                $imageMap = $this->extractImagesFromZip($excelFilePath, $vendorId, $filename);
            }
        } catch (\Exception $e) {
            \Log::warning('Image extraction error: ' . $e->getMessage());
        }

        return $imageMap;
    }

    /**
     * Fallback image extractor: reads drawing1.xml relationships to correctly
     * map each image file to its anchor row. This is accurate for any sheet size.
     */
    protected function extractImagesFromZip(string $excelFilePath, int $vendorId, string $filename): array
    {
        $imageMap = [];

        try {
            $zip = new \ZipArchive();
            if ($zip->open($excelFilePath) !== true) {
                return $imageMap;
            }

            // ── Step 1: Parse drawing relationships (rId → media filename) ──────
            // xl/drawings/_rels/drawing1.xml.rels maps relationship IDs to media files
            $rIdToMedia = [];
            $relsXml = @$zip->getFromName('xl/drawings/_rels/drawing1.xml.rels');
            if ($relsXml) {
                // Each Relationship looks like:
                // <Relationship Id="rId1" Target="../media/image1.png" .../>
                preg_match_all(
                    '/Id="([^"]+)"[^>]+Target="[^"]*media\/([^"]+)"/',
                    $relsXml,
                    $relMatches,
                    PREG_SET_ORDER
                );
                foreach ($relMatches as $m) {
                    $rIdToMedia[$m[1]] = $m[2]; // e.g. 'rId1' => 'image1.png'
                }
            }

            // ── Step 2: Parse drawing1.xml — map each anchor row to its rId ─────
            // Each <xdr:twoCellAnchor> or <xdr:oneCellAnchor> has:
            //   <xdr:from><xdr:row>N</xdr:row>...</xdr:from>
            //   <a:blip r:embed="rId1"/>
            $rowToRId = [];
            $drawingXml = @$zip->getFromName('xl/drawings/drawing1.xml');
            if ($drawingXml) {
                // Match each anchor block containing both row and rId
                preg_match_all(
                    '/<xdr:from>\s*<xdr:col>\d+<\/xdr:col>\s*<xdr:colOff>\d+<\/xdr:colOff>\s*<xdr:row>(\d+)<\/xdr:row>.*?r:embed="([^"]+)"/s',
                    $drawingXml,
                    $anchorMatches,
                    PREG_SET_ORDER
                );
                foreach ($anchorMatches as $m) {
                    $excelRow = (int)$m[1] + 1; // XML rows are 0-indexed; Excel rows are 1-indexed
                    $rId      = $m[2];
                    if ($excelRow >= 2) { // skip header row
                        $rowToRId[$excelRow] = $rId;
                    }
                }
            }

            // ── Step 3: Save each media file and build the row → path map ────────
            $destDir = 'offer-thumbnails/' . $vendorId;

            foreach ($rowToRId as $excelRow => $rId) {
                $mediaFilename = $rIdToMedia[$rId] ?? null;
                if (!$mediaFilename) {
                    continue;
                }

                $imageData = $zip->getFromName('xl/media/' . $mediaFilename);
                if (!$imageData) {
                    continue;
                }

                $ext      = strtolower(pathinfo($mediaFilename, PATHINFO_EXTENSION) ?: 'png');

                //   $destPath = $destDir . '/offer-img-' . $vendorId . '-row' . $excelRow . '-' . time() . '-' . mt_rand(1000, 9999) . '.' . $ext;
                $destPath = $destDir . '/' . $filename . '.' . $ext;
                // file_put_contents("storage/logs/ImagesFromZip" . date('Y-m-d') . ".log", "{$mediaFilename}\t{$destPath}\n", FILE_APPEND);

                FileStorage::put($destPath, $imageData);

                $imageMap[$excelRow] = $destPath;
            }

            // ── Step 4: If drawing XML had no parseable relationships, fall back  ─
            // to saving all media files sequentially (last resort, best-effort)
            if (empty($imageMap)) {
                $mediaFiles = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (str_starts_with($name, 'xl/media/')) {
                        $mediaFiles[] = ['index' => $i, 'name' => $name];
                    }
                }

                // Sort by filename so image1, image2... are in order
                usort($mediaFiles, fn($a, $b) => strnatcmp($a['name'], $b['name']));

                $dataRow = 2;
                foreach ($mediaFiles as $media) {
                    $imageData = $zip->getFromIndex($media['index']);
                    if (!$imageData) {
                        continue;
                    }
                    $ext      = strtolower(pathinfo($media['name'], PATHINFO_EXTENSION) ?: 'png');
                    //  $destPath = $destDir . '/offer-img-' . $vendorId . '-row' . $dataRow . '-' . time() . '-' . mt_rand(1000, 9999) . '.' . $ext;
                    $destPath = $destDir . '/' . $filename . '.' . $ext;
                    FileStorage::put($destPath, $imageData);
                    $imageMap[$dataRow] = $destPath;
                    $dataRow++;
                }
            }

            $zip->close();
        } catch (\Exception $e) {
            \Log::warning('Zip image extraction error: ' . $e->getMessage());
        }

        return $imageMap;
    }


    /**
     * Show offer sheet detail — tabular view with all columns from template
     */
    public function showOfferSheet(OfferSheet $offerSheet)
    {
        $vendor = auth()->user()->vendor;
        if ($offerSheet->vendor_id !== $vendor->id) {
            abort(403);
        }
        $offerSheet->load('items.product', 'items.category');
        return view('vendor.offer-sheets.show', compact('offerSheet', 'vendor'));
    }

    /**
     * Download blank offer sheet template (Excel)
     */
    public function downloadOfferSheetTemplate1()
    {
        $activeCompany = session('active_company') ?? '2100';
        $templateFiles = [
            '2100' => 'Offer-Sheet-US.xlsx',
            '2200' => 'Offer-Sheet-EU.xlsx',
            '2400' => 'Offer-Sheet-UK.xlsx',
        ];
        $fileName = $templateFiles[$activeCompany] ?? 'Offer-Sheet-US.xlsx'; // fallback
        $path = storage_path('app/public/downloads/' . $fileName);

        if (!file_exists($path)) {
            // Fallback: generate CSV template
            $csv = "S.no,Vendor SKU,Product Name,Product Image,Product Length (In Inches),Product Width (In Inches),Product Height (In Inches),Product Weight (In Gram),Material Composition,Color,Product Finish,Category,Sub Category,Vendor FOB,Comments\n";
            $csv .= "1,EB123,Glass Vase,,10,10,2,200,Glass,Clear,Glossy,Home & Décor,Décor,1,\n";

            $fileName = $activeCompany . "_Offer_Sheet_Template.csv";

            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        }
        return response()->download($path, $activeCompany . '_Offer_Sheet_Template.xlsx');
    }
    public function downloadOfferSheetTemplate()
    {
        $activeCompany = session('active_company') ?? '2100';

        $templateFiles = [
            '2100' => 'Offer-Sheet-US.xlsx',
            '2200' => 'Offer-Sheet-EU.xlsx',
            '2400' => 'Offer-Sheet-UK.xlsx',
        ];

        $fileName = $templateFiles[$activeCompany] ?? 'Offer-Sheet-US.xlsx';
        $path = storage_path('app/public/downloads/' . $fileName);

        // If Excel template exists, download it
        if (file_exists($path)) {
            return response()->download($path, $activeCompany . '_Offer_Sheet_Template.xlsx');
        }

        // ====================== CSV Fallback with Dynamic Units ======================
        $isUS = ($activeCompany === '2100');

        $currency = trim(config('app.active_currency_symbol')); // $, ₹, €

        $lwhUnit = $isUS ? 'Inches' : 'CM';
        $weightUnit = $isUS ? 'LBS' : 'KG';   // You can change to LBS if needed for 2100

        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM

        $csv .= "S.no,Vendor SKU,Product Name,Product Image,Product Length ({$lwhUnit}),Product Width ({$lwhUnit}),Product Height ({$lwhUnit}),Product Weight ({$weightUnit}),Material Composition,Color,Product Finish,Category,Sub Category,Vendor FOB{{$currency}},Comments\n";

        // Sample Row
        $csv .= "1,EB123,Glass Vase,,10,10,12," . ($isUS ? "450" : "0.45") . ",Glass,Clear,Glossy,Home & Décor,Décor,1.25,Sample Comment\n";

        $downloadFileName = $activeCompany . "_Offer_Sheet_Template.csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$downloadFileName}\"",
        ]);
    }
    /**
     * Download submitted offer sheet data as CSV
     */
    public function downloadOfferSheet(OfferSheet $offerSheet)
    {
        $vendor = auth()->user()->vendor;
        if ($offerSheet->vendor_id !== $vendor->id) {
            abort(403);
        }

        $activeCompany = session('active_company') ?? '2100';
        $isUS = ($activeCompany === '2100');
        $currency = trim(config('app.active_currency_symbol')); // $, ₹, €
        $lwhUnit = $isUS ? 'Inches' : 'CM';
        $weightUnit = $isUS ? 'LBS' : 'KG';   // You can change to LBS if needed for 2100
        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
        $csv .= "S.no,Vendor SKU,Product Name,Product Length ({$lwhUnit}),Product Width ({$lwhUnit}),Product Height ({$lwhUnit}),Product Weight ({$weightUnit}),Material,Color,Finish,Category,Sub Category,Vendor FOB ({$currency}),Comments,Selection\n";

        foreach ($offerSheet->items as $item) {
            $d = $item->product_details ?? [];
            $csv .= implode(',', [
                $d['sno'] ?? $item->id,
                '"' . str_replace('"', '""', $item->product_sku) . '"',
                '"' . str_replace('"', '""', $item->product_name) . '"',
                $d['length'] ?? $d['length_inches'] ?? '',
                $d['width'] ?? $d['width_inches'] ?? '',
                $d['height'] ?? $d['height_inches'] ?? '',
                $d['weight'] ?? $d['weight_grams'] ?? '',
                '"' . str_replace('"', '""', $d['material'] ?? '') . '"',
                '"' . str_replace('"', '""', $d['color'] ?? '') . '"',
                '"' . str_replace('"', '""', $d['finish'] ?? '') . '"',
                '"' . str_replace('"', '""', $d['category'] ?? '') . '"',
                '"' . str_replace('"', '""', $d['sub_category'] ?? '') . '"',
                $item->vendor_price,
                '"' . str_replace('"', '""', $d['comments'] ?? '') . '"',
                $item->is_selected ? 'Selected' : '',
            ]) . "\n";
        }

        $filename = "offer-sheet-{$offerSheet->offer_sheet_number}.csv";
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB
            'offer_sheet_item_id' => 'required|exists:offer_sheet_items,id'
        ]);

        $item = OfferSheetItem::findOrFail($request->offer_sheet_item_id);



        if ($request->hasFile('image')) {
            // Delete old image if exists


            // Determine storage disk based on environment

            // Delete thumbnail if exists
            if ($item->thumbnail && FileStorage::exists($item->thumbnail)) {
                FileStorage::delete($item->thumbnail);
            }

            // Store new image
            $path = $request->file('image')->store('offer-thumbnails', FileStorage::disk());

            // Save path to database
            $item->update(['thumbnail' => $path]);

            return response()->json([
                'success' => true,
                'image_url' => FileStorage::url($path),
                'message' => 'Image uploaded successfully.'
            ]);
        }

        return response()->json(['success' => false, 'message' => 'No image uploaded.'], 400);
    }
    // =====================================================================
    //  CONSIGNMENTS, LIVE SHEETS, SALES, CHARGEBACKS, PAYOUTS
    //  (same as previous version — kept intact)
    // =====================================================================

    public function consignments()
    {
        $vendor = auth()->user()->vendor;

        $activeCompany = session('active_company');

        //$consignments = $vendor->consignments()->with('liveSheet', 'grn', 'shipment')->latest()->paginate(20);

        $consignments = $vendor->consignments()
            ->when($activeCompany, function ($q) use ($activeCompany) {
                $q->where('company_code', $activeCompany);
            })
            ->with('liveSheet', 'shipments')->latest()->paginate(20);
        //             echo '<pre>';
        //             print_r( $consignments->toArray() );
        // echo '</pre>';

        return view('vendor.consignments.index', compact('consignments', 'vendor'));
    }

    /**
     * Upload Commercial Invoice for a consignment
     */
    public function uploadCommercialInvoice(Request $request, Consignment $consignment)
    {
        $vendor = auth()->user()->vendor;
        if ($consignment->vendor_id !== $vendor->id) {
            abort(403);
        }

        $request->validate([
            'commercial_invoice_file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
            'commercial_invoice_number' => 'required|string|max:100',
        ], [
            'commercial_invoice_file.required' => 'Please upload the Commercial Invoice file.',
            'commercial_invoice_number.required' => 'Invoice number is required.',
        ]);

        try {
            $path = $request->file('commercial_invoice_file')
                ->store("consignments/{$consignment->id}/documents", FileStorage::disk());

            $consignment->update([
                'commercial_invoice_file'        => $path,
                'commercial_invoice_number'      => $request->commercial_invoice_number,
                'commercial_invoice_upload_date'  => now()->toDateString(),
                'commercial_invoice_upload_by'    => auth()->id(),
            ]);

            \App\Models\ActivityLog::log('uploaded', 'commercial_invoice', $consignment, null, [
                'invoice_number' => $request->commercial_invoice_number,
            ], "Commercial Invoice {$request->commercial_invoice_number} uploaded for {$consignment->consignment_number}");

            return back()->with('success', 'Commercial Invoice uploaded successfully.');
        } catch (\Exception $e) {
            \Log::error('Commercial invoice upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage())->withInput();
        }
    }
    /**
     * Upload Packing List for a consignment
     */
    public function uploadPackingList(Request $request, Consignment $consignment)
    {
        $vendor = auth()->user()->vendor;
        if ($consignment->vendor_id !== $vendor->id) {
            abort(403);
        }

        $request->validate([
            'packing_list_file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,xlsx,xls,csv',
            'packing_list_number' => 'required|string|max:100',
        ], [
            'packing_list_file.required' => 'Please upload the Packing List file.',
            'packing_list_number.required' => 'Packing list number is required.',
        ]);

        try {
            $path = $request->file('packing_list_file')
                ->store("consignments/{$consignment->id}/documents", FileStorage::disk());

            $consignment->update([
                'packing_list_file'        => $path,
                'packing_list_number'      => $request->packing_list_number,
                'packing_list_upload_date' => now()->toDateString(),
                'packing_list_upload_by'   => auth()->id(),
            ]);

            \App\Models\ActivityLog::log('uploaded', 'packing_list', $consignment, null, [
                'packing_list_number' => $request->packing_list_number,
            ], "Packing List {$request->packing_list_number} uploaded for {$consignment->consignment_number}");

            return back()->with('success', 'Packing List uploaded successfully.');
        } catch (\Exception $e) {
            \Log::error('Packing list upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage())->withInput();
        }
    }
    /**
     * Upload Shipping Bill Copy
     */
    public function uploadShippingBill(Request $request, Consignment $consignment)
    {
        $vendor = auth()->user()->vendor;
        if ($consignment->vendor_id !== $vendor->id) {
            abort(403);
        }

        $request->validate([
            'shipping_bill_file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
            'shipping_bill_number' => 'required|string|max:100',
        ]);

        try {
            $path = $request->file('shipping_bill_file')->store("consignments/{$consignment->id}/documents", FileStorage::disk());
            $consignment->update([
                'shipping_bill_file'        => $path,
                'shipping_bill_number'      => $request->shipping_bill_number,
                'shipping_bill_upload_date' => now()->toDateString(),
                'shipping_bill_upload_by'   => auth()->id(),
            ]);
            ActivityLog::log('uploaded', 'shipping_bill', $consignment, null, ['number' => $request->shipping_bill_number], "Shipping Bill uploaded for {$consignment->consignment_number}");
            return back()->with('success', 'Shipping Bill uploaded successfully.');
        } catch (\Exception $e) {
            \Log::error('Shipping bill upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Upload Measurement Copy
     */
    public function uploadMeasurement(Request $request, Consignment $consignment)
    {
        $vendor = auth()->user()->vendor;
        if ($consignment->vendor_id !== $vendor->id) {
            abort(403);
        }

        $request->validate([
            'measurement_file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,xlsx,xls',
            'measurement_number' => 'required|string|max:100',
        ]);

        try {
            $path = $request->file('measurement_file')->store("consignments/{$consignment->id}/documents", FileStorage::disk());
            $consignment->update([
                'measurement_file'        => $path,
                'measurement_number'      => $request->measurement_number,
                'measurement_upload_date' => now()->toDateString(),
                'measurement_upload_by'   => auth()->id(),
            ]);
            ActivityLog::log('uploaded', 'measurement', $consignment, null, ['number' => $request->measurement_number], "Measurement copy uploaded for {$consignment->consignment_number}");
            return back()->with('success', 'Measurement Copy uploaded successfully.');
        } catch (\Exception $e) {
            \Log::error('Measurement upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Upload HBL (House Bill of Lading) Copy
     */
    public function uploadHbl(Request $request, Consignment $consignment)
    {
        $vendor = auth()->user()->vendor;
        if ($consignment->vendor_id !== $vendor->id) {
            abort(403);
        }

        $request->validate([
            'hbl_file'   => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
            'hbl_number' => 'required|string|max:100',
        ]);

        try {
            $path = $request->file('hbl_file')->store("consignments/{$consignment->id}/documents", FileStorage::disk());
            $consignment->update([
                'hbl_file'        => $path,
                'hbl_number'      => $request->hbl_number,
                'hbl_upload_date' => now()->toDateString(),
                'hbl_upload_by'   => auth()->id(),
            ]);
            ActivityLog::log('uploaded', 'hbl', $consignment, null, ['number' => $request->hbl_number], "HBL uploaded for {$consignment->consignment_number}");
            return back()->with('success', 'HBL Copy uploaded successfully.');
        } catch (\Exception $e) {
            \Log::error('HBL upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Upload Other Document
     */
    public function uploadOtherDoc(Request $request, Consignment $consignment)
    {
        $vendor = auth()->user()->vendor;
        if ($consignment->vendor_id !== $vendor->id) {
            abort(403);
        }

        $request->validate([
            'other_doc_file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx',
            'other_doc_name' => 'required|string|max:200',
        ]);

        try {
            $path = $request->file('other_doc_file')->store("consignments/{$consignment->id}/documents", FileStorage::disk());
            $consignment->update([
                'other_doc_file'        => $path,
                'other_doc_name'        => $request->other_doc_name,
                'other_doc_upload_date' => now()->toDateString(),
                'other_doc_upload_by'   => auth()->id(),
            ]);
            ActivityLog::log('uploaded', 'other_document', $consignment, null, ['name' => $request->other_doc_name], "Other doc uploaded for {$consignment->consignment_number}");
            return back()->with('success', 'Document uploaded successfully.');
        } catch (\Exception $e) {
            \Log::error('Other doc upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage())->withInput();
        }
    }

    public function liveSheets()
    {
        $user = auth()->user();

        $activeCompany = session('active_company');

        $vendor = $user->vendor;
        $liveSheets = LiveSheet::where('vendor_id', $vendor->id)
            ->with('consignment', 'offerSheet', 'items.product')
            ->when($activeCompany, function ($query) use ($activeCompany) {
                $query->where('company_code', $activeCompany);
            })
            ->latest()->paginate(20);
        return view('vendor.live-sheets.index', compact('liveSheets', 'vendor'));
    }

    public function editLiveSheet(LiveSheet $liveSheet)
    {
        $liveSheet->load('consignment', 'offerSheet', 'items.product');
        $vendor = auth()->user()->vendor;
        if ($liveSheet->vendor_id !== $vendor->id) {
            abort(403);
        }
        // if ($liveSheet->is_locked) {
        //    return back()->with('error', 'Live sheet is locked. Contact admin to unlock.');
        // }
        return view('vendor.live-sheets.edit', compact('liveSheet', 'vendor'));
    }
    public function submitLiveSheet(Request $request, LiveSheet $liveSheet)
    {
        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        $service = new \App\Services\LiveSheetService();
        $result = $service->updateItems($liveSheet, $request->items, 'vendor', $request->change_reason);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        // Submit for approval
        $liveSheet->update(['status' => 'submitted']);

        return redirect()->route('vendor.live-sheets')
            ->with('success', 'Live sheet submitted for Sourcing approval.');
    }
    public function submitLiveSheet27072026(Request $request, LiveSheet $liveSheet)
    {
        $request->validate([
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.quantity'         => 'required|integer|min:1',
            //  'items.*.unit_price'       => 'required|numeric|min:0',
            //'items.*.cbm_per_unit'     => 'required|numeric|min:0',
            //  'items.*.weight_per_unit'  => 'nullable|numeric|min:0',
        ]);

        // Track changes before submitting
        try {
            foreach ($request->items as $row) {
                $item = \App\Models\LiveSheetItem::where('live_sheet_id', $liveSheet->id)
                    ->where('product_id', $row['product_id'])->first();
                if (!$item) {
                    continue;
                }

                $newDetails = [
                    'vendor_fob' => $row['unit_price'],
                    'final_qty'  => $row['quantity'],
                ];
                \App\Models\LiveSheetItemChange::trackChanges(
                    $item,
                    $newDetails,
                    auth()->user(),
                    'vendor',
                    $request->change_reason
                );
            }
        } catch (\Exception $e) {
            \Log::warning('Vendor submit tracking failed: ' . $e->getMessage());
        }

        $this->sourcingService->submitLiveSheet($liveSheet, $request->items, 'vendor');
        return redirect()->route('vendor.live-sheets')->with('success', 'Live sheet submitted for Sourcing approval.');
    }

    /**
     * Vendor creates consignment after both dates are set
     */
    public function createConsignment(LiveSheet $liveSheet)
    {
        $vendor = auth()->user()->vendor;
        if ($liveSheet->vendor_id !== $vendor->id) {
            abort(403);
        }

        if ($liveSheet->consignment_id) {
            return back()->with('error', 'Consignment already exists for this live sheet.');
        }

        if (!$liveSheet->ex_factory_date || !$liveSheet->final_inspection_date) {
            return back()->with('error', 'Please set both Ex-Factory Date and Final Inspection Date before creating the consignment.');
        }

        try {
            $items = $liveSheet->items()->where('is_selected', 1)->get();
            if ($items->isEmpty()) {
                $items = $liveSheet->items;
            }
            if ($items->isEmpty()) {
                return back()->with('error', 'No items in this live sheet.');
            }

            $country = match ($liveSheet->company_code) {
                '2100' => 'US',
                '2200' => 'EU',
                '2400' => 'GB',
                default => 'IN',
            };

            $consignment = Consignment::create([
                'consignment_number'   => Consignment::generateNumber($liveSheet->company_code, $country),
                'vendor_id'            => $vendor->id,
                'live_sheet_id'        => $liveSheet->id,
                'offer_sheet_id'       => $liveSheet->offer_sheet_id,
                'company_code'         => $liveSheet->company_code,
                'destination_country'  => $country,
                'status'               => 'created',
                'total_items'          => $items->sum('quantity'),
                'total_cbm'            => $items->sum('total_cbm'),
                'total_value'          => $items->sum('unit_price'), // $items->sum('total_price'),
                'ex_factory_date'      => $liveSheet->ex_factory_date,
                'final_inspection_date' => $liveSheet->final_inspection_date,
                'created_by'           => auth()->id(),
            ]);

            $liveSheet->update(['consignment_id' => $consignment->id]);
            $liveSheet->items()->update(['consignment_id' => $consignment->id]);

            \App\Models\ActivityLog::log('created', 'consignment', $consignment, null, [
                'created_by_vendor' => true,
                'ex_factory_date'   => $liveSheet->ex_factory_date->toDateString(),
                'final_inspection_date' => $liveSheet->final_inspection_date->toDateString(),
            ], "Consignment {$consignment->consignment_number} created by vendor");

            // Notify Sourcing & Logistics
            try {
                $sourcing = \App\Models\User::internal()->byDepartment('sourcing')->active()->get();
                $logistics = \App\Models\User::internal()->byDepartment('logistics')->active()->get();
                \Illuminate\Support\Facades\Notification::send($sourcing->merge($logistics), new \App\Notifications\ConsignmentNotification($consignment, 'ready_for_planning'));
            } catch (\Exception $e) {
                \Log::warning('Consignment notification failed: ' . $e->getMessage());
            }

            return redirect()->route('vendor.live-sheets')
                ->with('success', "Consignment {$consignment->consignment_number} created successfully. Sourcing and Logistics have been notified.");
        } catch (\Exception $e) {
            \Log::error('Vendor consignment creation failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to create consignment: ' . $e->getMessage());
        }
    }
    /**
     * Vendor change history for their live sheet
     */
    public function liveSheetHistory(LiveSheet $liveSheet)
    {
        $vendor = auth()->user()->vendor;
        if ($liveSheet->vendor_id !== $vendor->id) {
            abort(403);
        }


        $liveSheet->load('vendor', 'items.product');

        try {
            $changes = \App\Models\LiveSheetItemChange::where('live_sheet_id', $liveSheet->id)
                ->with('user', 'product')
                ->orderByDesc('created_at')
                ->paginate(50);
        } catch (\Exception $e) {
            $changes = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
        }

        $revisions = $changes->getCollection()->groupBy('revision_number')->map(function ($g) {
            $fullRole = $g->first()->changed_by_role;
            $parts = explode(':', $fullRole, 2);
            return [
                'revision' => $g->first()->revision_number,
                'user'     => $g->first()->user,
                'role'     => $parts[0] ?? $fullRole,
                'email'    => $parts[1] ?? null,
                'reason'   => $g->first()->change_reason,
                'date'     => $g->first()->created_at,
                'count'    => $g->count(),
                'changes'  => $g,
            ];
        })->sortByDesc('revision');

        return view('vendor.live-sheets.history', compact('liveSheet', 'changes', 'revisions'));
    }
    /**
     * Download live sheet template pre-filled with SKUs for vendor to fill
     */
    public function downloadLiveSheetTemplate(LiveSheet $liveSheet)
    {
        $currency = trim(config('app.active_currency_symbol')); // $, ₹, €
        $vendor = auth()->user()->vendor;
        if ($liveSheet->vendor_id !== $vendor->id) {
            abort(403);
        }

        $liveSheet->load('items.product');

        $activeCompany = session('active_company') ?? '2100';
        $isUS = ($activeCompany === '2100');
        $lwhUnit = $isUS ? 'Inches' : 'CM';
        $weightUnit = $isUS ? 'LBS' : 'KG';   // You can change to LBS if needed for 2100

        $headers = "S.no,Vendor SKU,Product Name,Product Description (Min 100 words),Product Specification,HSN & HTS Code,Duty %,Product Length ({$lwhUnit}),Product Width ({$lwhUnit}),Product Height ({$lwhUnit}),Product Weight ({$weightUnit}),Material Composition,Other Material,Color,Product Finish,Category,Sub Category,Qty In Inner Pack,Inner Carton Length ({$lwhUnit}),Inner Carton Width ({$lwhUnit}),Inner Carton Height ({$lwhUnit}),Inner Carton Weight ({$weightUnit}),Qty In Master Pack,Master Carton Length ({$lwhUnit}),Master Carton Width ({$lwhUnit}),Master Carton Height ({$lwhUnit}),Master Carton Weight ({$weightUnit}),No Of Master Carton,Qty Offered (Units/Sets),Vendor FOB({$currency})\n";
        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM
        $csv .= $headers;

        // Track totals for numeric columns
        // Column indexes: 0=Sno, 6=Duty%, 7=Length, 8=Width, 9=Height, 10=Weight,
        // 17=InnerQty, 18=InnerL, 19=InnerW, 20=InnerH, 21=MasterQty, 22=MasterL,
        // 23=MasterW, 24=MasterH, 25=MasterWt, 26=QtyOffered, 27=FOB
        $totals = array_fill(0, 28, 0);
        $numericCols = [6, 7, 8, 9, 10, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27];

        foreach ($liveSheet->items as $idx => $item) {
            $p = $item->product;
            $d = $item->product_details ?? [];

            $row = [
                $idx + 1,
                '"' . str_replace('"', '""', $p->sku ?? '') . '"',
                //  $p->barcode ?? $d['barcode'], // Barcode
                '"' . str_replace('"', '""', $p->name ?? '') . '"',
                '"' . str_replace('"', '""', $d['description'] ?? '') . '"',                // Description
                '"' . str_replace('"', '""', $d['specification'] ?? '') . '"', // Specification
                '"' . str_replace('"', '""', $d['hsn_hts_code'] ?? '') . '"', // HSN
                $d['duty_percent'] ?? '', // Duty %
                $d['length'] ?? $d['length_inches'] ?? '',
                $d['width'] ?? $d['width_inches'] ?? '',
                $d['height'] ?? $d['height_inches'] ?? '',
                $d['weight'] ?? $d['weight_grams'] ?? '',
                '"' . str_replace('"', '""', $d['material'] ?? '') . '"',
                '', // Other Material
                '"' . str_replace('"', '""', $d['color'] ?? '') . '"',
                '"' . str_replace('"', '""', $d['finish'] ?? '') . '"',
                '"' . str_replace('"', '""', $d['category'] ?? '') . '"',
                '"' . str_replace('"', '""', $d['sub_category'] ?? '') . '"',
                $d['qty_inner_pack'] ?? '', // Inner pack qty
                $d['inner_length'] ?? $d['inner_carton_length'] ?? '', // Inner Carton Length
                $d['inner_width'] ?? $d['inner_carton_width'] ?? '', // Inner Carton Width
                $d['inner_height'] ?? $d['inner_carton_height'] ?? '', // Inner Carton Height
                $d['inner_weight'] ??  $d['inner_carton_weight'] ??  $d['inner_weight_kg'] ?? '', // Inner Carton Weight
                $d['qty_master_pack'] ?? '', // Qty In Master Pack
                $d['master_length'] ??  $d['master_carton_length'] ?? '', // Master Carton Length
                $d['master_width'] ??  $d['master_carton_width'] ?? '', // Master Carton Width
                $d['master_height'] ??  $d['master_carton_height'] ?? '', // Master Carton Height
                $d['master_weight'] ??  $d['master_carton_weight'] ?? $d['master_weight_kg'] ?? '', // Master Carton Weight
                $d['no_of_master_carton'] ?? '', // No of Master Cartons
                $item->quantity, // $d['qty_offered'] ?? '', // Qty Offered
                '"' . str_replace('"', '""', $p->vendor_price ?? '') . '"',
            ];

            // Accumulate totals for numeric columns
            foreach ($numericCols as $ci) {
                $val = str_replace('"', '', $row[$ci] ?? '');
                if (is_numeric($val)) {
                    $totals[$ci] += floatval($val);
                }
            }

            $csv .= implode(',', $row) . "\n";
        }

        // Add Line Total row
        $totalRow = [];
        for ($i = 0; $i < 28; $i++) {
            if ($i === 0) {
                $totalRow[] = ''; // S.no
            } elseif ($i === 5) {
                $totalRow[] = '"LINE TOTAL"';
            } elseif (in_array($i, $numericCols) && $totals[$i] > 0) {
                $totalRow[] = round($totals[$i], 2);
            } else {
                $totalRow[] = '';
            }
        }
        $csv .= implode(',', $totalRow) . "\n";

        $filename = "live-sheet-{$liveSheet->live_sheet_number}.csv";
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
    /**
     * Download blank live sheet Excel template
     */
    public function downloadLiveSheetBlankTemplate()
    {
        $path = public_path('downloads/Live_Sheet-US.xlsx');
        if (!file_exists($path)) {
            return back()->with('error', 'Template file not found.');
        }
        return response()->download($path, 'Live_Sheet_Template.xlsx');
    }

    /**
     * Upload filled live sheet Excel — update items by SKU match
     */
    public function uploadLiveSheet(Request $request, LiveSheet $liveSheet)
    {
        // $request->validate([
        //     'live_sheet_file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        // ]);

        $activeCompany = session('active_company') ?? '2100';

        $request->validate([
            'live_sheet_file' => [
                'required',
                'file',
                'max:20480',
                //'mimes:xlsx,xls,csv',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,text/plain,application/csv,application/octet-stream',
            ],
        ]);

        $vendor = auth()->user()->vendor;
        if ($liveSheet->vendor_id !== $vendor->id) {
            abort(403);
        }
        if ($liveSheet->is_locked) {
            return back()->with('error', 'Live sheet is locked.');
        }

        $file = $request->file('live_sheet_file');
        // Parse from temp upload (works for both local and S3)
        $fullPath = $file->getRealPath();
        $parsed = $this->parseLiveSheetExcel($fullPath);
        // Store on configured disk after parsing
        $storedPath = $file->store('live-sheet-uploads/' . $vendor->id, FileStorage::disk());

        if (empty($parsed)) {
            return back()->with('error', 'No valid data found. Please use the provided template.');
        }
        // Check if error returned
        if (isset($parsed['error'])) {
            $errorMessage = nl2br(htmlspecialchars($parsed['error']));
            return back()->with('error', $errorMessage);
        }
        $updated = 0;
        $errors = [];

        foreach ($parsed as $idx => $row) {
            $sku = $row['vendor_sku'] ?? '';
            if (empty($sku)) {
                continue;
            }

            // Find matching live sheet item by SKU
            $item = $liveSheet->items()->whereHas('product', fn($q) => $q->where('sku', $sku))->first();

            if (!$item) {
                $errors[] = "Row " . ($idx + 1) . ": SKU '{$sku}' not found in this live sheet.";
                continue;
            }
            if (empty(trim($row['description']))) {
                $errors[] = "Row " . ($idx + 1) . ": Description not found in this live sheet. Please add description.";
            }
            // ── Barcode validation ──
            $barcode = trim($row['barcode'] ?? '');
            if (!empty($barcode)) {
                // Check uniqueness against other products (exclude current product)
                $dupBarcode = \App\Models\Product::withoutGlobalScopes()->where('barcode', $barcode)
                    ->where('id', '!=', $item->product_id)
                    ->first();
                if ($dupBarcode) {
                    $errors[] = "Row " . ($idx + 1) . ": Barcode '{$barcode}' already assigned to SKU '{$dupBarcode->sku}'.";
                    continue;
                }
            }

            // Calculate CBM and weight based on master carton details if provided
            $masterL = (float)($row['master_length'] ?? 0);
            $masterW = (float)($row['master_width'] ?? 0);
            $masterH = (float)($row['master_height'] ?? 0);
            $qtyMaster = (int)($row['qty_master_pack'] ?? 1);
            $finalQty = (int)($row['final_qty'] ?? $row['qty_offered'] ?? $item->quantity);
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

            //   $totalMasterCartons = $qtyMaster > 0 ? ceil($finalQty / $qtyMaster) : 0;
            //   $cbmShipment = $totalMasterCartons * $masterCbm;

            $cbmShipment = $masterCbm * $row['no_of_master_carton'];
            $unitPrice = (float)($row['vendor_fob'] ?? $item->unit_price);
            $finalFob = (float)($row['final_fob'] ?? $unitPrice);
            $weightPerUnit = isset($row['weight']) && $row['weight'] > 0
                ? round((float)$row['weight'] / 1000, 3)
                : (float)($item->weight_per_unit ?? 0);

            $masterWeight = (float)($row['master_weight'] ?? 0);

            // Track changes BEFORE updating
            try {
                $newDetails = [
                    'vendor_fob'  => $row['vendor_fob'] ?? null,
                    'qty_offered' => $row['qty_offered'] ?? null,
                    'final_qty'   => $finalQty,
                    'final_fob'   => $finalFob,
                    'barcode'     => $row['barcode'] ?? null,
                    'sap_code'    => $row['sap_code'] ?? null,
                    'vendor_wsp'    => $row['vendor_wsp'] ?? null,
                ];
                \App\Models\LiveSheetItemChange::trackChanges($item, $newDetails, auth()->user(), 'vendor');
            } catch (\Exception $e) {
                \Log::warning('Vendor change tracking failed: ' . $e->getMessage());
            }
            $itemData = [
                'quantity'        => $finalQty,
                'unit_price'      => $finalFob ?: $unitPrice,
                'total_price'     => ($finalFob ?: $unitPrice) * $finalQty,
                'cbm_per_unit'    => $qtyMaster > 0 ? round($masterCbm / $qtyMaster, 6) : 0,
                'total_cbm'       => round($cbmShipment, 4),
                'weight_per_unit' => $weightPerUnit,
                'total_weight'    => round($weightPerUnit * $finalQty, 3),
                'product_details' => array_merge($item->product_details ?? [], [
                    'sno'              => $row['sno'] ?? null,
                    'sap_code'         => $row['sap_code'] ?? null,
                    'vendor_wsp'       => $row['vendor_wsp'] ?? null,
                    'barcode'          => $row['barcode'] ?? null,
                    'description'      => $row['description'] ?? null,
                    'specification'    => $row['specification'] ?? null,
                    'hsn_hts_code'     => $row['hsn_code'] ?? null,
                    'duty_percent'     => trim(str_replace('%', '', $row['duty_percent'])) ?? null,
                    'length'           => $row['length'] ?? null,
                    'width'            => $row['width'] ?? null,
                    'height'           => $row['height'] ?? null,
                    'weight'           => $row['weight'] ?? null,
                    'material'         => $row['material'] ?? null,
                    'other_material'   => $row['other_material'] ?? null,
                    'color'            => $row['color'] ?? null,
                    'finish'           => $row['finish'] ?? null,
                    'category'         => $row['category'] ?? null,
                    'sub_category'     => $row['sub_category'] ?? null,
                    'qty_inner_pack'   => $row['qty_inner_pack'] ?? null,
                    'inner_carton_length'     => $row['inner_length'] ?? null,
                    'inner_carton_width'      => $row['inner_width'] ?? null,
                    'inner_carton_height'     => $row['inner_height'] ?? null,
                    'inner_carton_weight'      => $row['inner_weight'] ?? null,
                    'qty_master_pack'          => $row['qty_master_pack'] ?? null,
                    'master_carton_length'    => $masterL,
                    'master_carton_width'     => $masterW,
                    'master_carton_height'    => $masterH,
                    'master_carton_weight'     => $masterWeight,
                    'no_of_master_carton' => $row['no_of_master_carton'] ?? null,
                    'qty_offered'      => $row['qty_offered'] ?? null,
                    'vendor_fob'       => $row['vendor_fob'] ?? null
                ]),
            ];
            $item->update($itemData);

            \Log::channel('daily')->info('Live sheet item data', $itemData);

            // Update product master

            $updateData = [];

            $updateData['vendor_price'] = $finalFob ?: $unitPrice;
            $updateData['cbm']          = $cbmShipment; //$qtyMaster > 0 ? round($masterCbm / $qtyMaster, 6) : null;
            $updateData['weight']       = $weightPerUnit ?: null;
            $updateData['description']  = $row['description'] ?? null;
            $updateData['hsn_code']     = $row['hsn_code'] ?? null;

            if (!empty($barcode)) {
                $updateData['barcode'] = $barcode;
            }

            // Remove null values before update
            $updateData = array_filter($updateData, fn($val) => $val !== null);
            $item->product->update($updateData);
            $updated++;
        }

        // Recalculate live sheet totals
        $liveSheet->update([
            'total_cbm' => $liveSheet->items()->sum('total_cbm'),
        ]);

        $msg = "{$updated} item(s) updated from uploaded file.";
        if (count($errors) > 0) {
            $msg .= ' ' . count($errors) . ' error(s).';
        }

        return redirect()->route('vendor.live-sheets.edit', $liveSheet)
            ->with('success', $msg)
            ->with('upload_errors', $errors);
    }

    /**
     * Parse Live Sheet Excel matching the Live_Sheet-US template (44 columns)
     */
    protected function parseLiveSheetExcel(string $filePath): array
    {
        $rows = [];

        $formulaErrors = [];

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $maxRow = $sheet->getHighestRow();

            // Scan for formulas

            for ($row = 2; $row <= $maxRow; $row++) {
                $rowHasFormula = false;
                $formulaDetails = [];

                foreach ($sheet->getRowIterator($row, $row) as $rowObj) {
                    foreach ($rowObj->getCellIterator() as $cell) {
                        $rawValue = $cell->getValue();
                        $calculatedValue = $cell->getCalculatedValue();

                        // Detect formula: starts with '=' or calculated value differs significantly
                        if (is_string($rawValue) && str_starts_with(trim($rawValue), '=')) {
                            $rowHasFormula = true;
                            $col = $cell->getColumn();
                            $formulaDetails[] = "{$col}{$row}: " . substr($rawValue, 0, 40) . (strlen($rawValue) > 40 ? '...' : '');
                        }
                    }
                }

                if ($rowHasFormula) {
                    $formulaErrors[] = "Row {$row}: " . implode(", ", $formulaDetails);
                }
            }


            // === FORMULA VALIDATION ===
            if (!empty($formulaErrors)) {
                $errorMsg = "Formula detected in uploaded file!\n";
                $errorMsg .= "The following cells contain formulas:\n";
                $errorMsg .= implode("\n", array_slice($formulaErrors, 0, 10));

                //  $errorMsg .= implode("\n", $formulaErrors);

                if (count($formulaErrors) > 10) {
                    $errorMsg .= "\n... and " . (count($formulaErrors) - 10) . " more cells.";
                }

                $errorMsg .= "\nPlease remove all formulas and upload only static values.";
                \Log::error('Live sheet parse error: ' . $errorMsg);
                return ['error' => $errorMsg];
                //throw new \Exception($errorMsg);
            }

            // Build header map
            $colMap = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $val = strtolower(trim(preg_replace('/\s+/', ' ', (string) $cell->getValue())));
                    $col = $cell->getColumn();

                    if (empty($val)) {
                        continue;
                    }
                    if (str_contains($val, 'inner carton weight') || $val === 'inner carton weight (kg)') {
                        \Log::channel('daily')->debug("Live sheet col map: {$col} = {$val}");
                        $colMap['inner_weight'] = $col;
                    }
                    if (str_contains($val, 'master') && str_contains($val, 'weight')) {
                        $colMap['master_weight'] = $col;
                        \Log::channel('daily')->debug("Live sheet col map: {$col} = {$val}");
                    }

                    if (str_contains($val, 'vendor sku') || $val === 'sku') {
                        $colMap['vendor_sku'] = $col;
                    } elseif (str_contains($val, 'barcode')) {
                        $colMap['barcode'] = $col;
                    } elseif (str_contains($val, 'product name')) {
                        $colMap['product_name'] = $col;
                    } elseif (str_contains($val, 'description')) {
                        $colMap['description'] = $col;
                    } elseif (str_contains($val, 'specification')) {
                        $colMap['specification'] = $col;
                    } elseif (str_contains($val, 'hsn') || str_contains($val, 'hts')) {
                        $colMap['hsn_code'] = $col;
                    } elseif (str_contains($val, 'duty %') || str_contains($val, 'duty%')) {
                        $colMap['duty_percent'] = $col;
                    } elseif (str_contains($val, 'product length')) {
                        $colMap['length'] = $col;
                    } elseif (str_contains($val, 'product width')) {
                        $colMap['width'] = $col;
                    } elseif (str_contains($val, 'product height')) {
                        $colMap['height'] = $col;
                    } elseif (str_contains($val, 'product weight')) {
                        $colMap['weight'] = $col;
                        //  file_put_contents(storage_path('logs/live_sheet_parse.log'), "Live sheet col map: {$col} = {$val}\n", FILE_APPEND);

                    } elseif (str_contains($val, 'material') && !str_contains($val, 'other')) {
                        $colMap['material'] = $col;
                    } elseif (str_contains($val, 'other material')) {
                        $colMap['other_material'] = $col;
                    } elseif (str_contains($val, 'color')) {
                        $colMap['color'] = $col;
                    } elseif (str_contains($val, 'finish')) {
                        $colMap['finish'] = $col;
                    } elseif (str_contains($val, 'sub category')) {
                        $colMap['sub_category'] = $col;
                    } elseif (str_contains($val, 'category') && !str_contains($val, 'sub')) {
                        $colMap['category'] = $col;
                    } elseif (str_contains($val, 'qty in inner')) {
                        $colMap['qty_inner_pack'] = $col;
                    } elseif (str_contains($val, 'inner') && str_contains($val, 'length')) {
                        $colMap['inner_length'] = $col;
                    } elseif (str_contains($val, 'inner') && str_contains($val, 'width')) {
                        $colMap['inner_width'] = $col;
                    } elseif (str_contains($val, 'inner') && str_contains($val, 'height')) {
                        $colMap['inner_height'] = $col;
                    } elseif (str_contains($val, 'inner carton weight') || $val === 'inner carton weight (kg)') {
                        $colMap['inner_weight'] = $col;
                    } elseif (str_contains($val, 'qty in master')) {
                        $colMap['qty_master_pack'] = $col;
                    } elseif (str_contains($val, 'master') && str_contains($val, 'length')) {
                        $colMap['master_length'] = $col;
                    } elseif (str_contains($val, 'master') && str_contains($val, 'width')) {
                        $colMap['master_width'] = $col;
                    } elseif (str_contains($val, 'master') && str_contains($val, 'height')) {
                        $colMap['master_height'] = $col;
                    } elseif (str_contains($val, 'master') && str_contains($val, 'weight')) {
                        $colMap['master_weight'] = $col;
                    } elseif (str_contains($val, 'master') && str_contains($val, 'carton')) {
                        $colMap['no_of_master_carton'] = $col;
                    } elseif (str_contains($val, 'qty offered')) {
                        $colMap['qty_offered'] = $col;
                    } elseif (str_contains($val, 'vendor fob')) {
                        $colMap['vendor_fob'] = $col;
                    } elseif (str_contains($val, 'target fob')) {
                        $colMap['target_fob'] = $col;
                    } elseif (str_contains($val, 'final qty')) {
                        $colMap['final_qty'] = $col;
                    } elseif (str_contains($val, 'final fob')) {
                        $colMap['final_fob'] = $col;
                    } elseif (str_contains($val, 'freight factor')) {
                        $colMap['freight_factor'] = $col;
                    } elseif (str_contains($val, 'wsp factor')) {
                        $colMap['wsp_factor'] = $col;
                    } elseif (str_contains($val, 'comment')) {
                        $colMap['comments'] = $col;
                    } elseif (str_contains($val, 's.no') || $val === 'sno') {
                        $colMap['sno'] = $col;
                    }
                }
            }


            // Start from row 3 (row 1 = headers, row 2 = formulas)
            for ($r = 2; $r <= $maxRow; $r++) {
                $sku = trim((string) ($sheet->getCell(($colMap['vendor_sku'] ?? 'B') . $r)->getValue() ?? ''));
                if (empty($sku)) {
                    continue;
                }

                $getVal = fn($key, $default = null) => isset($colMap[$key]) ? $sheet->getCell($colMap[$key] . $r)->getValue() : $default;

                // file_put_contents(storage_path('logs/live_sheet_parse_v.log'), "Live sheet col map: {$sku} = {$getVal('weight')}\n", FILE_APPEND);

                $rows[] = [
                    'sno'             => $getVal('sno'),
                    'vendor_sku'      => $sku,
                    'sap_code'        => $getVal('sap_code'),
                    'vendor_wsp'      => $getVal('vendor_wsp'),
                    'barcode'         => $getVal('barcode'),
                    'product_name'    => $getVal('product_name'),
                    'description'     => $getVal('description'),
                    'specification'   => $getVal('specification'),
                    'hsn_code'        => $getVal('hsn_code'),
                    'duty_percent'    => trim(str_replace('%', '', $getVal('duty_percent'))),
                    'length'          => $getVal('length'),
                    'width'           => $getVal('width'),
                    'height'          => $getVal('height'),
                    'weight'          => $getVal('weight'),
                    'material'        => $getVal('material'),
                    'other_material'  => $getVal('other_material'),
                    'color'           => $getVal('color'),
                    'finish'          => $getVal('finish'),
                    'category'        => $getVal('category'),
                    'sub_category'    => $getVal('sub_category'),
                    'qty_inner_pack'  => $getVal('qty_inner_pack'),
                    'inner_length'    => $getVal('inner_length'),
                    'inner_width'     => $getVal('inner_width'),
                    'inner_height'    => $getVal('inner_height'),
                    'inner_weight'      => $getVal('inner_weight'),
                    'qty_master_pack' => $getVal('qty_master_pack'),
                    'master_length'   => $getVal('master_length'),
                    'master_width'    => $getVal('master_width'),
                    'master_height'   => $getVal('master_height'),
                    'master_weight' => $getVal('master_weight'),
                    'no_of_master_carton' => $getVal('no_of_master_carton'),
                    'qty_offered'     => $getVal('qty_offered'),
                    'vendor_fob'      => $getVal('vendor_fob'),

                ];
            }
        } catch (\Exception $e) {
            \Log::error('Live sheet parse error: ' . $e->getMessage());

            return ['error' => 'Failed to parse Excel file: ' . $e->getMessage()];
        }

        return $rows;
    }
    public function uploadInspection(Request $request, Consignment $consignment)
    {
        $validated = $request->validate([
            'inspection_type'    => 'required|in:inline,midline,final',
            'report'             => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,ppt,pptx|max:20480',
            'commercial_invoice' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx|max:20480',
            'packing_list'       => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx|max:20480',
            'result'             => 'nullable|string',
            'remarks'            => 'nullable|string',
        ]);

        $folder = 'inspection-reports/' . $consignment->id;

        $data = [
            'consignment_id'     => $consignment->id,
            'inspection_type'    => $validated['inspection_type'],
            'report_file'        => $request->file('report')->store($folder, FileStorage::disk()),
            'report_name'        => $request->file('report')->getClientOriginalName(),
            'result'             => $validated['result'] ?? null,
            'remarks'            => $validated['remarks'] ?? null,
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

        \Log::channel('daily')->info('Vendor inspection upload', $data);

        \App\Models\InspectionReport::create($data);

        \App\Models\ActivityLog::log('uploaded', 'inspection', $consignment, null, [
            'type' => $request->inspection_type,
            'result' => $request->result
        ], ucfirst($request->inspection_type) . ' inspection uploaded by vendor.');

        return back()->with('success', 'Inspection report uploaded successfully.');
    }

    public function salesReport(Request $request)
    {
        $vendor = auth()->user()->vendor;
        $activeCompany = session('active_company');

        // Get shipped orders with vendor's items
        $orders = Order::withoutGlobalScopes()
            ->whereIn('status', ['shipped', 'delivered'])
            ->where('company_code', $activeCompany)
            ->whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id)->where('shipped_qty', '>', 0))
            ->with([
                'salesChannel',
                'items' => fn($q) => $q->where('vendor_id', $vendor->id)
                    ->where('shipped_qty', '>', 0)
                    ->with(['product' => fn($pq) => $pq->withoutGlobalScopes()])
            ])
            ->when($request->month, fn($q, $v) => $q->whereMonth('order_date', $v))
            ->when($request->year, fn($q, $v) => $q->whereYear('order_date', $v))
            ->latest('order_date')
            ->paginate(25);

        // Build FIFO queue
        $vendorLiveSheets = \App\Models\LiveSheet::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('company_code', $activeCompany)
            ->where('status', 'locked')
            ->orderBy('approved_at', 'asc')
            ->with(['items' => fn($q) => $q->select('id', 'live_sheet_id', 'product_id', 'quantity', 'unit_price', 'product_details')])
            ->get();

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

        // Deduct prior sold using shipped_qty
        $priorSold = \App\Models\OrderItem::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('shipped_qty', '>', 0)
            ->whereHas('order', fn($q) => $q->withoutGlobalScopes()
                ->where('company_code', $activeCompany)
                ->whereIn('status', ['shipped', 'delivered']))
            ->select('product_id', \DB::raw('SUM(shipped_qty) as shipped'))
            ->groupBy('product_id')
            ->pluck('shipped', 'product_id');

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

        // Build line items per order
        $orderLineItems = [];
        foreach ($orders as $order) {
            $orderTotal = 0;
            $orderShippedQty = 0;
            foreach ($order->items as $item) {
                $product = $item->product;
                if (!$product) {
                    continue;
                }
                $pid = $product->id;
                $shippedQty = intval($item->shipped_qty);
                if ($shippedQty <= 0) {
                    continue;
                }

                $qtyToAllocate = $shippedQty;
                $itemSale = 0;
                $itemComm = 0;

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
                        $itemSale += $batchSale;
                        $itemComm += round(($batch['commission'] / 100) * $batchSale, 2);
                        $batch['remaining_qty'] -= $allocate;
                        $qtyToAllocate -= $allocate;
                    }
                    unset($batch);
                }

                if ($qtyToAllocate > 0) {
                    $fallbackWsp = floatval($product->vendor_wsp ?? $product->fob_price ?? 0);
                    $itemSale += round($fallbackWsp * $qtyToAllocate, 2);
                }

                $orderTotal += $itemSale;
                $orderShippedQty += $shippedQty;
            }
            $orderLineItems[$order->id] = [
                'sale_amount'  => round($orderTotal, 2),
                'shipped_qty'  => $orderShippedQty,
            ];
        }

        // Stats
        $totalSales = array_sum(array_column($orderLineItems, 'sale_amount'));
        $totalShippedQty = array_sum(array_column($orderLineItems, 'shipped_qty'));

        return view('vendor.sales.index', compact('orders', 'vendor', 'totalSales', 'totalShippedQty', 'orderLineItems'));
    }
    public function salesReportBAK(Request $request)
    {
        $vendor = auth()->user()->vendor;

        $activeCompany = session('active_company');

        $orders = Order::where('status', 'shipped')
            ->whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))
            ->with(['salesChannel', 'receivable', 'items' => fn($q) => $q->where('vendor_id', $vendor->id)->with('product')])
            ->when($activeCompany, fn($q) => $q->whereHas('salesChannel', fn($sq) => $sq->where('company_code', $activeCompany)))
            ->get();


        // Before the loop — build FIFO commission map per product for this vendor
        $vendorLiveSheets = \App\Models\LiveSheet::where('vendor_id', $vendor->id)
            ->where('status', 'locked') // Only consider locked sheets for commission (approved but not yet paid out)
            //  ->when($activeCompany, fn($q) => $q->whereHas('salesChannel', fn($sq) => $sq->where('company_code', $activeCompany)))
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
        $priorSold = \App\Models\OrderItem::where('vendor_id', $vendor->id)
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
                //  print_r($item->toArray());
                $product = $item->product;
                $pid = $product->id;
                $qty = intval($item->shipped_qty);
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

                        $details[] = "{$allocate}u x \${$batch['vendor_wsp']} @ {$batch['commission']}%";

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
                    $details[] = "{$qtyToAllocate}u x \${$fallbackWsp} @ 0%";
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
        // exit;
        //print_r($lineItems->toArray());exit;

        $orders = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))
            ->with('salesChannel', 'items')
            ->when($activeCompany, fn($q) => $q->whereHas('salesChannel', fn($sq) => $sq->where('company_code', $activeCompany)))
            ->when($request->month, fn($q, $v) => $q->whereMonth('order_date', $v))
            ->when($request->year, fn($q, $v) => $q->whereYear('order_date', $v))
            ->latest('order_date')->paginate(25);

        $totalSales = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))
            ->when($activeCompany, fn($q) => $q->whereHas('salesChannel', fn($sq) => $sq->where('company_code', $activeCompany)))
            ->when($request->month, fn($q, $v) => $q->whereMonth('order_date', $v))
            ->when($request->year, fn($q, $v) => $q->whereYear('order_date', $v))
            ->sum('total_amount');

        return view('vendor.sales.index', compact('orders', 'vendor', 'totalSales', 'lineItems'));
    }
    public function grn(Request $request)
    {
        $user = auth()->user();
        // User's allowed company codes
        $activeCompany = session('active_company');

        $vendor = $user->vendor;
        $vendorProductIds = $vendor->products()
            ->when($activeCompany, fn($q) => $q->where('company_code', $activeCompany))
            ->pluck('id')->toArray();

        // Get consignment IDs for this vendor
        $consignmentIds = Consignment::where('vendor_id', $vendor->id)
            ->when($activeCompany, fn($q) => $q->where('company_code', $activeCompany))
            ->pluck('id');

        // Get shipment IDs linked to those consignments
        $shipmentIds = \DB::table('shipment_consignments')
            ->whereIn('consignment_id', $consignmentIds)
            ->pluck('shipment_id');

        $grns = \App\Models\Grn::with('shipment', 'warehouse', 'items.product')
            ->whereIn('shipment_id', $shipmentIds)
            ->when($activeCompany, function ($query) use ($activeCompany) {
                $query->where('company_code', $activeCompany);
            })
            ->latest('receipt_date')
            ->paginate(20);

        // Calculate vendor-specific stats per GRN
        $totalExpected = 0;
        $totalReceived = 0;
        $totalDamaged = 0;
        $totalMissing = 0;
        $totalExcess = 0;

        $grns->getCollection()->transform(function ($grn) use ($vendorProductIds, &$totalExpected, &$totalReceived, &$totalDamaged, &$totalMissing, &$totalExcess) {
            $myItems = $grn->items->filter(fn($i) => in_array($i->product_id, $vendorProductIds));
            $grn->vendor_expected = $myItems->sum('expected_quantity');
            $grn->vendor_received = $myItems->sum('received_quantity');
            $grn->vendor_damaged  = $myItems->sum('damaged_quantity');
            $grn->vendor_missing  = $myItems->sum('missing_quantity');
            $grn->vendor_excess   = $myItems->sum('excess_quantity');
            $totalExpected += $grn->vendor_expected;
            $totalReceived += $grn->vendor_received;
            $totalDamaged  += $grn->vendor_damaged;
            $totalMissing  += $grn->vendor_missing;
            $totalExcess   += $grn->vendor_excess;
            return $grn;
        });

        $stats = [
            'total_grns'     => $grns->total(),
            'total_expected' => $totalExpected,
            'total_received' => $totalReceived,
            'total_damaged'  => $totalDamaged,
            'total_missing'  => $totalMissing,
            'total_excess'   => $totalExcess,
        ];

        return view('vendor.grn.index', compact('grns', 'vendor', 'stats'));
    }

    public function showGrn(\App\Models\Grn $grn)
    {
        $vendor = auth()->user()->vendor;
        $activeCompany = session('active_company');

        // Verify this GRN belongs to this vendor's consignments
        $consignmentIds = Consignment::where('vendor_id', $vendor->id)
            ->when($activeCompany, fn($q) => $q->where('company_code', $activeCompany))
            ->pluck('id');
        $shipmentIds = \DB::table('shipment_consignments')
            ->whereIn('consignment_id', $consignmentIds)
            ->pluck('shipment_id');

        if (!$shipmentIds->contains($grn->shipment_id)) {
            abort(403, 'You do not have access to this GRN.');
        }

        $grn->load('shipment', 'warehouse', 'uploader', 'items.product', 'items.consignment');

        // Filter items to show only this vendor's products
        $vendorProductIds = $vendor->products()
            ->when($activeCompany, fn($q) => $q->where('company_code', $activeCompany))
            ->pluck('id')->toArray();
        $vendorItems = $grn->items->filter(fn($item) => in_array($item->product_id, $vendorProductIds));

        $itemStats = [
            'total_expected' => $vendorItems->sum('expected_quantity'),
            'total_received' => $vendorItems->sum('received_quantity'),
            'total_damaged'  => $vendorItems->sum('damaged_quantity'),
            'total_missing'  => $vendorItems->sum('missing_quantity'),
            'total_excess'   => $vendorItems->sum('excess_quantity'),
        ];

        return view('vendor.grn.show', compact('grn', 'vendor', 'vendorItems', 'itemStats'));
    }

    public function rateCard()
    {
        $user = auth()->user();

        $activeCompany = session('active_company');
        $vendor = $user->vendor;

        $rateCard = \App\Models\VendorRateCard::where('vendor_id', $vendor->id)
            ->where('status', 'approved')
            ->when($activeCompany, function ($query) use ($activeCompany) {
                $query->where('company_code', $activeCompany);
            })
            ->orderByDesc('version')
            ->first();

        // Get all versions for history
        $history = \App\Models\VendorRateCard::where('vendor_id', $vendor->id)
            ->with('warehouse')
            ->when($activeCompany, function ($query) use ($activeCompany) {
                $query->where('company_code', $activeCompany);
            })
            ->orderByDesc('version')
            ->get();

        $currency = match ($vendor->company_code ?? '2100') {
            '2000' => 'INR',
            '2200' => 'EUR',
            '2400' => 'GBP',
            default => 'USD',
        };
        $sym = match ($currency) {
            'INR' => '₹',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };
        $warehouses = \App\Models\Warehouse::active()->get();
        return view('vendor.rate-card', compact('vendor', 'rateCard', 'history', 'currency', 'sym', 'warehouses'));
    }
    public function inventory(Request $request)
    {
        $user = auth()->user();
        $activeCompany = session('active_company');

        $vendor = $user->vendor;

        $inventory = \App\Models\Inventory::with('product', 'warehouse', 'grn')
            ->whereHas('product', fn($q) => $q->where('vendor_id', $vendor->id))
            ->when($request->warehouse_id, fn($q, $v) => $q->where('warehouse_id', $v))
            ->when(!$user->isAdmin() && !empty($activeCompany), function ($query) use ($activeCompany) {
                $query->where('company_code', $activeCompany);
            })
            ->latest('received_date')
            ->paginate(30);

        $warehouses = \App\Models\Warehouse::orderBy('name')->get(['id', 'name']);

        // Stats
        $stats = [
            'total_skus'     => \App\Models\Inventory::whereHas('product', fn($q) => $q->where('vendor_id', $vendor->id))->distinct('product_id')->count('product_id'),
            'total_qty'      => (int) \App\Models\Inventory::whereHas('product', fn($q) => $q->where('vendor_id', $vendor->id))->sum('quantity'),
            'available_qty'  => (int) \App\Models\Inventory::whereHas('product', fn($q) => $q->where('vendor_id', $vendor->id))->sum('available_quantity'),
            'reserved_qty'   => (int) \App\Models\Inventory::whereHas('product', fn($q) => $q->where('vendor_id', $vendor->id))->sum('reserved_quantity'),
        ];

        return view('vendor.inventory.index', compact('inventory', 'vendor', 'warehouses', 'stats'));
    }

    public function chargebacks()
    {
        $vendor = auth()->user()->vendor;
        $activeCompany = session('active_company');

        $chargebacks = Chargeback::where('vendor_id', $vendor->id)->with('order.salesChannel')
            ->when(!$vendor->user->isAdmin() && !empty($activeCompany), function ($query) use ($activeCompany) {
                $query->whereHas('order.salesChannel', fn($q) => $q->where('company_code', $activeCompany));
            })
            ->latest()->paginate(20);
        return view('vendor.chargebacks.index', compact('chargebacks', 'vendor'));
    }

    public function payoutsOLD()
    {
        $vendor = auth()->user()->vendor;
        $activeCompany = session('active_company');

        $payouts = VendorPayout::where('vendor_id', $vendor->id)
            ->where('company_code', $activeCompany)
            ->orderByDesc('payout_year')->orderByDesc('payout_month')->paginate(12);

        $warehouseCharges = WarehouseCharge::where('vendor_id', $vendor->id)
            ->where('company_code', $activeCompany)
            ->latest()->take(10)->get();

        $vendorMonthlyCharges = VendorMonthlyCharge::where('vendor_id', $vendor->id)
            ->where('company_code', $activeCompany)
            ->latest()->take(10)->get();

        return view('vendor.payouts.index', compact('payouts', 'vendor', 'warehouseCharges', 'vendorMonthlyCharges'));
    }

    public function payouts()
    {
        $vendor = auth()->user()->vendor;
        $activeCompany = session('active_company');

        $payouts = VendorPayout::where('vendor_id', $vendor->id)
            ->where('company_code', $activeCompany)
            ->orderByDesc('payout_year')
            ->orderByDesc('payout_month')
            ->paginate(12);

        $vendorMonthlyCharges = \App\Models\VendorMonthlyCharge::where('vendor_id', $vendor->id)
            ->where('company_code', $activeCompany)
            ->where('status', 'approved')
            ->with('warehouse', 'grn')
            ->latest()
            ->take(10)
            ->get();

        return view('vendor.payouts.index', compact('payouts', 'vendor', 'vendorMonthlyCharges'));
    }

    public function showPayout(VendorPayout $payout)
    {
        $vendor = auth()->user()->vendor;
        $activeCompany = session('active_company');

        // Security: vendor can only see their own payouts
        if ($payout->vendor_id !== $vendor->id) {
            return redirect()->route('vendor.payouts')->with('error', 'Unauthorized.');
        }
        if ($activeCompany && $payout->company_code !== $activeCompany) {
            return redirect()->route('vendor.payouts')->with('error', 'This payout does not belong to your active company.');
        }

        $payout->load('vendor');

        // Read from saved snapshot
        $snapshot = $payout->calculation_snapshot;

        if (!empty($snapshot)) {
            $lineItems = collect($snapshot['line_items'] ?? [])->map(fn($i) => (object) $i);
            $payoutSummary = $snapshot['summary'] ?? [];
        } else {
            // Fallback: recalculate live
            $service = new \App\Services\VendorPayoutService();
            $data = $service->buildPayoutData($vendor->id, $payout->company_code, $payout->payout_month, $payout->payout_year);
            $lineItems = collect($data['line_items'])->map(fn($i) => (object) $i);
            $payoutSummary = $data['summary'];
        }

        // Warehouse charges
        $warehouseCharges = \App\Models\VendorMonthlyCharge::where('vendor_id', $vendor->id)
            ->where('company_code', $payout->company_code)
            ->where('charge_month', $payout->payout_month)
            ->where('charge_year', $payout->payout_year)
            ->with('warehouse')
            ->get();

        // Chargebacks
        $chargebacks = \App\Models\Chargeback::where('vendor_id', $vendor->id)
            ->whereHas('order', fn($q) => $q->where('company_code', $payout->company_code))
            ->where('status', 'confirmed')
            ->whereMonth('confirmed_at', $payout->payout_month)
            ->whereYear('confirmed_at', $payout->payout_year)
            ->with('order')
            ->get();

        return view('vendor.payouts.show', compact('payout', 'lineItems', 'payoutSummary', 'warehouseCharges', 'chargebacks'));
    }

    public function uploadInvoice(Request $request, VendorPayout $payout)
    {
        $request->validate(['invoice' => 'required|file|mimes:pdf|max:10240', 'vendor_invoice_number' => 'required|string|max:100']);
        $path = $request->file('invoice')->store('vendor-invoices/' . $payout->vendor_id, FileStorage::disk());
        $payout->update(['vendor_invoice_file' => $path, 'vendor_invoice_number' => $request->vendor_invoice_number, 'vendor_invoice_date' => now(), 'status' => 'invoice_received']);
        return back()->with('success', 'Invoice uploaded. Invoice #: ' . $request->vendor_invoice_number);
    }

    /**
     * Download consignment contract PDF
     */
    public function downloadContract()
    {
        // $path = public_path('downloads/Consignment_Contract_ExpoBazaar.pdf');

        $activeCompany = session('active_company');
        $contractFile = 'EU_Contract.pdf';
        if ($activeCompany === '2100') {
            $contractFile = 'US_Contract.pdf';
        }

        $path = storage_path('app/public/downloads/' . $contractFile);

        if (!file_exists($path)) {
            return back()->with('error', 'Contract file not found. Please contact support.');
        }

        return response()->download($path, $activeCompany . '_' . $contractFile);
    }

    /**
     * Save Ex-Factory Date and/or Final Inspection Date inline from the index grid.
     * Called via AJAX POST from the live sheets index page.
     *
     * POST /vendor/live-sheets/{liveSheet}/dates
     */
    public function saveLiveSheetDates(Request $request, LiveSheet $liveSheet)
    {
        $vendor = auth()->user()->vendor;
        if ($liveSheet->vendor_id !== $vendor->id) {
            abort(403);
        }
        // if ($liveSheet->is_locked) {
        //     return response()->json(['success' => false, 'message' => 'Live sheet is locked.'], 403);
        // }

        $today      = now()->toDateString();
        $maxExFactory = now()->addDays(90)->toDateString();

        $request->validate([
            // 'ex_factory_date'        => "nullable|date|after_or_equal:{$today}|before_or_equal:{$maxExFactory}",
            'ex_factory_date' => 'nullable|date',
            'final_inspection_date'  => 'nullable|date',
            'factory_location'       => 'nullable|string|max:500',
        ]);

        $data = [];

        // ── Ex-Factory Date ───────────────────────────────────────────────────────
        if ($request->has('ex_factory_date')) {
            $data['ex_factory_date'] = $request->ex_factory_date ?: null;
        }

        // ── Final Inspection Date — must be within 7 days of ex_factory_date ─────
        if ($request->has('final_inspection_date')) {
            $inspDate  = $request->final_inspection_date;
            $exFactory = $request->ex_factory_date
                ?? $liveSheet->ex_factory_date?->toDateString();


            // $maxInspection = $exFactory ?? $maxExFactory;
            // $minInspection = $exFactory
            //                 ->copy()
            //                 ->subDays(8)        // You can change 3 to any number 1-7
            //                 ->toDateString();



            if ($inspDate && $exFactory) {
                //   $maxInspection = \Carbon\Carbon::parse($exFactory)->addDays(7)->toDateString();

                $minInspection = \Carbon\Carbon::parse($exFactory)->subDays(8)->toDateString();

                if ($inspDate > $exFactory || $inspDate < $minInspection) {
                    return response()->json([
                        'success' => false,
                        'message' => "Final Inspection Date must be between  {$minInspection} and {$exFactory}.",
                    ], 422);
                }
            }

            $data['final_inspection_date'] = $inspDate ?: null;
        }

        // ── Factory Location ──────────────────────────────────────────────────────
        if ($request->has('factory_location')) {
            $data['factory_location'] = $request->factory_location ?: null;
        }
        \Log::channel('daily')->info('Live sheet dates update', $data);
        if (!empty($data)) {
            $liveSheet->update($data);
            $data['message'] = 'dates updated';
        }

        return response()->json(['success' => true, $data]);
    }
    public function inspectionReports(Request $request)
    {
        $vendor = auth()->user()->vendor;

        $activeCompany = session('active_company');

        $reports = \App\Models\InspectionReport::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))
            ->with('consignment', 'uploader')
            ->when($activeCompany, fn($q) => $q->whereHas('consignment', fn($cq) => $cq->where('company_code', $activeCompany)))
            ->when($request->type, fn($q, $v) => $q->where('inspection_type', $v))
            ->when($request->result, fn($q, $v) => $q->where('result', $v))
            ->when($request->consignment_id, fn($q, $v) => $q->where('consignment_id', $v))
            ->latest()->paginate(20);

        $consignments = $vendor->consignments()
            ->when($activeCompany, function ($q) use ($activeCompany) {
                $q->where('company_code', $activeCompany);
            })
            ->latest()->get();

        $stats = [
            'total'    => \App\Models\InspectionReport::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))
                ->when($activeCompany, fn($q) => $q->whereHas('consignment', fn($cq) => $cq->where('company_code', $activeCompany)))
                ->count(),
            'passed'   => \App\Models\InspectionReport::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))->where('result', 'passed')
                ->when($activeCompany, fn($q) => $q->whereHas('consignment', fn($cq) => $cq->where('company_code', $activeCompany)))
                ->count(),

            'failed'   => \App\Models\InspectionReport::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))->where('result', 'failed')
                ->when($activeCompany, fn($q) => $q->whereHas('consignment', fn($cq) => $cq->where('company_code', $activeCompany)))
                ->count(),

            'conditional' => \App\Models\InspectionReport::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))->where('result', 'conditional')
                ->when($activeCompany, fn($q) => $q->whereHas('consignment', fn($cq) => $cq->where('company_code', $activeCompany)))
                ->count(),
        ];

        return view('vendor.inspections.index', compact('reports', 'consignments', 'stats', 'vendor'));
    }

    // ═══════════════════════════════════════════════════════════
    //   OFFER SHEET INLINE UPDATE (Vendor)
    // ═══════════════════════════════════════════════════════════

    public function updateOfferSheetItems(Request $request, \App\Models\OfferSheet $offerSheet)
    {
        if ($offerSheet->status === 'approved' || $offerSheet->status === 'converted') {
            return response()->json(['error' => 'Cannot edit approved/converted offer sheet.'], 422);
        }

        $request->validate([
            'items'             => 'required|array',
            'items.*.id'        => 'required|exists:offer_sheet_items,id',
            'items.*.vendor_price' => 'nullable|numeric|min:0',
            'items.*.length'    => 'nullable|numeric|min:0',
            'items.*.width'     => 'nullable|numeric|min:0',
            'items.*.height'    => 'nullable|numeric|min:0',
            'items.*.weight'    => 'nullable|numeric|min:0',
            'items.*.material'  => 'nullable|string|max:200',
            'items.*.color'     => 'nullable|string|max:100',
            'items.*.finish'    => 'nullable|string|max:100',
            'items.*.category'  => 'nullable|string|max:100',
            'items.*.sub_category' => 'nullable|string|max:100',
            'items.*.comments'  => 'nullable|string|max:500',
        ]);

        $updated = 0;
        foreach ($request->items as $data) {
            $item = \App\Models\OfferSheetItem::where('id', $data['id'])
                ->where('offer_sheet_id', $offerSheet->id)->first();
            if (!$item) {
                continue;
            }

            $details = $item->product_details ?? [];
            foreach (['length', 'width', 'height', 'weight', 'material', 'color', 'finish', 'category', 'sub_category', 'comments'] as $field) {
                if (isset($data[$field])) {
                    $details[$field] = $data[$field];
                }
            }

            $item->update([
                'vendor_price'    => $data['vendor_price'] ?? $item->vendor_price,
                'product_details' => $details,
            ]);
            $updated++;
        }

        return response()->json(['success' => true, 'message' => "{$updated} item(s) updated."]);
    }
}
