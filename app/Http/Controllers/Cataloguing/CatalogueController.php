<?php

namespace App\Http\Controllers\Cataloguing;

use App\Http\Controllers\Controller;
use App\Models\{PlatformPricing, Product, SalesChannel};
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

        $channels = SalesChannel::active()->orderBy('name')->get();
        $categories = \App\Models\Category::orderBy('name')->get(['id', 'name']);

        return view('cataloguing.listing-panel', compact('products', 'channels', 'categories'));
    }

    public function updateListings(Request $request)
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

        $pricings = PlatformPricing::with('product.vendor', 'product.category', 'salesChannel', 'asn')
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
            $weightUnit      = 'LBS';
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

            // Basic Data
            $sheet->setCellValue('A' . $row, $row - 1);
            $sheet->setCellValue('B' . $row, $p->product->sku ?? '');
            $sheet->setCellValue('C' . $row, $p->product->sap_code ?? '');
            $sheet->setCellValue('D' . $row, $d['barcode'] ?? '');
            $sheet->setCellValue('E' . $row, $p->product->name ?? '');
            $sheet->setCellValue('F' . $row, $d['description'] ?? $d['product_description'] ?? '');
            $sheet->setCellValue('G' . $row, $d['specification'] ?? '');

            // **Product Image**
            if (!empty($p->product->thumbnail)) {
                $imagePath = public_path('storage/' . $p->product->thumbnail);
                if (file_exists($imagePath)) {
                    $drawing = new Drawing();
                    $drawing->setName('Product Image');
                    $drawing->setPath($imagePath);
                    $drawing->setHeight(60);
                    $drawing->setCoordinates('H' . $row);
                    $drawing->setWorksheet($sheet);
                }
            }

            $sheet->setCellValue('I' . $row, $d['hsn_code'] ?? $d['hts_code'] ?? '');

            // Dimensions
            $sheet->setCellValue('J' . $row, $d['product_length'] * $lwhConverter ?? $d['length'] * $lwhConverter ?? '');
            $sheet->setCellValue('K' . $row, $d['product_width'] * $lwhConverter ?? $d['width'] * $lwhConverter ?? '');
            $sheet->setCellValue('L' . $row, $d['product_height'] * $lwhConverter ?? $d['height'] * $lwhConverter ?? '');
            $sheet->setCellValue('M' . $row, $d['product_weight'] ?? $d['weight_per_unit'] * $weightConverter ?? '');

            $sheet->setCellValue('N' . $row, $d['material'] ?? '');
            $sheet->setCellValue('O' . $row, $d['other_material'] ?? '');
            $sheet->setCellValue('P' . $row, $d['color'] ?? '');
            $sheet->setCellValue('Q' . $row, $d['finish'] ?? '');
            $sheet->setCellValue('R' . $row, $p->product->category->name ?? $d['category'] ?? '');
            $sheet->setCellValue('S' . $row, $d['sub_category'] ?? '');

            // Carton Info
            $sheet->setCellValue('T' . $row, $d['qty_inner_pack'] ?? '');
            $sheet->setCellValue('U' . $row, $d['inner_length'] * $lwhConverter ?? '');
            $sheet->setCellValue('V' . $row, $d['inner_width'] * $lwhConverter ?? '');
            $sheet->setCellValue('W' . $row, $d['inner_height'] * $lwhConverter ?? '');
            $sheet->setCellValue('X' . $row, $d['inner_weight'] * $weightConverter ?? '');
            $sheet->setCellValue('Y' . $row, $d['qty_master_pack'] ?? '');

            $sheet->setCellValue('Z' . $row, $d['master_length'] * $lwhConverter ?? '');
            $sheet->setCellValue('AA' . $row, $d['master_width'] * $lwhConverter ?? '');
            $sheet->setCellValue('AB' . $row, $d['master_height'] * $lwhConverter ?? '');
            $sheet->setCellValue('AC' . $row, $d['master_weight'] * $weightConverter ?? '');

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
