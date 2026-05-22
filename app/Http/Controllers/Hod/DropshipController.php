<?php

namespace App\Http\Controllers\Hod;

use App\Http\Controllers\Controller;
use App\Models\{Product, Vendor, Warehouse, Inventory, InventoryLog, ActivityLog};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DropshipController extends Controller
{
    public function index()
    {
        $stats = [
            'total_products'  => Product::where('status', 'dropship')->count(),
            'total_vendors'   => Product::where('status', 'dropship')->distinct('vendor_id')->count('vendor_id'),
            'total_inventory' => Inventory::whereHas('product', fn($q) => $q->where('status', 'dropship'))->sum('available_quantity'),
        ];

        $products = Product::where('status', 'dropship')
            ->with('vendor', 'category')
            ->latest()
            ->paginate(30);

        $vendors = Vendor::orderBy('company_name')->get();
        $warehouses = Warehouse::active()->orderBy('name')->get();

        return view('hod.dropship.index', compact('stats', 'products', 'vendors', 'warehouses'));
    }

    public function downloadProductTemplate()
    {
        //  $csv = "Vendor Name,SKU,SAP Code,Barcode,Product Name,Description,Hsn & Hts Code,Category,Material,Color,Length (cm),Width (cm),Height (cm),Weight (kg),HSN Code,FOB Price,Currency,Warehouse Name,Quantity\n";

        $csv = "Vendor Name,Vendor SKU,SAP Code,Barcode,Product Name,Product Description,Hsn & Hts Code,Product Length (In Inches),Product Width (In Inches),Product Height (In Inches),Product Weight(In Kg),Material Composition,Other Material,Color,Product Finish,Category,Sub Category,Qty In Inner Pack,Inner Carton Length(In Inches),Inner Carton Width(In Inches),Inner Carton Height(In Inches),Qty In Master Pack,Master Carton Length(In Inches),Master Carton Width(In Inches),Master Carton Height(In Inches),Master Carton Weight in (Kg),Inventory(Units / Sets),Vendor WSP,EB WSP,Comments\n";

        //  $csv .= "Glass Artisan Co,DS-SKU-001, SAP-001,BARCODE-001,Handmade Vase,Beautiful glass vase,Home Decor,Glass,Blue,30,15,15,1.2,7013,25.00,USD,US Warehouse,100\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Dropship-Product-Template.csv"',
        ]);
    }

    public function downloadInventoryTemplate()
    {
        $products = Product::where('status', 'dropship')->get();
        $csv = "Vendor SKU,Quantity\n";
        foreach ($products as $p) {
            $csv .= "{$p->sku},\n";
        }
        // $csv .= "DS-SKU-001,50\nDS-SKU-002,100\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Dropship-Inventory-Update-Template.csv"',
        ]);
    }

    public function uploadProducts(Request $request)
    {
        $request->validate([
            'company_code' => 'required|in:2000,2100,2200,2400',
            'product_file' => 'required|file|max:10240',
        ]);

        $file = $request->file('product_file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            return back()->with('error', 'File must be CSV or XLSX.');
        }

        try {
            $rows = $this->readFile($file->getRealPath(), $ext);
            if (count($rows) < 2) return back()->with('error', 'File is empty.');

            $result = $this->processProductRows($rows, $request->company_code);

            ActivityLog::log('uploaded', 'dropship', auth()->user(), null, [
                'company_code' => $request->company_code,
                'created' => $result['created'],
                'updated' => $result['updated'],
                'errors' => count($result['errors']),
            ], "Dropship: {$result['created']} created, {$result['updated']} updated");

            return back()->with('upload_result', $result)->with(
                $result['created'] + $result['updated'] > 0 ? 'success' : 'error',
                "{$result['created']} created, {$result['updated']} updated from {$result['total_rows']} rows." .
                    (count($result['errors']) > 0 ? ' ' . json_encode($result['errors']) . ' error(s).' : '')
            );
        } catch (\Exception $e) {
            \Log::error('Dropship upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    private function processProductRows(array $rows, string $companyCode): array
    {
        $header = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
        $colMap = [];
        $aliases = [
            'vendor_name' => ['vendor name', 'vendor', 'vendor_name'],
            'sku'         => ['sku', 'vendor sku', 'style code', 'product code'],
            'sap_code' => ['sap code', 'sap', 'sap_id'],
            'barcode' => ['barcode', 'upc', 'ean'],
            'name'        => ['product name', 'name', 'product_name', 'title'],
            'description' => ['description', 'product description'],
            'hsn_code'    => ['hsn code', 'hsn', 'hts code'],
            'length'      => ['product length',  'product length (in inches)'],
            'width'       => ['product width',  'product width (in inches)'],
            'height'      => ['product height', 'product height (in inches)'],
            'weight'      => ['product weight', 'product weight(in kg)'],
            'material'    => ['material composition'],
            'other_material'    => ['other material'],
            'color'       => ['color', 'colour'],
            'product_finish'       => ['product finish'],
            'category'    => ['category', 'product category'],
            'fob'         => ['fob price', 'fob', 'price', 'unit price', 'vendor price'],
            'qty_in_inner_pack' => ['qty in inner pack', 'quantity in inner pack', 'qty_inner_pack'],
            'inner_length' => ['inner carton length(in inches)', 'inner carton length', 'inner_length'],
            'inner_width' => ['inner carton width(in inches)', 'inner carton width', 'inner_width'],
            'inner_height' => ['inner carton height(in inches)', 'inner carton height', 'inner_height'],
            'master_length' => ['master carton length(in inches)', 'master carton length', 'master_length'],
            'master_width' => ['master carton width(in inches)', 'master carton width', 'master_width'],
            'master_height' => ['master carton height(in inches)', 'master carton height', 'master_height'],
            'master_weight_kg' => ['master carton weight in (kg)', 'master carton weight', 'master_weight_kg'],

            'currency'    => ['currency'],
            'warehouse'   => ['warehouse name', 'warehouse', 'warehouse_name'],
            //  'quantity'    => ['quantity', 'qty', 'stock', 'inventory'],
            'inventory'   => ['inventory', 'stock', 'available'],
            'inventory_units_sets' => ['inventory(units / sets)', 'inventory'],
            'vendor_wsp' => ['vendor wsp', 'vendor_wsp'],
            'eb_wsp' => ['eb wsp', 'eb_wsp'],
            'comments' => ['comments']

        ];

        foreach ($aliases as $key => $names) {
            foreach ($header as $i => $h) {
                if (in_array($h, $names)) {
                    $colMap[$key] = $i;
                    break;
                }
            }
        }

        $missing = [];
        foreach (['vendor_name', 'sku', 'name', 'sap_code'] as $req) {
            if (!isset($colMap[$req])) $missing[] = $req;
        }
        if (!empty($missing)) {
            return ['created' => 0, 'updated' => 0, 'errors' => ['Missing columns: ' . implode(', ', $missing)], 'total_rows' => 0];
        }

        $get = fn($row, $key) => isset($colMap[$key]) ? trim($row[$colMap[$key]] ?? '') : '';
        $created = 0;
        $updated = 0;
        $errors = [];
        $totalRows = count($rows) - 1;
        $currency = match ($companyCode) {
            '2000' => 'INR',
            '2200' => 'EUR',
            '2400' => 'GBP',
            default => 'USD'
        };

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNum = $i + 1;
            $sku = $get($row, 'sku');
            if (empty($sku)) continue;

            $vendorName = $get($row, 'vendor_name');
            $productName = $get($row, 'name');
            $qty = intval($get($row, 'quantity'));

            if (empty($vendorName)) {
                $errors[] = "Row {$rowNum}: Vendor Name empty.";
                continue;
            }
            if (empty($productName)) {
                $errors[] = "Row {$rowNum}: Product Name empty.";
                continue;
            }
            // if ($qty < 1) {
            //     $errors[] = "Row {$rowNum}: Invalid quantity.";
            //     continue;
            // }

            $product = Product::where('sku', $sku)->first();
            if ($product) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' is already in use.It is used by {$product->name})";
                continue;
            }

            $sapCode = Product::where('sap_code', $get($row, 'sap_code'))->first();
            if ($sapCode) {
                $errors[] = "Row {$rowNum}: SAP Code '{$get($row, 'sap_code')}' is already in use.It is used by {$sapCode->name} (SKU: {$sapCode->sku})";
                continue;
            }

            $vendor = Vendor::where('company_name', 'LIKE', "%{$vendorName}%")->first();
            if (!$vendor) {
                $errors[] = "Row {$rowNum}: Vendor '{$vendorName}' not found.";
                continue;
            }

            $warehouseName = $get($row, 'warehouse');
            $warehouse = $warehouseName
                ? Warehouse::where('name', 'LIKE', "%{$warehouseName}%")->first()
                : Warehouse::where('company_code', $companyCode)->first();
            if (!$warehouse) {
                $errors[] = "Row {$rowNum}: No warehouse found.";
                continue;
            }

            try {
                DB::beginTransaction();

                //   $product = Product::where('sku', $sku)->first();
                //   $isNew = !$product;
                $productData = [
                    'sap_code' => $get($row, 'sap_code'),
                    'name' => $productName,
                    'vendor_id' => $vendor->id,
                    'company_code' => $companyCode,
                    'status' => 'dropship',
                    'currency' => $get($row, 'currency') ?: $currency,
                ];

                if ($get($row, 'barcode')) $productData['barcode'] = $get($row, 'barcode');
                if ($get($row, 'description')) $productData['description'] = $get($row, 'description');
                if ($get($row, 'material')) $productData['material'] = $get($row, 'material');
                if ($get($row, 'color')) $productData['color'] = $get($row, 'color');
                if ($get($row, 'length')) $productData['length'] = floatval($get($row, 'length'));
                if ($get($row, 'width')) $productData['width'] = floatval($get($row, 'width'));
                if ($get($row, 'height')) $productData['height'] = floatval($get($row, 'height'));
                if ($get($row, 'weight')) $productData['weight'] = floatval($get($row, 'weight'));
                if ($get($row, 'hsn')) $productData['hsn_code'] = $get($row, 'hsn');
                if ($get($row, 'vendor_wsp')) $productData['vendor_wsp'] = floatval($get($row, 'vendor_wsp'));
                if ($get($row, 'eb_wsp')) $productData['eb_wsp'] = floatval($get($row, 'eb_wsp'));

                if ($get($row, 'fob')) {
                    $productData['fob_price'] = floatval($get($row, 'fob'));
                    $productData['vendor_price'] = floatval($get($row, 'fob'));
                }
                $l = floatval($get($row, 'length')) / 100;
                $w = floatval($get($row, 'width')) / 100;
                $h = floatval($get($row, 'height')) / 100;
                if ($l > 0 && $w > 0 && $h > 0) $productData['cbm'] = round($l * $w * $h, 6);

                $productData['product_details_dropship'] = json_encode([
                    'product_finish' => ($get($row, 'product_finish')) ? $get($row, 'product_finish') : null,
                    'other_material' => ($get($row, 'other_material')) ? $get($row, 'other_material') : null,
                    'inner_length' => ($get($row, 'inner_length')) ? floatval($get($row, 'inner_length')) : null,
                    'inner_width' => ($get($row, 'inner_width')) ? floatval($get($row, 'inner_width')) : null,
                    'inner_height' => ($get($row, 'inner_height')) ? floatval($get($row, 'inner_height')) : null,
                    'qty_in_inner_pack' => ($get($row, 'qty_in_inner_pack')) ? floatval($get($row, 'qty_in_inner_pack')) : null,
                    'master_length' => ($get($row, 'master_length')) ? floatval($get($row, 'master_length')) : null,
                    'master_width' => ($get($row, 'master_width')) ? floatval($get($row, 'master_width')) : null,
                    "master_height" => ($get($row, 'master_height')) ? floatval($get($row, 'master_height')) : null,
                    "master_weight_kg" => ($get($row, 'master_weight_kg')) ? floatval($get($row, 'master_weight_kg')) : null,
                    'inventory_units_sets' => ($get($row, 'inventory_units_sets')) ? $get($row, 'inventory_units_sets') : null,
                    'comments' => ($get($row, 'comments')) ? $get($row, 'comments') : null,
                ], true);

                // if ($isNew) {
                $productData['sku'] = $sku;
                $productData['stock_quantity'] = $qty;
                // echo '<pre>===';
                // print_r($header);
                // print_r($colMap);
                // print_r($productData);
                // echo '</pre>';
                // exit;
                $product = Product::create($productData);
                $created++;
                // } else {
                //     $product->update($productData);
                //     $product->increment('stock_quantity', $qty);
                //     $updated++;
                // }

                $inventory = Inventory::firstOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'company_code' => $companyCode],
                    ['quantity' => 0, 'reserved_quantity' => 0, 'available_quantity' => 0, 'received_date' => now()]
                );

                $inventory->increment('quantity', $qty);
                $inventory->increment('available_quantity', $qty);

                InventoryLog::record($inventory, 'dropship_inward', $qty, [
                    'description' => "Dropship upload: {$qty} units of {$sku}",
                    'reference_type' => 'dropship_upload',
                    'reference_code' => "DS-" . now()->format('YmdHis'),
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = "Row {$rowNum}: Failed — " . $e->getMessage();
            }
        }
        return ['created' => $created, 'updated' => $updated, 'errors' => $errors, 'total_rows' => $totalRows];
    }

    public function updateInventory(Request $request)
    {
        $request->validate([
            'warehouse_id'   => 'required|exists:warehouses,id',
            'inventory_file' => 'required|file|max:10240',
            'update_mode'    => 'required|in:add,set',
        ]);

        $file = $request->file('inventory_file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            return back()->with('error', 'File must be CSV or XLSX.');
        }

        try {
            $rows = $this->readFile($file->getRealPath(), $ext);
            if (count($rows) < 2) return back()->with('error', 'File is empty.');

            $header = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
            $skuCol = null;
            $qtyCol = null;
            foreach ($header as $i => $h) {
                if (in_array($h, ['vendor sku', 'sku', 'style code'])) $skuCol = $i;
                if (in_array($h, ['quantity', 'qty', 'stock'])) $qtyCol = $i;
            }
            if ($skuCol === null || $qtyCol === null) {
                return back()->with('error', 'File must have "Vendor SKU" and "Quantity" columns.');
            }

            //    $companyCode = $request->company_code;
            $warehouseId = $request->warehouse_id;
            $mode = $request->update_mode;
            $updated = 0;
            $errors = [];

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $rowNum = $i + 1;
                $sku = trim($row[$skuCol] ?? '');
                $qty = intval($row[$qtyCol] ?? 0);
                if (empty($sku)) continue;
                if ($qty < 0) {
                    $errors[] = "Row {$rowNum}: Invalid quantity.";
                    continue;
                }

                $product = Product::where('sku', $sku)->first();
                if (!$product) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' not found.";
                    continue;
                }
                $companyCode =  $product->company_code;

                $inventory = Inventory::firstOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouseId, 'company_code' => $companyCode],
                    ['quantity' => 0, 'reserved_quantity' => 0, 'available_quantity' => 0, 'received_date' => now()]
                );

                try {
                    DB::beginTransaction();

                    $prevQty = $inventory->quantity;

                    if ($mode === 'set') {
                        $newAvail = max(0, $qty - $inventory->reserved_quantity);
                        $changeQty = $qty - $prevQty;
                        $inventory->update(['quantity' => $qty, 'available_quantity' => $newAvail]);
                        $desc = "Set to {$qty} (was {$prevQty}) for {$sku}";
                        $action = 'inventory_set';
                    } else {
                        $inventory->increment('quantity', $qty);
                        $inventory->increment('available_quantity', $qty);
                        $changeQty = $qty;
                        $desc = "Added {$qty} to {$sku} (was {$prevQty}, now " . ($prevQty + $qty) . ")";
                        $action = 'inventory_add';
                    }

                    $product->update(['stock_quantity' => Inventory::where('product_id', $product->id)->sum('quantity')]);

                    InventoryLog::record($inventory, $action, $changeQty, [
                        'description' => $desc,
                        'reference_type' => 'dropship_inventory_update',
                        'reference_code' => "DSU-" . now()->format('YmdHis'),
                        'metadata' => ['mode' => $mode, 'row' => $rowNum],
                    ]);

                    DB::commit();
                    $updated++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = "Row {$rowNum}: Failed — " . $e->getMessage();
                }
            }

            ActivityLog::log('updated', 'dropship_inventory', auth()->user(), null, [
                'mode' => $mode,
                'updated' => $updated,
                'errors' => count($errors),
            ], "Dropship inventory: {$updated} SKUs ({$mode})");

            $msg = "{$updated} SKU(s) updated.";
            if (!empty($errors)) $msg .= ' ' . count($errors) . ' error(s).';

            return back()->with('inventory_result', ['updated' => $updated, 'errors' => $errors])
                ->with($updated > 0 ? 'success' : 'error', $msg);
        } catch (\Exception $e) {
            \Log::error('Dropship inventory update failed: ' . $e->getMessage());
            return back()->with('error', 'Failed: ' . $e->getMessage());
        }
    }

    private function readFile(string $fullPath, string $ext): array
    {
        if (in_array($ext, ['xlsx', 'xls'])) {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($fullPath);
            return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        }
        $rows = [];
        if (($handle = fopen($fullPath, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) $rows[] = $row;
            fclose($handle);
        }
        return $rows;
    }
    public function downloadProducts()
    {
        $products = Product::where('status', 'dropship')->with('vendor')->orderBy('sku')->get();

        $csv = "SKU,Product Name,Vendor Name,Description,Category,Material,Color,Length (inch),Width (inch),Height (inch),Weight (kg),CBM,HSN Code,FOB Price,Currency,SAP Code,Stock Qty,Company Code,Status,Created\n";

        foreach ($products as $p) {
            $csv .= implode(',', [
                '"' . ($p->sku ?? '') . '"',
                '"' . str_replace('"', '""', $p->name ?? '') . '"',
                '"' . str_replace('"', '""', $p->vendor->company_name ?? '') . '"',
                '"' . str_replace('"', '""', substr($p->description ?? '', 0, 100)) . '"',
                '"' . ($p->category->name ?? '') . '"',
                '"' . ($p->material ?? '') . '"',
                '"' . ($p->color ?? '') . '"',
                $p->length ?? '',
                $p->width ?? '',
                $p->height ?? '',
                $p->weight ?? '',
                $p->cbm ?? '',
                '"' . ($p->hsn_code ?? '') . '"',
                number_format(floatval($p->fob_price ?? 0), 2, '.', ''),
                $p->currency ?? 'USD',
                '"' . ($p->sap_code ?? '') . '"',
                $p->stock_quantity ?? 0,
                $p->company_code ?? '',
                $p->status ?? '',
                $p->created_at?->format('Y-m-d') ?? '',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Dropship-Products-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    public function downloadInventory(Request $request)
    {
        $inventory = Inventory::whereHas('product', fn($q) => $q->where('status', 'dropship'))
            ->with('product.vendor', 'warehouse')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->warehouse_id, fn($q, $v) => $q->where('warehouse_id', $v))
            ->get();

        $csv = "SKU,Product Name,Vendor Name,Warehouse,Quantity,Reserved,Available,Received Date,Company Code\n";

        foreach ($inventory as $inv) {
            $csv .= implode(',', [
                '"' . ($inv->product->sku ?? '') . '"',
                '"' . str_replace('"', '""', $inv->product->name ?? '') . '"',
                '"' . str_replace('"', '""', $inv->product->vendor->company_name ?? '') . '"',
                '"' . ($inv->warehouse->name ?? '') . '"',
                $inv->quantity ?? 0,
                $inv->reserved_quantity ?? 0,
                $inv->available_quantity ?? 0,
                $inv->received_date?->format('Y-m-d') ?? '',
                $inv->company_code ?? '',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Dropship-Inventory-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
