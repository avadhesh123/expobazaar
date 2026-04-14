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
        $request->validate([
            'documents'           => 'required|array|min:1',
            'documents.*'         => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'bank_name'           => 'required|string',
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
        return redirect()->route('vendor.dashboard')->with('success', 'KYC documents submitted for Finance review.');
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
            $imageMap = $this->extractExcelImages($sheet);

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
                    
file_put_contents('img_'.time().'.log', $col);
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
    protected function extractExcelImages($sheet): array
    {
        $imageMap = [];
        $vendorId = auth()->user()->vendor->id ?? 0;

        try {
            $drawings = $sheet->getDrawingCollection();

            foreach ($drawings as $drawing) {
                // Get the cell coordinates where the image is anchored
                $coordinates = $drawing->getCoordinates();
                // Extract row number from coordinates (e.g., "D2" → 2)
                preg_match('/([A-Z]+)(\d+)/', $coordinates, $matches);
                $row = (int) ($matches[2] ?? 0);

                if ($row < 2) {
                    continue;
                } // Skip header row images

                $imagePath = null;

                // Handle different drawing types
                if ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\Drawing) {
                    // File-based drawing
                    $sourcePath = $drawing->getPath();
                    if (file_exists($sourcePath)) {
                        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'png';
                        $filename = 'offer-img-' . $vendorId . '-' . $row . '-' . time() . '.' . $ext;
                        $destPath = 'offer-thumbnails/' . $vendorId . '/' . $filename;

                        \Storage::disk('public')->put($destPath, file_get_contents($sourcePath));
                        $imagePath = $destPath;
                    }
                } elseif ($drawing instanceof \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing) {
                    // In-memory drawing (embedded image)
                    $ext = match ($drawing->getMimeType()) {
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_PNG  => 'png',
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_GIF  => 'gif',
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_JPEG => 'jpg',
                        default => 'png',
                    };

                    $filename = 'offer-img-' . $vendorId . '-' . $row . '-' . time() . '.' . $ext;
                    $destPath = 'offer-thumbnails/' . $vendorId . '/' . $filename;

                    ob_start();
                    $renderFunc = match ($drawing->getMimeType()) {
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_PNG  => 'imagepng',
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_GIF  => 'imagegif',
                        \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_JPEG => 'imagejpeg',
                        default => 'imagepng',
                    };
                    $imageResource = $drawing->getImageResource();
                    if ($imageResource) {
                        $renderFunc($imageResource);
                        $imageData = ob_get_clean();
                        \Storage::disk('public')->put($destPath, $imageData);
                        $imagePath = $destPath;
                    } else {
                        ob_end_clean();
                    }
                }

                if ($imagePath) {
                    $imageMap[$row] = $imagePath;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Image extraction error: ' . $e->getMessage());
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
        $liveSheets = LiveSheet::whereHas('consignment', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->with('consignment', 'items.product')->latest()->paginate(20);
        return view('vendor.live-sheets.index', compact('liveSheets', 'vendor'));
    }

    public function editLiveSheet(LiveSheet $liveSheet)
    {
        $liveSheet->load('consignment.vendor', 'items.product');
        $vendor = auth()->user()->vendor;
        if ($liveSheet->consignment->vendor_id !== $vendor->id) {
            abort(403);
        }
        if ($liveSheet->is_locked) {
            return back()->with('error', 'Live sheet is locked. Contact admin to unlock.');
        }
        return view('vendor.live-sheets.edit', compact('liveSheet', 'vendor'));
    }

    public function submitLiveSheet(Request $request, LiveSheet $liveSheet)
    {
        $request->validate(['items' => 'required|array|min:1']);
        $this->sourcingService->submitLiveSheet($liveSheet, $request->items);
        return redirect()->route('vendor.consignments')->with('success', 'Live sheet submitted for Sourcing approval.');
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
