<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\{Order, SalesChannel, InventoryLog, OrderItem};
use App\Services\{DashboardService, SalesService};
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected SalesService $salesService
    ) {}

    public function dashboard(Request $request)
    {
        $companyCode = $request->get('company_code');
        $data = $this->dashboardService->getSalesDashboard($companyCode);
        return view('sales.dashboard', compact('data', 'companyCode'));
    }

    public function orders(Request $request)
    {
        $orders = Order::with('salesChannel', 'items.product')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->sales_channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
            ->when($request->search, fn($q, $v) => $q->where('order_number', 'like', "%{$v}%")->orWhere('platform_order_id', 'like', "%{$v}%"))
            ->latest('order_date')->paginate(30);
        $channels = SalesChannel::active()->get();
        return view('sales.orders.index', compact('orders', 'channels'));
    }

    public function uploadSales()
    {
        $channels = SalesChannel::active()->get();
        return view('sales.upload', compact('channels'));
    }

    public function downloadTemplate()
    {
        $csv = "Order Date,PO Number / Order ID,Invoice Number,Sales Channel,Vendor Name,Vendor Type,SAP code,Style Code,Per Unit Sales Price,Order Qty,Order Amount,Warehouse Name,Shipping Method,Customer Type,Customer Name,Company Name,Shipping Address,City,State,Zip Code,Country,Phone Number,Email\n";
        $csv .= "2026-05-10,70981308,,Amazon,,,,SKU1234,2.40,1,,,,CFL,John Doe,My Company,123 Main St,New York,NY,10001,US,1234567890,john@example.com\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Sales-Upload-Template.csv"',
        ]);
    }

    public function storeSales(Request $request)
    {
        $request->validate([
            'company_code' => 'required|in:2000,2100,2200',
            'sales_file'   => 'required|file|max:10240',
        ]);

        $file = $request->file('sales_file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            return back()->with('error', 'File must be CSV or XLSX format.');
        }

        try {
            //   $filePath = $file->store('temp', 'local');
            $fullPath = $file->getRealPath();

            //     $fullPath = storage_path('app/' . $filePath);

            if (in_array($ext, ['xlsx', 'xls'])) {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(false);
                $spreadsheet = $reader->load($fullPath);
                $rows = $spreadsheet->getActiveSheet()->toArray();
            } else {
                $rows = [];
                if (($handle = fopen($fullPath, 'r')) !== false) {
                    while (($row = fgetcsv($handle)) !== false) {
                        $rows[] = $row;
                    }
                    fclose($handle);
                }
            }

            @unlink($fullPath);

            if (count($rows) < 2) {
                return back()->with('error', 'File is empty or has no data rows.');
            }

            $result = $this->processUploadedRows($rows, $request->company_code);


            \App\Models\ActivityLog::log('uploaded', 'sales_data', auth()->user(), null, [
                'company_code' => $request->company_code,
                'created' => $result['created'],
                'errors' => count($result['errors']),
            ], "Sales data uploaded: {$result['created']} orders created");


            \Log::error('Upload result: ' . json_encode($result['errors']));

            return back()->with('upload_result', $result)->with(
                $result['created'] > 0 ? 'success' : 'error',
                "{$result['created']} order(s) created from {$result['total_rows']} rows.\n" .
                    (count($result['errors']) > 0 ? ' ' . implode(', ', $result['errors']) : '')
            );
        } catch (\Exception $e) {
            \Log::error('Sales upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }
    /**
     * Store manually entered orders from the grid form
     */
    public function storeManualOrders(Request $request)
    {
        $request->validate([
            'company_code'                     => 'required|in:2000,2100,2200',
            'orders'                           => 'required|array|min:1',
            'orders.*.platform_order_id'       => 'required|string',
            'orders.*.order_date'              => 'required|date',
            'orders.*.total_amount'            => 'required|numeric|min:0.01',
            'orders.*.items'                   => 'required|array|min:1',
            'orders.*.items.*.sku'             => 'required|string',
            'orders.*.items.*.quantity'         => 'required|integer|min:1',
            'orders.*.items.*.unit_price'       => 'required|numeric|min:0',
        ]);

        $companyCode = $request->company_code;
        $currency = match ($companyCode) {
            '2000' => 'INR',
            '2200' => 'EUR',
            default => 'USD'
        };

        // Invoice number prefix
        $fy = now()->month >= 4 ? now()->year . '-' . (now()->year + 1) : (now()->year - 1) . '-' . now()->year;
        $fyShort = substr($fy, 2, 2) . substr($fy, -2);
        $invoicePrefix = "EB{$companyCode}{$fyShort}";
        $lastInvoiceNum = \App\Models\Order::where('invoice_number', 'LIKE', "{$invoicePrefix}%")
            ->max(\DB::raw("CAST(SUBSTRING(invoice_number, " . (strlen($invoicePrefix) + 1) . ") AS UNSIGNED)")) ?? 0;

        $created = 0;
        $errors = [];

        foreach ($request->orders as $idx => $orderData) {
            $rowNum = $idx + 1;
            $poNumber = trim($orderData['platform_order_id']);

            // Check duplicate PO
            $existing = \App\Models\Order::where('platform_order_id', $poNumber)
                ->where('company_code', $companyCode)->first();
            if ($existing) {
                $errors[] = "Row {$rowNum}: PO '{$poNumber}' already exists.";
                continue;
            }

            // Process items
            $orderItems = [];
            $totalAmount = 0;
            $hasError = false;

            foreach ($orderData['items'] as $itemData) {
                $sku = trim($itemData['sku']);
                $qty = intval($itemData['quantity']);
                $unitPrice = floatval($itemData['unit_price']);

                $product = \App\Models\Product::where('sku', $sku)->first();
                if (!$product) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' not found.";
                    $hasError = true;
                    break;
                }

                if (empty($product->sap_code)) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' has no SAP code.";
                    $hasError = true;
                    break;
                }

                // Check inventory
                $availableStock = \App\Models\Inventory::where('product_id', $product->id)
                    ->where('company_code', $companyCode)
                    ->sum('available_quantity');
                if ($availableStock < $qty) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' insufficient inventory. Available: {$availableStock}, Ordered: {$qty}.";
                    $hasError = true;
                    break;
                }

                $lineTotal = round($unitPrice * $qty, 2);
                $totalAmount += $lineTotal;

                $orderItems[] = [
                    'product'    => $product,
                    'sku'        => $sku,
                    'qty'        => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if ($hasError) continue;

            try {
                \DB::beginTransaction();

                $lastInvoiceNum++;
                $invoiceNumber = $invoicePrefix . str_pad($lastInvoiceNum, 4, '0', STR_PAD_LEFT);

                $orderNumber = 'ORD-' . $companyCode . '-' . str_pad(
                    \App\Models\Order::where('company_code', $companyCode)->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

                // Match sales channel
                $channelName = $orderData['sales_channel'] ?? '';
                $channel = $channelName ? \App\Models\SalesChannel::where('name', 'LIKE', "%{$channelName}%")->first() : null;

                $order = \App\Models\Order::create([
                    'order_number'      => $orderNumber,
                    'platform_order_id' => $poNumber,
                    'invoice_number'    => $invoiceNumber,
                    'sales_channel_id'  => $channel->id ?? null,
                    'company_code'      => $companyCode,
                    'order_date'        => $orderData['order_date'],
                    'subtotal'          => $totalAmount,
                    'total_amount'      => floatval($orderData['total_amount']),
                    'currency'          => $orderData['currency'] ?? $currency,
                    'customer_name'     => $orderData['customer_name'] ?? null,
                    'customer_email'    => $orderData['customer_email'] ?? null,
                    'payment_status'    => 'unpaid',
                    'status'            => 'pending',
                    'uploaded_by'       => auth()->id(),
                ]);

                foreach ($orderItems as $oi) {
                    \App\Models\OrderItem::create([
                        'order_id'    => $order->id,
                        'product_id'  => $oi['product']->id,
                        'vendor_id'   => $oi['product']->vendor_id,
                        'sku'         => $oi['sku'],
                        'quantity'    => $oi['qty'],
                        'unit_price'  => $oi['unit_price'],
                        'total_price' => $oi['line_total'],
                    ]);

                    // Reserve inventory
                    $inventory = \App\Models\Inventory::where('product_id', $oi['product']->id)
                        ->where('company_code', $companyCode)
                        ->where('available_quantity', '>', 0)
                        ->first();

                    if ($inventory) {
                        $inventory->decrement('available_quantity', $oi['qty']);
                        $inventory->increment('reserved_quantity', $oi['qty']);

                        \App\Models\InventoryLog::record($inventory, \App\Models\InventoryLog::ACTION_ORDER_PLACED, 0, [
                            'change_available' => -$oi['qty'],
                            'change_reserved'  => $oi['qty'],
                            'reference_type'   => 'order',
                            'reference_id'     => $order->id,
                            'reference_code'   => $order->order_number,
                        ]);
                    }
                }

                \DB::commit();
                $created++;
            } catch (\Exception $e) {
                \DB::rollBack();
                $errors[] = "Row {$rowNum}: Failed — " . $e->getMessage();
            }
        }

        \App\Models\ActivityLog::log('created', 'order', auth()->user(), null, [
            'company_code' => $companyCode,
            'created' => $created,
            'errors' => count($errors),
        ], "Manual sales entry: {$created} orders created");

        $msg = "{$created} order(s) created.";
        if (!empty($errors)) {
            $msg .= ' ' . count($errors) . ' error(s): ' . implode('; ', array_slice($errors, 0, 5));
        }

        return redirect()->route('sales.orders')->with($created > 0 ? 'success' : 'error', $msg);
    }
    private function processUploadedRows(array $rows, string $companyCode): array
    {
        // Map header columns
        $header = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
        $colMap = [];
        $colAliases = [
            'order_date'    => ['order date', 'date', 'order_date'],
            'po_number'     => ['po number / order id', 'po number', 'order id', 'po_number', 'order_id'],
            'invoice'       => ['invoice number', 'invoice', 'invoice_number'],
            'channel'       => ['sales channel', 'channel', 'platform'],
            'sku'           => ['style code', 'sku', 'style_code', 'vendor sku'],
            'unit_price'    => ['per unit sales price', 'unit price', 'price', 'unit_price', 'sales price'],
            'qty'           => ['order qty', 'qty', 'quantity', 'order_qty'],
            'ship_method'   => ['shipping method', 'ship method', 'shipping_method'],
            'cust_type'     => ['customer type', 'customer_type'],
            'cust_name'     => ['customer name', 'customer_name'],
            'company_name'  => ['company name', 'company_name'],
            'address'       => ['shipping address', 'address'],
            'city'          => ['city'],
            'state'         => ['state'],
            'zip'           => ['zip code', 'zip', 'pincode', 'zip_code'],
            'country'       => ['country'],
            'phone'         => ['phone number', 'phone', 'phone_number'],
            'email'         => ['email'],
        ];

        foreach ($colAliases as $key => $aliases) {
            foreach ($header as $i => $h) {
                if (in_array($h, $aliases)) {
                    $colMap[$key] = $i;
                    break;
                }
            }
        }

        // Validate required columns
        $missing = [];
        foreach (['order_date', 'po_number', 'sku', 'unit_price', 'qty'] as $req) {
            if (!isset($colMap[$req])) $missing[] = $req;
        }
        if (!empty($missing)) {
            return ['created' => 0, 'errors' => ['Missing required columns: ' . implode(', ', $missing)], 'total_rows' => 0];
        }

        $get = function ($row, $key) use ($colMap) {
            return isset($colMap[$key]) ? trim($row[$colMap[$key]] ?? '') : '';
        };

        $created = 0;
        $errors = [];
        $totalRows = count($rows) - 1;

        // Generate invoice number prefix: CompanyCode + FY
        $fy = now()->month >= 4 ? now()->year . '-' . (now()->year + 1) : (now()->year - 1) . '-' . now()->year;
        $fyShort = substr($fy, 2, 2) . substr($fy, -2);
        $invoicePrefix = "EB{$companyCode}{$fyShort}";
        $lastInvoiceNum = \App\Models\Order::where('invoice_number', 'LIKE', "{$invoicePrefix}%")
            ->max(\DB::raw("CAST(SUBSTRING(invoice_number, " . (strlen($invoicePrefix) + 1) . ") AS UNSIGNED)")) ?? 0;

        $currency = match ($companyCode) {
            '2000' => 'INR',
            '2200' => 'EUR',
            default => 'USD'
        };

        // Add this helper inside processUploadedRows, before the for loop:

        $parseDate = function ($val) {
            if (empty($val)) {
                return null;
            }
            // Excel serial number (numeric, typically 40000-60000 range)
            if (is_numeric($val) && $val > 25000 && $val < 60000) {
                return \Carbon\Carbon::createFromFormat('d-m-Y', '1899-12-30')
                    ->addDays(intval($val))->toDateString();
            }
            // Normal date string
            try {
                return \Carbon\Carbon::parse($val)->toDateString();
            } catch (\Exception $e) {
                return null;
            }
        };

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNum = $i + 1;

            // Skip empty rows
            $sku = $get($row, 'sku');
            if (empty($sku)) continue;

            $orderDate = $parseDate($get($row, 'order_date'));
            $poNumber = $get($row, 'po_number');
            $unitPrice = $get($row, 'unit_price');
            $qty = $get($row, 'qty');

            // Validate required fields
            if (empty($orderDate)) {
                $errors[] = "Row {$rowNum}: Order Date is empty.";
                continue;
            }
            if (empty($poNumber)) {
                $errors[] = "Row {$rowNum}: PO Number is empty.";
                continue;
            }
            if (!is_numeric($unitPrice)) {
                $errors[] = "Row {$rowNum}: Invalid unit price '{$unitPrice}'.";
                continue;
            }
            if (!is_numeric($qty) || intval($qty) < 1) {
                $errors[] = "Row {$rowNum}: Invalid quantity '{$qty}'.";
                continue;
            }

            // Parse date
            try {
                $orderDate = \Carbon\Carbon::parse($orderDate)->toDateString();
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum}: Invalid date format '{$get($row, 'order_date')}'.";
                continue;
            }

            // Look up product by SKU
            $product = \App\Models\Product::where('sku', $sku)->first();
            if (!$product) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' not found in the system.";
                continue;
            }

            // Check SAP code exists
            if (empty($product->sap_code)) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' has no SAP code assigned. Skipping.";
                continue;
            }

            // Check available inventory
            $availableStock = \App\Models\Inventory::where('product_id', $product->id)
                ->where('company_code', $companyCode)
                ->sum('available_quantity');

            if ($availableStock < $qty) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' insufficient inventory. Available: {$availableStock}, Ordered: {$qty}.";
                continue;
            }

            // Look up vendor
            $vendor = $product->vendor;
            $vendorId = $vendor->id ?? null;

            // Check duplicate PO
            $existingOrder = \App\Models\Order::where('platform_order_id', $poNumber)
                ->where('company_code', $companyCode)->first();
            if ($existingOrder) {
                $errors[] = "Row {$rowNum}: PO '{$poNumber}' already exists (Order #{$existingOrder->order_number}).";
                continue;
            }

            // Calculate
            $unitPrice = floatval($unitPrice);
            $qty = intval($qty);
            $orderAmount = round($unitPrice * $qty, 2);

            // Generate invoice number
            $lastInvoiceNum++;
            $invoiceNumber = $invoicePrefix . str_pad($lastInvoiceNum, 4, '0', STR_PAD_LEFT);

            // Match sales channel
            $channelName = $get($row, 'channel');
            $channel = null;
            if ($channelName) {
                $channel = \App\Models\SalesChannel::where('name', 'LIKE', "%{$channelName}%")->first();
            }

            // Get warehouse from vendor
            $warehouseId = null;
            if ($vendor) {
                $inventory = \App\Models\Inventory::where('product_id', $product->id)->first();
                $warehouseId = $inventory->warehouse_id ?? null;
            }

            try {
                \DB::beginTransaction();

                // Generate order number
                $orderNumber = 'ORD-' . $companyCode . '-' . str_pad(
                    \App\Models\Order::where('company_code', $companyCode)->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

                $order = \App\Models\Order::create([
                    'order_number'      => $orderNumber,
                    'platform_order_id' => $poNumber,
                    'invoice_number'    => $invoiceNumber,
                    'sales_channel_id'  => $channel->id ?? null,
                    'company_code'      => $companyCode,
                    'order_date'        => $orderDate,
                    'subtotal'          => $orderAmount,
                    'total_amount'      => $orderAmount,
                    'currency'          => $currency,
                    'customer_name'     => $get($row, 'cust_name') ?: null,
                    'customer_email'    => $get($row, 'email') ?: null,
                    'customer_phone'    => $get($row, 'phone') ?: null,
                    'customer_type'     => $get($row, 'cust_type') ?: null,
                    'company_name'      => $get($row, 'company_name') ?: null,
                    'shipping_address'  => $get($row, 'address') ?: null,
                    'shipping_city'     => $get($row, 'city') ?: null,
                    'shipping_state'    => $get($row, 'state') ?: null,
                    'shipping_country'  => $get($row, 'country') ?: null,
                    'shipping_pincode'  => $get($row, 'zip') ?: null,
                    'shipping_method'   => $get($row, 'ship_method') ?: null,
                    'warehouse_id'      => $warehouseId,
                    'payment_status'    => 'unpaid',
                    'status'            => 'pending',
                    'uploaded_by'       => auth()->id(),
                ]);

                \App\Models\OrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => $product->id,
                    'vendor_id'   => $vendorId,
                    'sku'         => $sku,
                    'quantity'    => $qty,
                    'unit_price'  => $unitPrice,
                    'total_price' => $orderAmount,
                ]);

                \DB::commit();
                $created++;

                // Deduct inventory
                $inventory = \App\Models\Inventory::where('product_id', $product->id)
                    ->where('company_code', $companyCode)
                    ->where('available_quantity', '>', 0)
                    ->first();

                if ($inventory) {
                    $inventory->decrement('available_quantity', $qty);
                    $inventory->increment('reserved_quantity', $qty);
                }
                \App\Models\Product::where('id', $product->id)->decrement('stock_quantity', $qty);

                InventoryLog::record($inventory, InventoryLog::ACTION_ORDER_PLACED, 0, [
                    'change_available' => -$qty,
                    'change_reserved'  => $qty,
                    'reference_type'   => 'order',
                    'reference_id'     => $order->id,
                    'reference_code'   => $order->order_number,
                ]);
            } catch (\Exception $e) {
                \DB::rollBack();
                $errors[] = "Row {$rowNum}: Failed to create order — " . $e->getMessage();
            }
        }

        return ['created' => $created, 'errors' => $errors, 'total_rows' => $totalRows];
    }
    public function showOrder(Order $order)
    {
        $order->load('salesChannel', 'items.product.vendor', 'customer', 'receivable', 'chargebacks', 'uploader');
        return view('sales.orders.show', compact('order'));
    }
    public function updateTracking(Request $request, Order $order)
    {
        $request->validate(['tracking_id' => 'required|string']);
        $this->salesService->updateTracking($order, $request->tracking_id, $request->tracking_url, $request->shipping_provider);
        return back()->with('success', 'Tracking updated.');
    }

    /**
     * To Be Shipped — orders pending shipment (shipping_method != Store Pickup, no tracking yet)
     */
    public function toBeShipped(Request $request)
    {
        $orders = Order::with('items.product', 'salesChannel')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNull('shipping_method')->orWhere('shipping_method', '!=', '1');
            })
            ->where(function ($q) {
                $q->whereNull('tracking_id')->orWhere('tracking_id', '');
            })
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->latest('order_date')
            ->paginate(30)->withQueryString();

        // Calculate ageing for each order
        $orders->getCollection()->transform(function ($order) {
            $orderDate = $order->order_date ? \Carbon\Carbon::parse($order->order_date) : now();
            $order->ageing_days = $orderDate->diffInDays(now());
            $order->is_overdue = $order->ageing_days > 2;
            return $order;
        });

        $channels = SalesChannel::active()->get();

        $stats = [
            'total_pending'  => $orders->total(),
            'overdue'        => $orders->getCollection()->where('is_overdue', true)->count(),
            'total_value'    => $orders->getCollection()->sum('total_amount'),
        ];

        return view('sales.to-be-shipped', compact('orders', 'channels', 'stats'));
    }

    /**
     * Update shipping details for an order (AJAX or form)
     */
    public function updateShipping1(Request $request, Order $order)
    {
        $request->validate([
            'shipped_qty'    => 'required|integer|min:1',
            'tracking_id'    => 'required|string|max:100',
            'shipping_cost'  => 'nullable|numeric|min:0',
            'carrier'        => 'required|in:Fedex,UPS,USPS,LTL,Other',
        ]);

        $firstItem = $order->items->first();
        $unitPrice = $firstItem ? floatval($firstItem->unit_price) : 0;
        $shippedAmount = round($unitPrice * intval($request->shipped_qty), 2);

        $order->update([
            'shipped_qty'      => $request->shipped_qty,
            'shipped_amount'   => $shippedAmount,
            'tracking_id'      => $request->tracking_id,
            'shipping_cost'    => $request->shipping_cost,
            'carrier'          => $request->carrier,
            'shipping_provider' => $request->carrier,
            'shipment_status'  => 'shipped',
            'status'           => 'shipped',
            'shipped_date'     => now(),
        ]);

        InventoryLog::record($inventory, InventoryLog::ACTION_ORDER_SHIPPED, -$shippedQty, [
            'change_available' => 0,
            'change_reserved'  => -$shippedQty,
            'reference_type'   => 'order',
            'reference_id'     => $order->id,
            'reference_code'   => $order->order_number,
            'metadata'         => ['tracking_id' => $request->tracking_id, 'carrier' => $request->carrier],
        ]);

        \App\Models\ActivityLog::log('shipped', 'order', $order, null, [
            'tracking_id' => $request->tracking_id,
            'carrier' => $request->carrier,
            'shipped_qty' => $request->shipped_qty,
        ], "Order {$order->order_number} shipped via {$request->carrier}");

        return back()->with('success', "Order {$order->order_number} marked as shipped. Tracking: {$request->tracking_id}");
    }
    public function updateShipping(Request $request, Order $order)
    {
        $request->validate([
            'shipped_qty'    => 'required|integer|min:1',
            'tracking_id'    => 'required|string|max:100',
            'shipping_cost'  => 'nullable|numeric|min:0',
            'carrier'        => 'required|in:Fedex,UPS,USPS,LTL,Other',
        ]);

        $firstItem = $order->items->first();
        $unitPrice = $firstItem ? floatval($firstItem->unit_price) : 0;
        $shippedQty = intval($request->shipped_qty);
        $shippedAmount = round($unitPrice * $shippedQty, 2);

        $order->update([
            'shipped_qty'       => $shippedQty,
            'shipped_amount'    => $shippedAmount,
            'tracking_id'       => $request->tracking_id,
            'shipping_cost'     => $request->shipping_cost,
            'carrier'           => $request->carrier,
            'shipping_provider' => $request->carrier,
            'shipment_status'   => 'shipped',
            'status'            => 'shipped',
            'shipped_date'      => now(),
        ]);

        // Log inventory change
        if ($firstItem && $firstItem->product_id) {
            $inventory = \App\Models\Inventory::where('product_id', $firstItem->product_id)
                ->where('company_code', $order->company_code)
                ->first();

            if ($inventory) {
                // Move from reserved to shipped (deduct from total and reserved)
                $inventory->decrement('quantity', $shippedQty);
                $inventory->decrement('reserved_quantity', $shippedQty);

                \App\Models\InventoryLog::record($inventory, \App\Models\InventoryLog::ACTION_ORDER_SHIPPED, -$shippedQty, [
                    'change_available' => 0,
                    'change_reserved'  => -$shippedQty,
                    'reference_type'   => 'order',
                    'reference_id'     => $order->id,
                    'reference_code'   => $order->order_number,
                    'metadata'         => ['tracking_id' => $request->tracking_id, 'carrier' => $request->carrier],
                ]);

                \App\Models\Product::where('id', $firstItem->product_id)->decrement('stock_quantity', $shippedQty);
            }
        }

        \App\Models\ActivityLog::log('shipped', 'order', $order, null, [
            'tracking_id' => $request->tracking_id,
            'carrier' => $request->carrier,
            'shipped_qty' => $shippedQty,
        ], "Order {$order->order_number} shipped via {$request->carrier}");

        return back()->with('success', "Order {$order->order_number} marked as shipped. Tracking: {$request->tracking_id}");
    }
    public function orderManagement1(Request $request)
    {
        $orders = Order::with(['salesChannel', 'items.product'])
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest('order_date')
            ->paginate(30)
            ->withQueryString();

        $channels = SalesChannel::active()->get();

        return view('sales.order-management', compact('orders', 'channels'));
    }
    /**
     * Order Management — all shipped orders with tracking, status, delivery, ageing
     */
    public function orderManagement(Request $request)
    {
        $orders = Order::with('items.product', 'salesChannel')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNotNull('tracking_id')->where('tracking_id', '!=', '');
            })
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('current_status', $v))
            ->latest('ship_date')
            ->paginate(30)->withQueryString();

        // Calculate ageing for each order
        $orders->getCollection()->transform(function ($order) {
            $shipDate = $order->ship_date ? \Carbon\Carbon::parse($order->ship_date) : ($order->shipped_date ? \Carbon\Carbon::parse($order->shipped_date) : null);

            if ($shipDate && $order->current_status !== 'delivered') {
                $days = $shipDate->diffInDays(now());
                $order->ageing_days = $days;
                if ($days >= 10) {
                    $order->ageing_label = 'Critical';
                    $order->ageing_color = '#7c2d12';
                    $order->ageing_bg = '#fef2f2';
                } elseif ($days >= 7) {
                    $order->ageing_label = 'Overdue';
                    $order->ageing_color = '#dc2626';
                    $order->ageing_bg = '#fef2f2';
                } elseif ($days >= 5) {
                    $order->ageing_label = 'Due';
                    $order->ageing_color = '#e8a838';
                    $order->ageing_bg = '#fefce8';
                } else {
                    $order->ageing_label = 'On Track';
                    $order->ageing_color = '#16a34a';
                    $order->ageing_bg = '';
                }
            } else {
                $order->ageing_days = 0;
                $order->ageing_label = $order->current_status === 'delivered' ? 'Delivered' : '—';
                $order->ageing_color = '#16a34a';
                $order->ageing_bg = $order->current_status === 'delivered' ? '#f0fdf4' : '';
            }
            return $order;
        });

        $channels = SalesChannel::active()->get();

        $stats = [
            'total_shipped'  => $orders->total(),
            'delivered'      => Order::whereNotNull('tracking_id')->where('current_status', 'delivered')->count(),
            'in_transit'     => Order::whereNotNull('tracking_id')->where('tracking_id', '!=', '')->where(function ($q) {
                $q->whereNull('current_status')->orWhere('current_status', '!=', 'delivered');
            })->count(),
            'critical'       => $orders->getCollection()->where('ageing_label', 'Critical')->count(),
        ];

        return view('sales.order-management', compact('orders', 'channels', 'stats'));
    }

    /**
     * Update order management fields (status, delivery date, material cost, charges, remarks)
     */
    public function updateOrderManagement(Request $request, Order $order)
    {
        $request->validate([
            'current_status'           => 'nullable|in:in_transit,out_for_delivery,delivered,returned,exception',
            'ship_date'                => 'nullable|date',
            'material_cost'            => 'nullable|numeric|min:0',
            'order_processing_charges' => 'nullable|numeric|min:0',
            'remarks'                  => 'nullable|string|max:500',
        ]);

        $data = $request->only(['current_status', 'ship_date', 'material_cost', 'order_processing_charges', 'remarks']);

        // Auto-set delivery date when status changes to delivered
        if ($request->current_status === 'delivered' && !$order->delivered_date) {
            $data['delivered_date'] = now()->toDateString();
            $data['status'] = 'delivered';
        }

        $order->update($data);

        return back()->with('success', "Order {$order->order_number} updated.");
    }
    public function updateCosts(Request $request, Order $order)
    {
        $request->validate([
            'material_cost' => 'nullable|numeric|min:0',
            'processing_charges' => 'nullable|numeric|min:0',
        ]);

        $item = $order->items()->first();
        if ($item) {
            $item->update([
                'material_cost' => $request->material_cost,
                'processing_charges' => $request->processing_charges,
            ]);
        }

        return response()->json(['success' => true]);
    }
}
