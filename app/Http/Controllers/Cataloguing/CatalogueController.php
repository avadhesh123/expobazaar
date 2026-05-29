<?php

namespace App\Http\Controllers\Cataloguing;

use App\Http\Controllers\Controller;
use App\Models\{PlatformPricing, Product, SalesChannel, ProductCatalogue};
use App\Services\{DashboardService, CatalogueService};
use Illuminate\Http\Request;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;



class CatalogueController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected CatalogueService $catalogueService
    ) {}

    public function dashboard(Request $request)
    {
        // $companyCode = $request->get('company_code');
        $companyCode = session('company_code'); // For view to highlight selected code
        $data = $this->dashboardService->getCataloguingDashboard($companyCode);
        return view('cataloguing.dashboard', compact('data', 'companyCode'));
    }

    public function pricingSheets(Request $request)
    {
        $user = auth()->user();
        $activeCompany = session('active_company') ?? '2100'; // fallback;
        $currency = trim(config('app.active_currency_symbol')); // $, ₹, €

        $pricings = PlatformPricing::with('product.category', 'product.vendor', 'salesChannel', 'asn')
            ->whereIn('status', ['approved'])
            ->when(!$user->isAdmin() && !empty($activeCompany), function ($q) use ($activeCompany) {
                return $q->where('company_code', $activeCompany);
            })
            ->when($request->channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->asn_id, fn($q, $v) => $q->where('asn_id', $v))
            ->when($request->search, function ($q, $v) {
                $q->whereHas('product', fn($p) => $p->where('sku', 'LIKE', "%{$v}%")->orWhere('name', 'LIKE', "%{$v}%"));
            })
            ->latest()->paginate(30); //->withQueryString();

        $pricings->setCollection(
            $pricings->getCollection()->groupBy('product_id')
        );

        $channels = SalesChannel::active()
            ->when(!$user->isAdmin() && !empty($activeCompany), function ($q) use ($activeCompany) {
                return $q->whereJsonContains('company_codes', $activeCompany);
            })
            ->when($request->channel_id, fn($q, $v) => $q->where('id', $v))
            ->orderBy('name')->get();

        $asns = \App\Models\Asn::orderBy('asn_number', 'desc')
            ->when(!$user->isAdmin() && !empty($activeCompany), function ($q) use ($activeCompany) {
                return $q->where('company_code', $activeCompany);
            })
            // ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->limit(100)->get(['id', 'asn_number']);

        return view('cataloguing.pricing-sheets', compact('pricings', 'channels', 'asns'));
    }

    public function listingPanel(Request $request)
    {
        $activeCompany = session('active_company'); // For view to highlight selected code
        $products = Product::with('category', 'vendor')
            ->where('company_code', $activeCompany)
            ->when($request->category_id, fn($q, $v) => $q->where('category_id', $v))
            ->when($request->search, function ($q, $v) {
                $q->where(function ($s) use ($v) {
                    $s->where('sku', 'LIKE', "%{$v}%")
                        ->orWhere('name', 'LIKE', "%{$v}%");
                });
            })
            ->paginate(30)->withQueryString();

        $channels = SalesChannel::active()->whereJsonContains('company_codes', $activeCompany)->orderBy('name')->get();
        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);

        return view('cataloguing.listing-panel', compact('products', 'channels', 'categories'));
    }

    public function updateListings1(Request $request)
    {
        $request->validate([
            'listings' => 'required|array|min:1',
            'listings.*.product_id'        => 'required|exists:products,id',
            'listings.*.sales_channel_id'  => 'required|exists:sales_channels,id',
            'listings.*.listing_status'    => 'nullable|string|max:50',
        ]);

        $allowed = ['pending', 'listed', 'inactive', 'removed'];
        $aliases = [
            'active' => 'listed',
            'published' => 'listed',
            'live' => 'listed',
            'enabled' => 'listed',
            'draft' => 'pending',
            'not_listed' => 'pending',
            'unlisted' => 'pending',
            'paused' => 'inactive',
            'disabled' => 'inactive',
            'deleted' => 'removed',
            'archived' => 'removed',
            '' => 'pending',
        ];


        try {
            $updated = 0;
            foreach ($request->listings as $l) {
                $product = Product::find($l['product_id']);
                if (!$product) continue;

                $channelId = $l['sales_channel_id'];
                $status = strtolower(trim($l['listing_status'] ?? 'pending'));
                $status = $aliases[$status] ?? $status;
                if (!in_array($status, $allowed)) $status = 'pending';

                // Save to product.platform_listing_status JSON
                $pls = $product->platform_listing_status ?? [];
                $pls[$channelId] = $status;
                $product->update(['platform_listing_status' => $pls]);
                $updated++;
            }
            foreach ($request->shopify_url as $productId => $url) {
                $product = Product::find($productId);
                if (!$product) continue;
                $shopify_url = $url ? $url : $product->shopify_url ?? null;
                $product->update(['shopify_url' => $shopify_url]);
                file_put_contents(storage_path('logs/shopify_urls.log'), "Updated Product ID {$productId} {$product->name} with Shopify URL: {$shopify_url}\n", FILE_APPEND);
            }
            return back()->with('success', "{$updated} listing(s) updated successfully.");
        } catch (\Exception $e) {
            \Log::error('Listing update failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to update listings: ' . $e->getMessage())->withInput();
        }
    }

    public function downloadListingTemplate()
    {
        $activeCompany = session('active_company');
        $channels = SalesChannel::active()
            ->when($activeCompany, fn($q) => $q->whereJsonContains('company_codes', $activeCompany))
            ->orderBy('name')->get();

        $products = Product::with('catalogues')
            ->when($activeCompany, fn($q) => $q->where('company_code', $activeCompany))
           // ->whereIn('status', ['approved', 'live', 'dropship', 'active'])
            ->orderBy('sku')
            ->get(); 

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle('Listing Template');

        // ── Instructions Sheet ──
        $instrSheet = $spreadsheet->createSheet();
        $instrSheet->setTitle('Instructions');
        $instructions = [
            ['Bulk Listing Update — Instructions'],
            [''],
            ['Column A: Vendor SKU — Do NOT modify this column.'],
            ['Columns B onwards: Sales Channel names.'],
            [''],
            ['Values:'],
            ['  Yes  — Mark SKU as listed on this channel (creates listing if not exists)'],
            ['  No   — Remove/unlist SKU from this channel'],
            ['  Blank — No change, keeps current status'],
            [''],
            ['Values are case-insensitive (Yes, YES, yes all work)'],
            ['Do not rename column headers.'],
        ];
        foreach ($instructions as $r => $row) {
            $instrSheet->setCellValue('A' . ($r + 1), $row[0] ?? '');
        }
        $instrSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instrSheet->getColumnDimension('A')->setWidth(60);

        // ── Back to main sheet ──
        $spreadsheet->setActiveSheetIndex(0);

        // Headers
        $headers = ['Vendor SKU'];
        foreach ($channels as $ch) {
            $headers[] = $ch->name;
        }

        $hdrStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10, 'name' => 'Arial'],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];

        foreach ($headers as $col => $header) {
            $cell = $ws->getCell([$col + 1, 1]);
            $cell->setValue($header);
        }
        $ws->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1')->applyFromArray($hdrStyle);

        // Data rows
        $row = 2;
        foreach ($products as $product) {
            $ws->getCell([1, $row])->setValue($product->sku);
            $ws->getCell([1, $row])->getStyle()->getFont()->setName('Arial')->setSize(9);
            $ws->getCell([1, $row])->getStyle()->getFont()->setBold(true);

            foreach ($channels as $cIdx => $ch) {
                $catalogue = $product->catalogues->firstWhere('sales_channel_id', $ch->id);
                $val = '';
                if ($catalogue) {
                    $val = $catalogue->listing_status === 'listed' ? 'Yes' : 'No';
                }
                $cell = $ws->getCell([$cIdx + 2, $row]);
                $cell->setValue($val);
                $cell->getStyle()->getFont()->setName('Arial')->setSize(9);
                $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Color code
                if (strtolower($val) === 'yes') {
                    $cell->getStyle()->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('16A34A'));
                    $cell->getStyle()->getFont()->setBold(true);
                } elseif (strtolower($val) === 'no') {
                    $cell->getStyle()->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DC2626'));
                }
            }

            // Alternating row color
            if ($row % 2 === 0) {
                $ws->getStyle('A' . $row . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . $row)
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }

            // Borders
            $ws->getStyle('A' . $row . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . $row)
                ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');

            $row++;
        }

        // Column widths
        $ws->getColumnDimension('A')->setWidth(18);
        foreach ($channels as $cIdx => $ch) {
            $ws->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx + 2))->setWidth(15);
        }

        $ws->setAutoFilter('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1');
        $ws->freezePane('B2');

        $filename = 'Listing-Template-' . now()->format('Y-m-d') . '.xlsx';
        $outputPath = storage_path("app/temp/{$filename}");
        @mkdir(storage_path('app/temp'), 0755, true);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($outputPath);
        $spreadsheet->disconnectWorksheets();

        return response()->download($outputPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function uploadListings(Request $request)
    {
        $request->validate([
            'listing_file' => 'required|file|max:10240|mimes:xlsx,xls,csv,txt',
        ]);

        $file = $request->file('listing_file');
        $ext = strtolower($file->getClientOriginalExtension());
        $fullPath = $file->getRealPath();

        try {
            // Read file
            if (in_array($ext, ['xlsx', 'xls'])) {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(false);
                $spreadsheet = $reader->load($fullPath);
                $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            } else {
                $rows = [];
                if (($handle = fopen($fullPath, 'r')) !== false) {
                    while (($row = fgetcsv($handle)) !== false) $rows[] = $row;
                    fclose($handle);
                }
            }

            if (count($rows) < 2) {
                return back()->with('error', 'File is empty.');
            }

            // Parse header — first col is SKU, rest are channel names
            $header = array_map(fn($h) => trim($h ?? ''), $rows[0]);
            $skuCol = 0; // First column is always SKU

            // Map channel names to IDs
            $channelMap = [];
            $invalidChannels = [];
            for ($c = 1; $c < count($header); $c++) {
                $chName = $header[$c];
                if (empty($chName)) continue;
                $channel = SalesChannel::where('name', $chName)->first();
                if ($channel) {
                    $channelMap[$c] = $channel;
                } else {
                    $invalidChannels[] = $chName;
                }
            }

            if (empty($channelMap)) {
                return back()->with('error', 'No valid channel names found in header. Invalid: ' . implode(', ', $invalidChannels));
            }

            $activeCompany = session('active_company');
            $listed = 0;
            $unlisted = 0;
            $skipped = 0;
            $errors = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $rowNum = $i + 1;
                $sku = trim($row[$skuCol] ?? '');
                if (empty($sku)) continue;

                $product = Product::withoutGlobalScopes()->where('sku', $sku)->first();
                if (!$product) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' not found.";
                    continue;
                }

                foreach ($channelMap as $colIdx => $channel) {
                    $val = strtolower(trim($row[$colIdx] ?? ''));

                    if ($val === '') {
                        $skipped++;
                        continue; // Blank = no change
                    }

                    if (!in_array($val, ['yes', 'no'])) {
                        $errors[] = "Row {$rowNum}, {$channel->name}: Invalid value '{$row[$colIdx]}'. Use Yes or No.";
                        continue;
                    }

                    if ($val === 'yes') {
                        // Create or update listing
                        ProductCatalogue::updateOrCreate(
                            ['product_id' => $product->id, 'sales_channel_id' => $channel->id],
                            [
                                'company_code'   => $activeCompany ?? $product->company_code,
                                'listing_status'  => 'listed',
                                'listing_sku'     => $product->sku,
                                'listed_by'       => auth()->id(),
                                'listed_at'       => now(),
                            ]
                        );
                        $listed++;
                    } elseif ($val === 'no') {
                        // Unlist
                        $catalogue = ProductCatalogue::where('product_id', $product->id)
                            ->where('sales_channel_id', $channel->id)
                            ->first();
                        if ($catalogue) {
                            $catalogue->update(['listing_status' => 'unlisted']);
                            $unlisted++;
                        }
                    }
                }
            }

            $msg = "{$listed} listed, {$unlisted} unlisted, {$skipped} unchanged.";
            if (!empty($invalidChannels)) {
                $msg .= ' Unrecognized channels: ' . implode(', ', $invalidChannels) . '.';
            }
            if (!empty($errors)) {
                $msg .= ' ' . count($errors) . ' error(s).';
            }

            return back()
                ->with($listed + $unlisted > 0 ? 'success' : 'error', $msg)
                ->with('listing_errors', $errors);
        } catch (\Exception $e) {
            \Log::error('Listing upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }
    public function updateListings(Request $request)
    {
        $request->validate(['listings' => 'required|array|min:1']);
        $this->catalogueService->updateListingStatus($request->listings, auth()->user());
        return back()->with('success', 'Listing status updated.');
    }
    public function skuDashboard(Request $request)
    {
        // $companyCode = $request->get('company_code', '2100');
        $channelId   = $request->get('channel_id');
        $categoryId  = $request->get('category_id');

        $companyCode = session('active_company');
        $channels   = SalesChannel::active()->whereJsonContains('company_codes', $companyCode)->orderBy('name')->get();
        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);

        $productQuery = Product::where('company_code', $companyCode)
            ->when($categoryId, fn($q, $v) => $q->where('category_id', $v));

        $totalProducts = (clone $productQuery)->count();
        $allProducts = (clone $productQuery)->whereNotNull('platform_listing_status')->get(['id', 'platform_listing_status', 'category_id']);

        // Per-channel stats from Product.platform_listing_status JSON
        $channelStats = $channels->map(function ($ch) use ($allProducts, $totalProducts) {
            $listed = 0;
            $pending = 0;
            foreach ($allProducts as $p) {
                $pls = $p->platform_listing_status ?? [];
                $status = $pls[$ch->id] ?? $pls[strval($ch->id)] ?? null;
                if ($status === 'listed') $listed++;
                elseif ($status === 'pending') $pending++;
            }
            return [
                'channel'    => $ch,
                'listed'     => $listed,
                'pending'    => $pending,
                'not_listed' => max(0, $totalProducts - $listed - $pending),
                'total'      => $totalProducts,
            ];
        });

        // Category breakdown when channel selected
        $categoryStats = collect();
        if ($channelId) {
            $categoryStats = $categories->map(function ($cat) use ($allProducts, $channelId, $companyCode) {
                $catProducts = $allProducts->where('category_id', $cat->id);
                $totalInCat = Product::where('company_code', $companyCode)->where('category_id', $cat->id)->count();
                $listed = 0;
                $pending = 0;
                foreach ($catProducts as $p) {
                    $pls = $p->platform_listing_status ?? [];
                    $status = $pls[$channelId] ?? $pls[strval($channelId)] ?? null;
                    if ($status === 'listed') $listed++;
                    elseif ($status === 'pending') $pending++;
                }
                return ['category' => $cat, 'listed' => $listed, 'pending' => $pending, 'total' => $totalInCat];
            })->filter(fn($cs) => $cs['total'] > 0);
        }

        // Count total listed across all channels
        $totalListed = 0;
        foreach ($allProducts as $p) {
            $pls = $p->platform_listing_status ?? [];
            if (collect($pls)->contains('listed')) $totalListed++;
        }

        $totals = [
            'total_products'    => $totalProducts,
            'total_listed'      => $totalListed,
            'platforms_covered' => $channelStats->where('listed', '>', 0)->count(),
            'total_platforms'   => $channels->count(),
        ];

        return view('cataloguing.sku-dashboard', compact(
            'totals',
            'channels',
            'categories',
            'channelStats',
            'categoryStats',
            'companyCode',
            'channelId',
            'categoryId'
        ));
    }
    /**
     * Download Pricing Sheet as Excel with Product Images
     */
    public function downloadPricingSheet(Request $request)
    {
        $activeCompany = session('active_company') ?? '2100'; // fallback;

        $currency = trim(config('app.active_currency_symbol')); // $, ₹, €

        $pricings = PlatformPricing::with('product.vendor', 'product.category', 'salesChannel', 'asn', 'product.offerSheetItems')
            ->where('status', 'approved')
            ->where('company_code', $activeCompany)
            ->when($request->channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->asn_id, fn($q, $v) => $q->where('asn_id', $v))
            ->orderBy('product_id')
            ->get();

        $channels = SalesChannel::active()
            ->whereJsonContains('company_codes', $activeCompany)
            ->when($request->channel_id, fn($q, $v) => $q->where('id', $v))
            ->orderBy('name')
            ->get();

        if ($activeCompany === '2100') {
            $weightUnit      = 'LBS';
            $lwhUnit         = 'INCH';
            $weightConverter = 2.20462; // KG to LBS
            $lwhConverter    = 1;       // INCH
        } else {
            $weightUnit      = 'GRAMS';
            $lwhUnit         = 'CM';
            $weightConverter = 1000; // KG to GRAMS
            $lwhConverter    = 2.54;    // INCH to CM
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pricing Sheet');

        // ==================== HEADERS ====================
        $headers = [
            'A' => 'S.no',
            'B' => 'Vendor SKU',
            'C' => 'SAP Code',
            'D' => 'Barcode',
            'E' => 'Product Name',
            'F' => 'Description',
            'G' => 'Specification',
            'H' => 'Picture',
            'I' => 'HSN/HTS',
            'J' => "Length ({$lwhUnit})",
            'K' => "Width ({$lwhUnit})",
            'L' => "Height ({$lwhUnit})",
            'M' => "Weight ({$weightUnit})",
            'N' => 'Material',
            'O' => 'Other Material',
            'P' => 'Color',
            'Q' => 'Finish',
            'R' => 'Category',
            'S' => 'Sub Category',
            'T' => 'Qty in Inner Carton',
            'U' => "Inner Carton Length ({$lwhUnit})",
            'V' => "Inner Carton Width ({$lwhUnit})",
            'W' => "Inner Carton Height ({$lwhUnit})",
            'X' => "Inner Carton Weight ({$weightUnit})",
            'Y' => 'Qty in Master Carton',
            'Z' => "Master Carton Length ({$lwhUnit})",
            'AA' => "Master Carton Width ({$lwhUnit})",
            'AB' => "Master Carton Height ({$lwhUnit})",
            'AC' => "Master Carton Weight ({$weightUnit})",
            'AD' => 'Final Qty',
            'AE' => "Final FOB ({$currency})",
            'AF' => "WSP({$currency})",
        ];

        // Add dynamic channel columns
        $col = 'AG';
        foreach ($channels as $ch) {
            $headers[$col] = $ch->name . '(' . $currency . ')';
            $col++;
        }

        // Write Headers
        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . '1', $header);
        }

        // Style Header Row
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0']
            ],
        ]);

        $row = 2;
        $seen = [];

        foreach ($pricings as $p) {
            if (!$p->product || isset($seen[$p->product_id])) continue;

            $seen[$p->product_id] = true;

            $lsItem = \App\Models\LiveSheetItem::where('product_id', $p->product_id)->latest()->first();
            $d = $lsItem ? ($lsItem->product_details ?? []) : [];
            //print_r( $d );exit;
            // Basic Data
            $sheet->setCellValue('A' . $row, $row - 1);
            $sheet->setCellValue('B' . $row, $p->product->sku ?? '');
            $sheet->setCellValue('C' . $row, $p->product->sap_code ?? '');
            $sheet->setCellValue('D' . $row, $d['barcode'] ?? '');
            $sheet->setCellValue('E' . $row, $p->product->name ?? '');
            $sheet->setCellValue('F' . $row, $d['description'] ?? $d['product_description'] ?? '');
            $sheet->setCellValue('G' . $row, $d['specification'] ?? '');

            // **Product Image**

            // Get image from OfferSheetItem
            $offerItem = $p->product->offerSheetItems->first(); // or ->sortByDesc('id')->first();        

            $imagePath = null;

            if ($offerItem && !empty($offerItem->thumbnail)) {
                // Correct full server path
                $relativePath = $offerItem->thumbnail;

                // Handle both cases: with or without 'storage/' prefix
                if (str_starts_with($relativePath, 'storage/')) {
                    $imagePath = storage_path('app/public/' . substr($relativePath, 8));
                } else {
                    $imagePath = storage_path('app/public/' . $relativePath);
                }
            }


            if ($imagePath && file_exists($imagePath)) {
                $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                $drawing->setPath($imagePath);
                $drawing->setHeight(75);
                $drawing->setCoordinates('H' . $row);
                $drawing->setWorksheet($sheet);

                $drawing->setOffsetX(8);                   // Horizontal offset
                $drawing->setOffsetY(5);

                $sheet->getRowDimension($row)->setRowHeight(75);
            }


            $sheet->setCellValue('I' . $row, $d['hsn_code'] ?? $d['hts_code'] ?? '');

            // Dimensions
            $sheet->setCellValue('J' . $row, $d['length_inches'] * $lwhConverter ?? '');
            $sheet->setCellValue('K' . $row, $d['width_inches'] * $lwhConverter ?? '');
            $sheet->setCellValue('L' . $row, $d['height_inches'] * $lwhConverter ?? '');
            $sheet->setCellValue('M' . $row, $d['weight_grams'] * $weightConverter ?? '');

            $sheet->setCellValue('N' . $row, $d['material'] ?? '');
            $sheet->setCellValue('O' . $row, $d['other_material'] ?? '');
            $sheet->setCellValue('P' . $row, $d['color'] ?? '');
            $sheet->setCellValue('Q' . $row, $d['finish'] ?? '');
            $sheet->setCellValue('R' . $row, $p->product->category->name ?? $d['category'] ?? '');
            $sheet->setCellValue('S' . $row, $d['sub_category'] ?? '');
            $innerWeight = $d['inner_weight_kg'] ?? $d['inner_weight'] ?? 0;
            // Carton Info
            $sheet->setCellValue('T' . $row, $d['qty_inner_pack'] ?? '');

            // Inner Carton Dimensions with safe conversion
            $sheet->setCellValue('U' . $row, isset($d['inner_length']) && $d['inner_length'] > 0
                ? round($d['inner_length'] * $lwhConverter, 2)
                : '');

            $sheet->setCellValue('V' . $row, isset($d['inner_width']) && $d['inner_width'] > 0
                ? round($d['inner_width'] * $lwhConverter, 2)
                : '');

            $sheet->setCellValue('W' . $row, isset($d['inner_height']) && $d['inner_height'] > 0
                ? round($d['inner_height'] * $lwhConverter, 2)
                : '');

            $sheet->setCellValue('X' . $row, $innerWeight * $weightConverter);
            $sheet->setCellValue('Y' . $row, $d['qty_master_pack'] ?? '');

            $sheet->setCellValue('Z' . $row, isset($d['master_length']) ? $d['master_length'] * $lwhConverter : '');
            $sheet->setCellValue('AA' . $row, isset($d['master_width']) ? $d['master_width'] * $lwhConverter : '');
            $sheet->setCellValue('AB' . $row, isset($d['master_height']) ? $d['master_height'] * $lwhConverter : '');
            $sheet->setCellValue('AC' . $row, isset($d['master_weight_kg']) ? $d['master_weight_kg'] * $weightConverter : '');

            $sheet->setCellValue('AD' . $row, $d['final_qty'] ?? $lsItem->quantity ?? '');
            $sheet->setCellValue('AE' . $row, $d['final_fob'] ?? $lsItem->unit_price ?? '');
            $sheet->setCellValue('AF' . $row, $d['wsp'] ?? $p->wsp_price ?? 0);

            // Channel Prices
            $productPricings = $pricings->where('product_id', $p->product_id);
            $col = 'AG';
            foreach ($channels as $ch) {
                $chPricing = $productPricings->where('sales_channel_id', $ch->id)->first();
                $price = $chPricing ? ($chPricing->platform_price ?? $chPricing->selling_price ?? 0) : 0;
                $sheet->setCellValue($col . $row, $price);
                $col++;
            }

            $row++;
        }

        // Final Formatting
        $sheet->getColumnDimension('H')->setWidth(16);           // Image column
        $sheet->getColumnDimension('E')->setWidth(35);           // Product Name
        $sheet->getColumnDimension('F')->setWidth(40);           // Description

        // Freeze Header Row
        $lastRow = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();

        // Apply top alignment to all data cells (from Row 2 onwards)
        $sheet->getStyle('A2:' . $lastColumn . $lastRow)->applyFromArray([
            'alignment' => [
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP,
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'wrapText'   => true,
            ],
        ]);

        // Keep header row centered
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'alignment' => [
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'font' => ['bold' => true],
        ]);


        // Final Formatting
        $sheet->getColumnDimension('H')->setWidth(18);        // Image column
        $sheet->getStyle('H2:H' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('A2');


        // Set default row height for all rows

        $sheet->getDefaultRowDimension()->setRowHeight(25);

        // Auto-size columns
        $highestColumn = $sheet->getHighestColumn();
        for ($col = 'A'; $col !== $highestColumn; $col++) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getColumnDimension($highestColumn)->setAutoSize(true); // Last column


        // Download
        $writer = new Xlsx($spreadsheet);
        $filename = 'Pricing_Sheet_' . $activeCompany . '_' . date('Y-m-d_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}
