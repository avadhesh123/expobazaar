<?php

namespace App\Http\Controllers;

use App\Models\{Product, Inventory, Vendor, InventoryLog};
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DropshipController extends Controller
{
    public function index()
    {
        return view('dropship.index');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'dropship_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $file = $request->file('dropship_file');
        $path = $file->store('temp', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            @unlink($fullPath);

            $created = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $key => $row) {
                if ($key === 0) {
                    continue;
                } // Skip header

                $vendorSku = trim($row[0] ?? '');
                $productName = trim($row[1] ?? '');
                $vendorName = trim($row[2] ?? '');
                $qty = (int)($row[3] ?? 0);
                $unitPrice = (float)($row[4] ?? 0);
                $vendorPayoutPrice = (float)($row[5] ?? 0);

                if (empty($vendorSku) || empty($productName) || $qty <= 0) {
                    $errors[] = "Row " . ($key + 1) . ": Invalid data";
                    continue;
                }

                $vendor = Vendor::where('company_name', 'LIKE', "%{$vendorName}%")->first();

                if (!$vendor) {
                    $errors[] = "Row " . ($key + 1) . ": Vendor not found - {$vendorName}";
                    continue;
                }

                // Create or Update Product
                $product = Product::updateOrCreate(
                    ['vendor_sku' => $vendorSku],
                    [
                        'name' => $productName,
                        'sku' => $vendorSku,           // or generate unique SKU
                        'vendor_id' => $vendor->id,
                        'vendor_sku' => $vendorSku,
                        'vendor_payout_price' => $vendorPayoutPrice,
                        'status' => 'active',
                    ]
                );

                // Create/Update Inventory (Main Warehouse)
                $inventory = Inventory::updateOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => 1], // Default Warehouse
                    ['quantity' => $qty]
                );

                // Log
                InventoryLog::create([
                    'product_id' => $product->id,
                    'warehouse_id' => 1,
                    'type' => 'dropship_upload',
                    'qty_before' => $inventory->quantity - $qty,
                    'qty_after' => $inventory->quantity,
                    'qty_change' => $qty,
                    'remarks' => 'Dropship bulk upload',
                    'created_by' => auth()->id(),
                ]);

                $product->wasRecentlyCreated ? $created++ : $updated++;
            }

            return back()->with('success', "$created products created, $updated updated. Inventory updated successfully.");

        } catch (\Exception $e) {
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }
}
