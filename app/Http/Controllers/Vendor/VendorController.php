<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\{Vendor, Consignment, VendorPayout, Chargeback, VendorDocument, Category, LiveSheet, OfferSheet, OfferSheetItem, Order, WarehouseCharge, Product};
use App\Services\{DashboardService, VendorService, SourcingService};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
        if (!$vendor) {
            return redirect()->route('vendor.kyc');
        }
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
        $vendor = auth()->user()->vendor;

        // Block changes after finance approval
        if ($vendor && $vendor->kyc_status === 'approved') {
            return back()->with('error', 'KYC has already been approved by Finance. No changes allowed.');
        }

        $request->validate([
            'gst_number'              => 'required|string|size:15|regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/',
            'rex_number'              => 'nullable|string|size:20|regex:/^[A-Za-z0-9]{20}$/',
            'company_name'            => 'required|string|max:255',
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
            'street_address.required'              => 'Street address is required.',
            'province_state.required'              => 'Province / State is required.',
        ]);

        // Update vendor fields
        try {
            $vendor->update($request->only([
                'gst_number',
                'company_name',
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
                    $path = $file->store('vendor-kyc/' . $vendor->id, 'public');
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
                    $path = $file->store('vendor-kyc/' . $vendor->id, 'public');
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
    public function submitKycBACKUP(Request $request)
    {
        $vendor = auth()->user()->vendor;

        // Block changes after finance approval
        if ($vendor && $vendor->kyc_status === 'approved') {
            return back()->with('error', 'KYC has already been approved by Finance. No changes allowed.');
        }

        $request->validate([
            'gst_number'              => [
                'required',
                'string',
                'size:15',
                'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'
            ],
            'rex_number'              => [
                'required',
                'string',
                'size:20',
                'regex:/^[A-Za-z0-9]{20}$/'
            ],
            'company_name'            => 'required|string|max:255',
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
            'msme_number'             => 'string|max:50',
            'documents.gst_certificate'   => $this->docRequired($vendor, 'gst_certificate'),
            'documents.cancelled_cheque'  => $this->docRequired($vendor, 'cancelled_cheque'),
            'documents.signed_contract'   => $this->docRequired($vendor, 'signed_contract'),
            'documents.iec_certificate'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.msme_certificate'  => $this->docRequired($vendor, 'msme_certificate'),
            'documents.other.*'           => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'gst_number.regex'                    => 'Invalid GST Number format.',
            'rex_number.regex'                    => 'REX must be exactly 20 alphanumeric characters.',
            'bank_swift_code.required'            => 'SWIFT / BIC code is mandatory.',
            'bank_swift_code.min'                 => 'SWIFT code must be at least 8 characters.',
            'documents.cancelled_cheque.required'  => 'Cancelled cheque / bank proof is mandatory.',
            'documents.signed_contract.required'   => 'Signed consignment contract is mandatory.',
            'documents.msme_certificate.required'  => 'MSME certificate is mandatory.',
            'documents.gst_certificate.required'   => 'GST certificate is mandatory.',
            'street_address.required'              => 'Street address is required.',
            'province_state.required'              => 'Province / State is required.',
        ]);

        // Update vendor fields
        try {
            $vendor->update($request->only([
                'gst_number',
                'company_name',
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
                    $path = $file->store('vendor-kyc/' . $vendor->id, 'public');
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
                    $path = $file->store('vendor-kyc/' . $vendor->id, 'public');
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
        $vendor = auth()->user()->vendor;
        $sheets = $vendor->offerSheets()->with('items')->latest()->paginate(20);
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

        $vendor = auth()->user()->vendor;
        $file = $request->file('offer_file');
        $path = $file->store('offer-sheets/' . $vendor->id, 'public');

        // Parse Excel using PhpSpreadsheet / openpyxl-compatible reader
        $products = $this->parseOfferSheetExcel($file->getRealPath());

        if (empty($products)) {
            return back()->with('error', 'No valid products found in the uploaded file. Please use the provided template.');
        }

        // Create offer sheet
        $offerSheet = OfferSheet::create([
            'offer_sheet_number' => OfferSheet::generateNumber($vendor->company_code),
            'vendor_id'          => $vendor->id,
            'company_code'       => $vendor->company_code,
            'status'             => 'submitted',
            'total_products'     => count($products),
            'selected_products'  => 0,
        ]);

        // Create items from parsed Excel data
        foreach ($products as $p) {

            // Find or create category
            $categoryId = null;
            if (!empty($p['category'])) {
                $cat = Category::firstOrCreate(
                    ['slug' => Str::slug($p['category'])],
                    ['name' => $p['category'], 'sort_order' => 0]
                );
                $categoryId = $cat->id;

                // Sub-category as child
                if (!empty($p['sub_category'])) {
                    $subCat = Category::firstOrCreate(
                        ['slug' => Str::slug($p['sub_category'])],
                        ['name' => $p['sub_category'], 'parent_id' => $cat->id, 'sort_order' => 0]
                    );
                    $categoryId = $subCat->id;
                }
            }
            // Use image extracted from Excel, or separate upload
            $thumbnail = $p['image_path'] ?? null;
            if (!$thumbnail && $request->hasFile('thumbnails.' . ($p['sno'] ?? ''))) {
                $thumbnail = $request->file('thumbnails.' . $p['sno'])->store('offer-thumbnails/' . $vendor->id, 'public');
            }

            // Find existing product by SKU (unique index is on sku alone, not sku+vendor_id)
            $product = Product::where('sku', $p['vendor_sku'])->first();
            if (!$product) {
                $product = Product::create([
                    'sku'          => $p['vendor_sku'],
                    'category_id'  => $categoryId,
                    'vendor_id'    => $vendor->id,
                    'name'         => $p['product_name'],
                    'company_code' => $vendor->company_code,
                    'length_cm'   => isset($p['length']) ? round($p['length'] * 2.54, 2) : null,
                    'width_cm'    => isset($p['width']) ? round($p['width'] * 2.54, 2) : null,
                    'height_cm'   => isset($p['height']) ? round($p['height'] * 2.54, 2) : null,
                    'weight_grams' => isset($p['weight']) ? round($p['weight'] * 453.592, 2) : null,
                    'vendor_price'   => $p['vendor_fob'] ?? 0,
                    'status'       => 'draft',
                ]);
            } elseif ($product->vendor_id !== $vendor->id) {
                // SKU already exists but belongs to another vendor                
                // \Log::error('Offer sheet error: ' . "SKU '{$p['vendor_sku']}' already exists under another vendor");
                \Log::channel('offer_sheet')->error('Error', [
                    'error' => "SKU '{$p['vendor_sku']}' already exists under another vendor",
                ]);
                return back()->with('error', "SKU '{$p['vendor_sku']}' already exists under another vendor. Please use a unique SKU.");

                //throw new \Exception("SKU '{$p['vendor_sku']}' already exists under another vendor. Please use a unique SKU.");
            }

            // // Auto-create or find product
            // $product = Product::firstOrCreate(
            //     ['sku' => $p['vendor_sku'], 'vendor_id' => $vendor->id],
            //     [
            //         'name'         => $p['product_name'],
            //         'company_code' => $vendor->company_code,
            //         'status'       => 'draft',
            //     ]
            // );



            file_put_contents("storage/logs/OfferSheetItem.csv." . date("Y-m-d") . ".log", print_r($p, true) . "\n", FILE_APPEND);

            OfferSheetItem::create([
                'offer_sheet_id' => $offerSheet->id,
                'product_id'     => $product->id,
                'product_name'   => $p['product_name'],
                'product_sku'    => $p['vendor_sku'],
                'category_id'    => $categoryId,
                'vendor_price'   => $p['vendor_fob'] ?? 0,
                'currency'       => 'USD',
                'thumbnail'      => $thumbnail,
                'product_details' => [
                    'sno'              => $p['sno'] ?? null,
                    'length_inches'    => $p['length'] ?? null,
                    'width_inches'     => $p['width'] ?? null,
                    'height_inches'    => $p['height'] ?? null,
                    'weight_grams'     => $p['weight'] ?? null,
                    'material'         => $p['material'] ?? null,
                    'color'            => $p['color'] ?? null,
                    'finish'           => $p['finish'] ?? null,
                    'category'         => $p['category'] ?? null,
                    'sub_category'     => $p['sub_category'] ?? null,
                    'comments'         => $p['comments'] ?? null,
                ],
                'is_selected' => false,
            ]);
        }

        $count = count($products);
        return redirect()->route('vendor.offer-sheets.show', $offerSheet)
            ->with('success', "Offer sheet uploaded with {$count} products. Pending Sourcing team review.");
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
            file_put_contents("storage/logs/products.csv." . date("Y-m-d") . ".log", print_r($products, true) . "\n", FILE_APPEND);
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
                file_put_contents("storage/logs/ExcelImages" . date('Y-m-d') . ".log", "Processing drawing at row {$row} with coordinates {$coordinates} \t {$filename}\n", FILE_APPEND);
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
                        \Storage::disk('public')->put($destPath, $imageData);
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
                            \Storage::disk('public')->put($destPath, $imageData);
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
                file_put_contents("storage/logs/ImagesFromZip" . date('Y-m-d') . ".log", "{$mediaFilename}\t{$destPath}\n", FILE_APPEND);

                \Storage::disk('public')->put($destPath, $imageData);
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
                    \Storage::disk('public')->put($destPath, $imageData);
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
    public function downloadOfferSheetTemplate()
    {
        $path = public_path('downloads/Offer_Sheet_US.xlsx');

        if (!file_exists($path)) {
            // Fallback: generate CSV template
            $csv = "S.no,Vendor SKU,Product Name,Product Image,Product Length (In Inches),Product Width (In Inches),Product Height (In Inches),Product Weight (In Gram),Material Composition,Color,Product Finish,Category,Sub Category,Vendor FOB Mumbai ($),Comments\n";
            $csv .= "1,EB123,Glass Vase,,10,10,2,200,Glass,Clear,Glossy,Home & Décor,Décor,1,\n";

            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="Offer_Sheet_Template.csv"',
            ]);
        }

        return response()->download($path, 'Offer_Sheet_Template.xlsx');
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

        $csv = "S.no,Vendor SKU,Product Name,Product Length (Inches),Product Width (Inches),Product Height (Inches),Product Weight (Grams),Material,Color,Finish,Category,Sub Category,Vendor FOB ($),Comments,Selection\n";

        foreach ($offerSheet->items as $item) {
            $d = $item->product_details ?? [];
            $csv .= implode(',', [
                $d['sno'] ?? $item->id,
                '"' . str_replace('"', '""', $item->product_sku) . '"',
                '"' . str_replace('"', '""', $item->product_name) . '"',
                $d['length_inches'] ?? '',
                $d['width_inches'] ?? '',
                $d['height_inches'] ?? '',
                $d['weight_grams'] ?? '',
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
            if ($item->thumbnail && \Storage::disk('public')->exists($item->thumbnail)) {
                \Storage::disk('public')->delete($item->thumbnail);
            }

            // Store new image
            $path = $request->file('image')->store('offer-thumbnails', 'public');

            // Save path to database
            $item->update(['thumbnail' => $path]);

            return response()->json([
                'success' => true,
                'image_url' => \Storage::url($path),
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
        //$consignments = $vendor->consignments()->with('liveSheet', 'grn', 'shipment')->latest()->paginate(20);

        $consignments = $vendor->consignments()->with('liveSheet', 'shipments')->latest()->paginate(20);
        return view('vendor.consignments.index', compact('consignments', 'vendor'));
    }

    public function liveSheets()
    {
        $vendor = auth()->user()->vendor;
        $liveSheets = LiveSheet::where('vendor_id', $vendor->id)
            ->with('consignment', 'offerSheet', 'items.product')
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
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.quantity'         => 'required|integer|min:1',
            'items.*.unit_price'       => 'required|numeric|min:0',
            //'items.*.cbm_per_unit'     => 'required|numeric|min:0',
            'items.*.weight_per_unit'  => 'nullable|numeric|min:0',
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

        $this->sourcingService->submitLiveSheet($liveSheet, $request->items);
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
                '2200' => 'NL',
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
                'total_value'          => $items->sum('total_price'),
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
        $vendor = auth()->user()->vendor;
        //print_r($liveSheet->toArray());exit;
        if ($liveSheet->vendor_id !== $vendor->id) {
            abort(403);
        }

        $currency = '€';
        if ($vendor->company_code == '2200') {
            $currency = '$';
        }

        $liveSheet->load('items.product');

        $headers = "S.no,Vendor SKU,Barcode,Product Name,Product Description (Min 100 words),Hsn & Hts Code,Duty %,Product Length (Inches),Product Width (Inches),Product Height (Inches),Product Weight (Gram),Material Composition,Other Material,Color,Product Finish,Category,Sub Category,Qty In Inner Pack,Inner Carton Length (Inches),Inner Carton Width (Inches),Inner Carton Height (Inches),Qty In Master Pack,Master Carton Length (Inches),Master Carton Width (Inches),Master Carton Height (Inches),Master Carton Weight (Kg),Qty Offered (Units/Sets),Vendor FOB Mumbai ({$currency})\n";

        $csv = $headers;
        foreach ($liveSheet->items as $idx => $item) {
            $p = $item->product;
            $d = $item->product_details ?? [];
            // echo $p->vendor_price;

            // print_r($item->toArray());
            // exit;
            $csv .= implode(',', [
                $idx + 1,
                '"' . str_replace('"', '""', $p->sku ?? '') . '"',
                '', // Barcode
                '"' . str_replace('"', '""', $p->name ?? '') . '"',
                '', // Description
                '', // HSN
                '', // Duty %
                $d['length_inches'] ?? '',
                $d['width_inches'] ?? '',
                $d['height_inches'] ?? '',
                $d['weight_grams'] ?? '',
                '"' . str_replace('"', '""', $d['material'] ?? '') . '"',
                '', // Other Material
                '"' . str_replace('"', '""', $d['color'] ?? '') . '"',
                '"' . str_replace('"', '""', $d['finish'] ?? '') . '"',
                '"' . str_replace('"', '""', $d['category'] ?? '') . '"',
                '"' . str_replace('"', '""', $d['sub_category'] ?? '') . '"',
                '', // Inner pack 
                '', //Inner Carton Length(In Inches)
                '', //Inner Carton Width(In Inches)
                '', //Inner Carton Height(In Inches)
                '', //Qty In Master Pack
                '', //Master Carton Length(In Inches)
                '', //Master Carton Width(In Inches)
                '', //Master Carton Height(In Inches)
                '', //Mastor Carton Weight in (Kg)
                '', //Qty Offered (Units/Sets)
                '"' . str_replace('"', '""', $p->vendor_price ?? '') . '"' //Vendor FOB Mumbai ($)                
            ]) . "\n";
        }

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
        $storedPath = $file->store('live-sheet-uploads/' . $vendor->id, 'public');
        $fullPath = storage_path('app/public/' . $storedPath);

        $parsed = $this->parseLiveSheetExcel($fullPath);

        if (empty($parsed)) {
            return back()->with('error', 'No valid data found. Please use the provided template.');
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

            // Calculate CBM
            $masterL = $row['master_length'] ?? 0;
            $masterW = $row['master_width'] ?? 0;
            $masterH = $row['master_height'] ?? 0;
            $masterCbm = ($masterL && $masterW && $masterH) ? ($masterL * $masterW * $masterH) / 61023 : 0;
            $qtyMaster = $row['qty_master_pack'] ?? 1;
            $finalQty = $row['final_qty'] ?? $row['qty_offered'] ?? $item->quantity;
            $totalMasterCartons = $qtyMaster > 0 ? ceil($finalQty / $qtyMaster) : 0;
            $cbmShipment = $totalMasterCartons * $masterCbm;
            $unitPrice = $row['vendor_fob'] ?? $item->unit_price;
            $finalFob = $row['final_fob'] ?? $unitPrice;
            $weightPerUnit = isset($row['weight_grams']) && $row['weight_grams'] > 0 ? round($row['weight_grams'] / 1000, 3) : $item->weight_per_unit;
            $masterWeight = $row['master_weight_kg'] ?? 0;

            // Track changes BEFORE updating
            try {
                $newDetails = [
                    'vendor_fob'  => $row['vendor_fob'] ?? null,
                    'qty_offered' => $row['qty_offered'] ?? null,
                    'final_qty'   => $finalQty,
                    'final_fob'   => $finalFob,
                    'barcode'     => $row['barcode'] ?? null,
                    'sap_code'    => $row['sap_code'] ?? null,
                ];
                \App\Models\LiveSheetItemChange::trackChanges($item, $newDetails, auth()->user(), 'vendor');
            } catch (\Exception $e) {
                \Log::warning('Vendor change tracking failed: ' . $e->getMessage());
            }

            $item->update([
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
                    'barcode'          => $row['barcode'] ?? null,
                    'description'      => $row['description'] ?? null,
                    'hsn_hts_code'     => $row['hsn_code'] ?? null,
                    'duty_percent'     => $row['duty_percent'] ?? null,
                    'length_inches'    => $row['length'] ?? null,
                    'width_inches'     => $row['width'] ?? null,
                    'height_inches'    => $row['height'] ?? null,
                    'weight_grams'     => $row['weight_grams'] ?? null,
                    'material'         => $row['material'] ?? null,
                    'other_material'   => $row['other_material'] ?? null,
                    'color'            => $row['color'] ?? null,
                    'finish'           => $row['finish'] ?? null,
                    'category'         => $row['category'] ?? null,
                    'sub_category'     => $row['sub_category'] ?? null,
                    'qty_inner_pack'   => $row['qty_inner_pack'] ?? null,
                    'inner_length'     => $row['inner_length'] ?? null,
                    'inner_width'      => $row['inner_width'] ?? null,
                    'inner_height'     => $row['inner_height'] ?? null,
                    'qty_master_pack'  => $row['qty_master_pack'] ?? null,
                    'master_length'    => $masterL,
                    'master_width'     => $masterW,
                    'master_height'    => $masterH,
                    'master_weight_kg' => $masterWeight,
                    'qty_offered'      => $row['qty_offered'] ?? null,
                    'vendor_fob'       => $row['vendor_fob'] ?? null
                ]),
            ]);

            // Update product master
            $item->product->update(array_filter([
                'vendor_price' => $finalFob ?: $unitPrice,
                'cbm'          => $qtyMaster > 0 ? round($masterCbm / $qtyMaster, 6) : null,
                'weight_kg'    => $weightPerUnit ?: null,
            ]));

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

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            // Build header map
            $colMap = [];
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $val = strtolower(trim(preg_replace('/\s+/', ' ', (string) $cell->getValue())));
                    $col = $cell->getColumn();
                    if (empty($val)) {
                        continue;
                    }

                    if (str_contains($val, 'vendor sku') || $val === 'sku') {
                        $colMap['vendor_sku'] = $col;
                    } elseif (str_contains($val, 'barcode')) {
                        $colMap['barcode'] = $col;
                    } elseif (str_contains($val, 'product name')) {
                        $colMap['product_name'] = $col;
                    } elseif (str_contains($val, 'description')) {
                        $colMap['description'] = $col;
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
                    } elseif (str_contains($val, 'product weight') || str_contains($val, 'weight')) {
                        $colMap['weight_grams'] = $col;
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
                    } elseif (str_contains($val, 'qty in master')) {
                        $colMap['qty_master_pack'] = $col;
                    } elseif (str_contains($val, 'master') && str_contains($val, 'length')) {
                        $colMap['master_length'] = $col;
                    } elseif (str_contains($val, 'master') && str_contains($val, 'width')) {
                        $colMap['master_width'] = $col;
                    } elseif (str_contains($val, 'master') && str_contains($val, 'height')) {
                        $colMap['master_height'] = $col;
                    } elseif (str_contains($val, 'master') && str_contains($val, 'weight')) {
                        $colMap['master_weight_kg'] = $col;
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

            $maxRow = $sheet->getHighestRow();
            // Start from row 3 (row 1 = headers, row 2 = formulas)
            for ($r = 2; $r <= $maxRow; $r++) {
                $sku = trim((string) ($sheet->getCell(($colMap['vendor_sku'] ?? 'B') . $r)->getValue() ?? ''));
                if (empty($sku)) {
                    continue;
                }

                $getVal = fn($key, $default = null) => isset($colMap[$key]) ? $sheet->getCell($colMap[$key] . $r)->getValue() : $default;

                $rows[] = [
                    'sno'             => $getVal('sno'),
                    'vendor_sku'      => $sku,
                    'sap_code'        => $getVal('sap_code'),
                    'barcode'         => $getVal('barcode'),
                    'product_name'    => $getVal('product_name'),
                    'description'     => $getVal('description'),
                    'hsn_code'        => $getVal('hsn_code'),
                    'duty_percent'    => $getVal('duty_percent'),
                    'length'          => $getVal('length'),
                    'width'           => $getVal('width'),
                    'height'          => $getVal('height'),
                    'weight_grams'    => $getVal('weight_grams'),
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
                    'qty_master_pack' => $getVal('qty_master_pack'),
                    'master_length'   => $getVal('master_length'),
                    'master_width'    => $getVal('master_width'),
                    'master_height'   => $getVal('master_height'),
                    'master_weight_kg' => $getVal('master_weight_kg'),
                    'qty_offered'     => $getVal('qty_offered'),
                    'vendor_fob'      => $getVal('vendor_fob'),

                ];
            }
        } catch (\Exception $e) {
            \Log::error('Live sheet parse error: ' . $e->getMessage());
        }

        return $rows;
    }
    public function uploadInspection(Request $request, Consignment $consignment)
    {
        $validated = $request->validate([
            'inspection_type'    => 'required|in:inline,midline,final',
            'report'             => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx|max:20480',
            'commercial_invoice' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx|max:20480',
            'packing_list'       => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx|max:20480',
            'result'             => 'nullable|string',
            'remarks'            => 'nullable|string',
        ]);

        $folder = 'inspection-reports/' . $consignment->id;

        $data = [
            'consignment_id'     => $consignment->id,
            'inspection_type'    => $validated['inspection_type'],
            'report_file'        => $request->file('report')->store($folder, 'public'),
            'report_name'        => $request->file('report')->getClientOriginalName(),
            'result'             => $validated['result'] ?? null,
            'remarks'            => $validated['remarks'] ?? null,
            'uploaded_by'        => auth()->id(),
        ];

        // Commercial Invoice
        if ($request->hasFile('commercial_invoice')) {
            $data['commercial_invoice_file'] = $request->file('commercial_invoice')->store($folder, 'public');
            $data['commercial_invoice_name'] = $request->file('commercial_invoice')->getClientOriginalName();
        }

        // Packing List
        if ($request->hasFile('packing_list')) {
            $data['packing_list_file'] = $request->file('packing_list')->store($folder, 'public');
            $data['packing_list_name'] = $request->file('packing_list')->getClientOriginalName();
        }

        file_put_contents("storage/logs/VendorInspection.log" . date("Y-m-d") . ".log", print_r($data, true) . "\n", FILE_APPEND);

        \App\Models\InspectionReport::create($data);

        \App\Models\ActivityLog::log('uploaded', 'inspection', $consignment, null, [
            'type' => $request->inspection_type,
            'result' => $request->result
        ], ucfirst($request->inspection_type) . ' inspection uploaded by vendor.');

        return back()->with('success', 'Inspection report uploaded successfully.');
    }
    public function uploadInspectionBACK(Request $request, Consignment $consignment)
    {
        $request->validate(['inspection_type' => 'required|in:inline,midline,final', 'report' => 'required|file|max:20480', 'commercial_invoice' => 'nullable|file|max:20480', 'packing_list' => 'nullable|file|max:20480']);
        $path = $request->file('report')->store('inspections/' . $consignment->id, 'public');

        $com_inv_path = $request->file('commercial_invoice')->store('inspections/' . $consignment->id, 'public');
        $pack_list_path = $request->file('packing_list')->store('inspections/' . $consignment->id, 'public');
        \App\Models\InspectionReport::create([
            'consignment_id' => $consignment->id,
            'inspection_type' => $request->inspection_type,
            'report_file' => $path,
            'report_name' => $request->file('report')->getClientOriginalName(),
            'commercial_invoice_file' => $com_inv_path,
            'commercial_invoice_name' => $request->file('commercial_invoice') ? $request->file('commercial_invoice')->getClientOriginalName() : null,
            'packing_list_file' => $pack_list_path,
            'packing_list_name' => $request->file('packing_list') ? $request->file('packing_list')->getClientOriginalName() : null,
            'result' => $request->result,
            'remarks' => $request->remarks,
            'uploaded_by' => auth()->id(),
        ]);
        return back()->with('success', 'Inspection report uploaded.');
    }
    public function uploadCommercialInvoice(Request $request, Consignment $consignment)
    {
        $request->validate(['commercial_invoice' => 'nullable|file|max:20480', 'packing_list' => 'nullable|file|max:20480']);
        print_r($request->all());
        exit;
        $path = $request->file('commercial_invoice')->store('inspections/' . $consignment->id, 'public');
        $path = $request->file('packing_list')->store('inspections/' . $consignment->id, 'public');

        \App\Models\InspectionReport::create([
            'consignment_id' => $consignment->id,
            'inspection_type' => $request->inspection_type,
            'report_file' => $path,
            'report_name' => $request->file('commercial_invoice')->getClientOriginalName(),
            'result' => $request->result,
            'remarks' => $request->remarks,
            'uploaded_by' => auth()->id(),
        ]);
        return back()->with('success', 'Commercial invoice uploaded.');
    }

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
        $chargebacks = Chargeback::where('vendor_id', $vendor->id)->with('order.salesChannel')->latest()->paginate(20);
        return view('vendor.chargebacks.index', compact('chargebacks', 'vendor'));
    }

    public function payouts()
    {
        $vendor = auth()->user()->vendor;
        $payouts = VendorPayout::where('vendor_id', $vendor->id)->orderByDesc('payout_year')->orderByDesc('payout_month')->paginate(12);
        $warehouseCharges = WarehouseCharge::where('vendor_id', $vendor->id)->latest()->take(10)->get();
        return view('vendor.payouts.index', compact('payouts', 'vendor', 'warehouseCharges'));
    }

    public function uploadInvoice(Request $request, VendorPayout $payout)
    {
        $request->validate(['invoice' => 'required|file|mimes:pdf|max:10240', 'vendor_invoice_number' => 'required|string|max:100']);
        $path = $request->file('invoice')->store('vendor-invoices/' . $payout->vendor_id, 'public');
        $payout->update(['vendor_invoice_file' => $path, 'vendor_invoice_number' => $request->vendor_invoice_number, 'vendor_invoice_date' => now(), 'status' => 'invoice_received']);
        return back()->with('success', 'Invoice uploaded. Invoice #: ' . $request->vendor_invoice_number);
    }

    /**
     * Download consignment contract PDF
     */
    public function downloadContract()
    {
        $path = public_path('downloads/Consignment_Contract_ExpoBazaar.pdf');

        if (!file_exists($path)) {
            return back()->with('error', 'Contract file not found. Please contact support.');
        }

        return response()->download($path, 'Consignment_Contract_ExpoBazaar.pdf');
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
            'ex_factory_date'        => "nullable|date|after_or_equal:{$today}|before_or_equal:{$maxExFactory}",
            'final_inspection_date'  => 'nullable|date',
        ]);

        $data = [];

        // ── Ex-Factory Date ───────────────────────────────────────────────────────
        if ($request->has('ex_factory_date')) {
            $data['ex_factory_date'] = $request->ex_factory_date ?: null;
        }

        // ── Final Inspection Date — must be within 10 days of ex_factory_date ─────
        if ($request->has('final_inspection_date')) {
            $inspDate  = $request->final_inspection_date;
            $exFactory = $request->ex_factory_date
                ?? $liveSheet->ex_factory_date?->toDateString();

            if ($inspDate && $exFactory) {
                $maxInspection = \Carbon\Carbon::parse($exFactory)->addDays(10)->toDateString();

                if ($inspDate < $exFactory || $inspDate > $maxInspection) {
                    return response()->json([
                        'success' => false,
                        'message' => "Final Inspection Date must be between {$exFactory} and {$maxInspection}.",
                    ], 422);
                }
            }

            $data['final_inspection_date'] = $inspDate ?: null;
        }

        if (!empty($data)) {
            $liveSheet->update($data);
            $data['test'] = 'dates updated';
        }

        return response()->json(['success' => true, $data]);
    }
    public function inspectionReports(Request $request)
    {
        $vendor = auth()->user()->vendor;

        $reports = \App\Models\InspectionReport::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))
            ->with('consignment', 'uploader')
            ->when($request->type, fn($q, $v) => $q->where('inspection_type', $v))
            ->when($request->result, fn($q, $v) => $q->where('result', $v))
            ->when($request->consignment_id, fn($q, $v) => $q->where('consignment_id', $v))
            ->latest()->paginate(20);

        $consignments = $vendor->consignments()->latest()->get();

        $stats = [
            'total'    => \App\Models\InspectionReport::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))->count(),
            'passed'   => \App\Models\InspectionReport::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))->where('result', 'passed')->count(),
            'failed'   => \App\Models\InspectionReport::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))->where('result', 'failed')->count(),
            'conditional' => \App\Models\InspectionReport::whereHas('consignment', fn($q) => $q->where('vendor_id', $vendor->id))->where('result', 'conditional')->count(),
        ];

        return view('vendor.inspections.index', compact('reports', 'consignments', 'stats', 'vendor'));
    }
}
