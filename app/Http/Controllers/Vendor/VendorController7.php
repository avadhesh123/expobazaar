<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\{Vendor, Consignment, VendorPayout, Chargeback, VendorDocument, Category, LiveSheet, OfferSheet, OfferSheetItem, Order, WarehouseCharge, Product};
use App\Services\{DashboardService, VendorService, SourcingService};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VendorController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected VendorService $vendorService,
        protected SourcingService $sourcingService
    ) {
    }

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
            'total_sales'   => Order::whereHas('items', fn ($q) => $q->where('vendor_id', $vendor->id))->sum('total_amount'),
            'chargebacks'   => Chargeback::where('vendor_id', $vendor->id)->where('status', 'confirmed')->sum('amount'),
            'pending_payout' => VendorPayout::where('vendor_id', $vendor->id)->whereIn('status', ['calculated', 'approved'])->sum('net_payout'),
        ];

        $data['recent_orders'] = Order::whereHas('items', fn ($q) => $q->where('vendor_id', $vendor->id))
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
            'gst_number'              => 'required|string|max:20',
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
            'msme_number'             => 'required|string|max:50',
            'documents.gst_certificate'   => $this->docRequired($vendor, 'gst_certificate'),
            'documents.cancelled_cheque'  => $this->docRequired($vendor, 'cancelled_cheque'),
           // 'documents.signed_contract'   => $this->docRequired($vendor, 'signed_contract'),
            'documents.iec_certificate'   => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'documents.msme_certificate'  => $this->docRequired($vendor, 'msme_certificate'),
            'documents.other.*'           => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ], [
            'msme_number.required'                 => 'MSME number is mandatory.',
            'msme_certificate.required'           => 'MSME certificate is mandatory.',
            'bank_swift_code.required'            => 'SWIFT / BIC code is mandatory.',
            'bank_swift_code.min'                 => 'SWIFT code must be at least 8 characters.',
            'documents.cancelled_cheque.required'  => 'Cancelled cheque / bank proof is mandatory.',
         //   'documents.signed_contract.required'   => 'Signed consignment contract is mandatory.',
            'documents.msme_certificate.required'  => 'MSME certificate is mandatory.',
            'documents.gst_certificate.required'   => 'GST certificate is mandatory.',
            'street_address.required'              => 'Street address is required.',
            'province_state.required'              => 'Province / State is required.',
        ]);

        // Update vendor fields
        try {
            $vendor->update($request->only([
                'gst_number', 'company_name', 'street_address', 'city', 'province_state',
                'pincode', 'country', 'contact_person', 'finance_contact_person', 'phone',
                'email', 'iec_code', 'msme_number', 'landline', 'official_website',
                'bank_name', 'bank_ifsc', 'bank_swift_code', 'bank_account_number',
            ]));
        } catch (\Exception $e) {
            \Log::error('KYC vendor update failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update vendor info: ' . $e->getMessage())->withInput();
        }

        // Process document uploads
        $docs = [];
        $docTypes = ['gst_certificate', 'cancelled_cheque', 'iec_certificate', 'msme_certificate'];
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
                        'vendor_id' => $vendor->id, 'document_type' => 'other',
                        'document_name' => $file->getClientOriginalName(), 'file_path' => $path,
                        'file_type' => $file->getMimeType(), 'file_size' => $file->getSize(),
                        'uploaded_by' => auth()->id(), 'status' => 'uploaded',
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
            // Use image extracted from Excel, or separate upload
            $thumbnail = $p['image_path'] ?? null;
            if (!$thumbnail && $request->hasFile('thumbnails.' . ($p['sno'] ?? ''))) {
                $thumbnail = $request->file('thumbnails.' . $p['sno'])->store('offer-thumbnails/' . $vendor->id, 'public');
            }

            // Auto-create or find product
            $product = Product::firstOrCreate(
                ['sku' => $p['vendor_sku'], 'vendor_id' => $vendor->id],
                [
                    'name'         => $p['product_name'],
                    'company_code' => $vendor->company_code,
                    'status'       => 'draft',
                ]
            );

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
                        ['slug' => Str::slug($p['sub_category']), 'parent_id' => $cat->id],
                        ['name' => $p['sub_category'], 'sort_order' => 0]
                    );
                    $categoryId = $subCat->id;
                }
            }

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
        } catch (\Exception $e) {
            \Log::error('Offer sheet parse error: ' . $e->getMessage());
        }

        return $products;
    }

    /**
     * Extract embedded images from Excel sheet and save to storage
     * Returns array keyed by row number => saved file path
     */
    protected function extractExcelImages($sheet, string $excelFilePath = ''): array
    {
        $imageMap = [];
        $vendorId = auth()->user()->vendor->id ?? 0;

        try {
            $drawings = $sheet->getDrawingCollection();

            foreach ($drawings as $drawing) {
                $coordinates = $drawing->getCoordinates();
                preg_match('/([A-Z]+)(\d+)/', $coordinates, $matches);
                $row = (int) ($matches[2] ?? 0);

                if ($row < 2) {
                    continue; // Skip header row images (like logo)
                }

                $imagePath = null;
                $filename = 'offer-img-' . $vendorId . '-' . $row . '-' . time() . '-' . mt_rand(100, 999);
                $destDir = 'offer-thumbnails/' . $vendorId;

                if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                    // File-based drawing — path may be a zip:// internal path
                    $sourcePath = $drawing->getPath();
                    $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'png');
                    $fullFilename = $filename . '.' . $ext;
                    $destPath = $destDir . '/' . $fullFilename;

                    $imageData = null;

                    // Method 1: Direct file (rare — only if image is external reference)
                    if (file_exists($sourcePath) && !str_starts_with($sourcePath, 'zip://')) {
                        $imageData = file_get_contents($sourcePath);
                    }

                    // Method 2: Read from zip path (zip://filepath#internal/path)
                    if (!$imageData && str_starts_with($sourcePath, 'zip://')) {
                        $imageData = @file_get_contents($sourcePath);
                    }

                    // Method 3: Extract directly from the xlsx zip archive
                    if (!$imageData && $excelFilePath && file_exists($excelFilePath)) {
                        $zip = new \ZipArchive();
                        if ($zip->open($excelFilePath) === true) {
                            // Find the image file in xl/media/
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $zipEntry = $zip->getNameIndex($i);
                                if (str_contains($zipEntry, 'media/') && str_contains($sourcePath, basename($zipEntry))) {
                                    $imageData = $zip->getFromIndex($i);
                                    $ext = strtolower(pathinfo($zipEntry, PATHINFO_EXTENSION) ?: 'png');
                                    $fullFilename = $filename . '.' . $ext;
                                    $destPath = $destDir . '/' . $fullFilename;
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
                    // In-memory drawing (pasted/embedded image)
                    $ext = match ($drawing->getMimeType()) {
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_PNG  => 'png',
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_GIF  => 'gif',
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_JPEG => 'jpg',
                        default => 'png',
                    };
                    $fullFilename = $filename . '.' . $ext;
                    $destPath = $destDir . '/' . $fullFilename;

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
                    $imageMap[$row] = $imagePath;
                }
            }

            // Fallback: Extract ALL images from xlsx zip and map by order
            if (empty($imageMap) && $excelFilePath && file_exists($excelFilePath)) {
                $imageMap = $this->extractImagesFromZip($excelFilePath, $vendorId);
            }

        } catch (\Exception $e) {
            \Log::warning('Image extraction error: ' . $e->getMessage());
        }

        return $imageMap;
    }

    /**
     * Fallback: Extract images directly from xlsx zip archive
     * Maps images to data rows by order (image 1 → row 2, image 2 → row 3, etc.)
     */
    protected function extractImagesFromZip(string $excelFilePath, int $vendorId): array
    {
        $imageMap = [];

        try {
            $zip = new \ZipArchive();
            if ($zip->open($excelFilePath) !== true) {
                return $imageMap;
            }

            $mediaFiles = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_starts_with($name, 'xl/media/')) {
                    $mediaFiles[] = ['index' => $i, 'name' => $name];
                }
            }

            // Try to read drawing XML to get row anchors
            $rowAnchors = [];
            $drawingXml = @$zip->getFromName('xl/drawings/drawing1.xml');
            if ($drawingXml) {
                preg_match_all('/<xdr:from>.*?<xdr:row>(\d+)<\/xdr:row>.*?<\/xdr:from>/s', $drawingXml, $anchorMatches);
                if (!empty($anchorMatches[1])) {
                    $rowAnchors = array_map('intval', $anchorMatches[1]);
                }
            }

            $destDir = 'offer-thumbnails/' . $vendorId;
            $dataRowStart = 2; // Row 2 is first data row (row 1 = headers)

            foreach ($mediaFiles as $idx => $media) {
                $imageData = $zip->getFromIndex($media['index']);
                if (!$imageData) {
                    continue;
                }

                $ext = strtolower(pathinfo($media['name'], PATHINFO_EXTENSION) ?: 'png');
                $filename = 'offer-img-' . $vendorId . '-' . ($idx + 1) . '-' . time() . '.' . $ext;
                $destPath = $destDir . '/' . $filename;

                \Storage::disk('public')->put($destPath, $imageData);

                // Map to row: use anchor if available, otherwise sequential
                if (isset($rowAnchors[$idx])) {
                    $row = $rowAnchors[$idx] + 1; // Excel rows are 0-indexed in XML
                    if ($row >= $dataRowStart) {
                        $imageMap[$row] = $destPath;
                    }
                } else {
                    // Sequential: skip first image if it's likely a logo (row 1)
                    $row = $dataRowStart + $idx;
                    if ($idx === 0 && count($mediaFiles) > 1) {
                        continue; // Skip first image (probably logo)
                    }
                    $imageMap[$row] = $destPath;
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
        $path = public_path('downloads/Offer_Sheet-US.xlsx');

        if (!file_exists($path)) {
            // Fallback: generate CSV template
            $csv = "S.no,Vendor SKU,Product Name,Product Image,Product Length (In Inches),Product Width (In Inches),Product Height (In Inches),Product Weight (In Gram),Material Composition,Color,Product Finish,Category,Sub Category,Vendor FOB Mumbai ($),Comments,Selection\n";
            $csv .= "1,EB123,Glass Vase,,10,10,2,200,Glass,Clear,Glossy,Home & Décor,Décor,1,,\n";

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
        if ($liveSheet->is_locked) {
            return back()->with('error', 'Live sheet is locked. Contact admin to unlock.');
        }
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
        return redirect()->route('vendor.live-sheets')->with('success', 'Live sheet submitted for Sourcing approval.');
    }

    /**
     * Download live sheet template pre-filled with SKUs for vendor to fill
     */
    public function downloadLiveSheetTemplate(LiveSheet $liveSheet)
    {
        $vendor = auth()->user()->vendor;
        if ($liveSheet->vendor_id !== $vendor->id) {
            abort(403);
        }

        $liveSheet->load('items.product');

        $headers = "S.no,Vendor SKU,SAP Code,Barcode,Product Name,Product Description (Min 100 words),Hsn & Hts Code,Duty %,Product Length (Inches),Product Width (Inches),Product Height (Inches),Product Weight (Gram),Material Composition,Other Material,Color,Product Finish,Category,Sub Category,Qty In Inner Pack,Inner Carton Length (Inches),Inner Carton Width (Inches),Inner Carton Height (Inches),Qty In Master Pack,Master Carton Length (Inches),Master Carton Width (Inches),Master Carton Height (Inches),Master Carton Weight (Kg),Qty Offered (Units/Sets),Vendor FOB Mumbai ($),Target FOB ($),Final Qty (Units/Sets),Total No Of Master Cartons,Master Carton CBM,CBM Shipment,Final FOB ($),Duty,Freight Factor,Freight,Landed Cost,WSP Factor,WSP ($),Comments\n";

        $csv = $headers;
        foreach ($liveSheet->items as $idx => $item) {
            $p = $item->product;
            $d = $item->product_details ?? [];
            $csv .= implode(',', [
                $idx + 1,
                '"' . str_replace('"', '""', $p->sku ?? '') . '"',
                '', // SAP Code
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
                '', '', '', '', // Inner pack fields
                '', '', '', '', '', // Master pack fields
                $item->quantity,
                $item->unit_price,
                '', // Target FOB
                '', '', '', '', // Final qty, master cartons, CBM fields
                '', '', '', '', '', // FOB, duty, freight fields
                '', '', // WSP
                '', // Comments
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
        $request->validate([
            'live_sheet_file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
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
            $item = $liveSheet->items()->whereHas('product', fn ($q) => $q->where('sku', $sku))->first();

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
                    'vendor_fob'       => $row['vendor_fob'] ?? null,
                    'target_fob'       => $row['target_fob'] ?? null,
                    'final_qty'        => $finalQty,
                    'total_master_cartons' => $totalMasterCartons,
                    'master_cbm'       => round($masterCbm, 6),
                    'cbm_shipment'     => round($cbmShipment, 4),
                    'final_fob'        => $finalFob,
                    'freight_factor'   => $row['freight_factor'] ?? null,
                    'wsp_factor'       => $row['wsp_factor'] ?? null,
                    'comments'         => $row['comments'] ?? null,
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
                    } elseif (str_contains($val, 'sap')) {
                        $colMap['sap_code'] = $col;
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
                    } elseif (str_contains($val, 'product weight') || str_contains($val, 'weight') && str_contains($val, 'gram')) {
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
            for ($r = 3; $r <= $maxRow; $r++) {
                $sku = trim((string) ($sheet->getCell(($colMap['vendor_sku'] ?? 'B') . $r)->getValue() ?? ''));
                if (empty($sku)) {
                    continue;
                }

                $getVal = fn ($key, $default = null) => isset($colMap[$key]) ? $sheet->getCell($colMap[$key] . $r)->getValue() : $default;

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
                    'target_fob'      => $getVal('target_fob'),
                    'final_qty'       => $getVal('final_qty'),
                    'final_fob'       => $getVal('final_fob'),
                    'freight_factor'  => $getVal('freight_factor'),
                    'wsp_factor'      => $getVal('wsp_factor'),
                    'comments'        => $getVal('comments'),
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Live sheet parse error: ' . $e->getMessage());
        }

        return $rows;
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
        $orders = Order::whereHas('items', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->with('salesChannel', 'items')
            ->when($request->month, fn ($q, $v) => $q->whereMonth('order_date', $v))
            ->when($request->year, fn ($q, $v) => $q->whereYear('order_date', $v))
            ->latest('order_date')->paginate(25);
        $totalSales = Order::whereHas('items', fn ($q) => $q->where('vendor_id', $vendor->id))->sum('total_amount');
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
}
