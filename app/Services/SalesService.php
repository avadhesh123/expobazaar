<?php

namespace App\Services;

use App\Models\{Order, OrderItem, Customer, SalesChannel, Product, FinanceReceivable, Inventory, InventoryLog, ActivityLog, User};
use Illuminate\Support\Facades\DB;

class SalesService
{
    /**
     * Clean numeric values from Excel (may contain line breaks, spaces, tabs)
     */
    private static $orderCounter = [];

    private function cleanNum($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = preg_replace('/[\r\n\t\s]+/', '', trim($value));
        return is_numeric($clean) ? $clean : $value;
    }
    private function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        // Try to convert to proper UTF-8
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }
        // Remove any remaining invalid UTF-8 bytes
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        return $value;
    }
    // ═══════════════════════════════════════════════════════
    //  SHARED HELPERS
    // ═══════════════════════════════════════════════════════

    /**
     * Generate invoice number: EB + CompanyCode + FY + Sequence
     */
    public function generateInvoiceNumber(string $companyCode): string
    {
        $fy = now()->month >= 4 ? now()->year . '-' . (now()->year + 1) : (now()->year - 1) . '-' . now()->year;
        $fyShort = substr($fy, 2, 2) . substr($fy, -2);
        $prefix = "EB{$companyCode}{$fyShort}";

        $lastNum = Order::where('invoice_number', 'LIKE', "{$prefix}%")
            ->max(DB::raw("CAST(SUBSTRING(invoice_number, " . (strlen($prefix) + 1) . ") AS UNSIGNED)")) ?? 0;

        return $prefix . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate order number: ORD-CompanyCode-Sequence
     */
    public function generateOrderNumber17092026(string $companyCode): string
    {
        $count = Order::where('company_code', $companyCode)->count() + 1;
        return 'ORD-' . $companyCode . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    }



    public function generateOrderNumber(string $companyCode): string
    {
        $prefix = 'ORD-' . $companyCode . '-';

        // Always get fresh MAX from DB (handles rollbacks)
        $lastNum = (int) Order::withoutGlobalScopes()
            ->where('order_number', 'like', $prefix . '%')
            ->selectRaw("MAX(CAST(REPLACE(order_number, '{$prefix}', '') AS UNSIGNED)) as max_num")
            ->value('max_num');

        // Also check static counter (handles batch inserts within same request)
        if (isset(self::$orderCounter[$companyCode]) && self::$orderCounter[$companyCode] > $lastNum) {
            $lastNum = self::$orderCounter[$companyCode];
        }

        $next = $lastNum + 1;

        // Safety: keep incrementing if somehow exists
        $orderNumber = $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
        while (Order::withoutGlobalScopes()->where('order_number', $orderNumber)->exists()) {
            $next++;
            $orderNumber = $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
        }

        self::$orderCounter[$companyCode] = $next;

        return $orderNumber;
    }

    /**
     * Get currency from company code
     */
    public function getCurrency(string $companyCode): string
    {
        return match ($companyCode) {
            '2000' => 'INR',
            '2100' => 'USD',
            '2200' => 'EUR',
            '2400' => 'GBP',
            default => 'USD'
        };
    }

    /**
     * Create a single order with items, inventory reservation, receivable, and log
     * Used by both file upload and manual entry
     */
    public function createOrder(array $orderData, array $items, string $companyCode, int  $warehouseId, ?string $uploadDate = null): Order
    {
        return DB::transaction(function () use ($orderData, $items, $companyCode, $warehouseId, $uploadDate) {

            $currency = $this->getCurrency($companyCode);

            // Match sales channel
            $channelName = $orderData['sales_channel'] ?? $orderData['channel'] ?? null;
            $channel = $channelName
                ? SalesChannel::where('name', 'LIKE', "%{$channelName}%")->orWhere('slug', $channelName)->first()
                : null;

            $shipMethod = strtolower($orderData['shipping_method'] ?? '');
            // Create order
            $order = Order::create([
                'order_number'      => $this->generateOrderNumber($companyCode),
                'platform_order_id' => $orderData['platform_order_id'] ?? $orderData['po_number'] ?? null,
                'invoice_number'    => $this->generateInvoiceNumber($companyCode),
                'sales_channel_id'  => $channel->id ?? $orderData['sales_channel_id'] ?? null,
                'company_code'      => $companyCode,
                'order_date'        => $orderData['order_date'] ?? now(),
                'subtotal'          => $orderData['subtotal'] ?? $orderData['total_amount'] ?? 0,
                'total_amount'      => $orderData['total_amount'] ?? 0,
                'shipping_amount'   => $orderData['shipping_amount'] ?? 0,
                'tax_amount'        => $orderData['tax_amount'] ?? 0,
                'discount_amount'   => $orderData['discount_amount'] ?? 0,
                'currency'          => $orderData['currency'] ?? $currency,
              //  'customer_name'     => $orderData['customer_name'] ?? null,
                'customer_name'    => $this->sanitizeString($orderData['customer_name'] ?? null),
                'customer_email'    => $orderData['customer_email'] ?? null,
                'customer_phone'    => $orderData['customer_phone'] ?? null,
                'customer_type'     => $orderData['customer_type'] ?? null,
                'company_name'      => $this->sanitizeString($orderData['company_name'] ?? null),
                'shipping_address'  => $this->sanitizeString($orderData['shipping_address'] ?? null),
                'shipping_city'     => $this->sanitizeString($orderData['shipping_city'] ?? null),
                'shipping_state'    => $this->sanitizeString($orderData['shipping_state'] ?? null),
                'shipping_country'  => $this->sanitizeString($orderData['shipping_country'] ?? null),
                'shipping_pincode'  => $orderData['shipping_pincode'] ?? null,
                'shipping_method'   => $shipMethod ?? null,
                'warehouse_id'      => $orderData['warehouse_id'] ?? null,
                'payment_status'    => $orderData['payment_status'] ?? 'unpaid',
                'status'            => ($shipMethod === 'sp') ? 'delivered' : 'open',
                'uploaded_by'       => auth()->id(),
                'created_at'        => $uploadDate ? \Carbon\Carbon::parse($uploadDate) : now(),
            ]);

            // Create order items and reserve inventory
            foreach ($items as $item) {
                $product = $item['product'] instanceof Product ? $item['product'] : Product::find($item['product_id']);
                $quantity  = intval($item['quantity'] ?? $item['qty'] ?? 1);
                OrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => $product->id,
                    'vendor_id'   => $product->vendor_id,
                    'sku'         => $item['sku'] ?? $product->sku,
                    'quantity'    => $quantity,
                    'shipped_qty' => ($shipMethod === 'sp') ? ($quantity) : 0,
                    'shipped_amount' => ($shipMethod === 'sp') ? ($quantity * ($item['unit_price'] ?? 0)) : 0,
                    'unit_price'  => $item['unit_price'] ?? 0,
                    'total_price' =>   $item['line_total'] ?? round(($item['unit_price'] ?? 0) * ($quantity), 2),
                ]);

                // Reserve inventory
                $this->reserveInventory($product->id, $companyCode, $quantity, $order, $warehouseId, $shipMethod);
            }

            if ($shipMethod === 'sp') {
                $order->update([
                    'shipped_qty'    => $order->items()->sum('shipped_qty'),
                    'shipped_amount' => $order->items()->sum('shipped_amount'),
                    'shipped_date'   => now(),
                    'shipment_status' => 'delivered',
                ]);
            }

            // Create finance receivable
            $this->createReceivable($order, $channel);

            return $order;
        });
    }

    /**
     * Reserve inventory for an order item
     */
    private function reserveInventory(int $productId, string $companyCode, int $qty, Order $order, int  $warehouseId, string $shipMethod): void
    {
        $inventory = Inventory::where('product_id', $productId)
            ->where('company_code', $companyCode)
            ->where('warehouse_id', $warehouseId)
            ->where('available_quantity', '>', 0)
            ->first();

        if ($inventory) {
            $inventory->decrement('available_quantity', $qty);
            if ($shipMethod !== 'sp') {
                $inventory->increment('reserved_quantity', $qty);
            }

            InventoryLog::record($inventory, InventoryLog::ACTION_ORDER_PLACED, 0, [
                'change_available' => -$qty,
                'change_reserved'  => $qty,
                'reference_type'   => 'order',
                'reference_id'     => $order->id,
                'reference_code'   => $order->order_number,
            ]);
        }

        Product::where('id', $productId)->decrement('stock_quantity', $qty);
    }

    /**
     * Create finance receivable for an order
     */
    private function createReceivable(Order $order, ?SalesChannel $channel): void
    {
        try {
            FinanceReceivable::create([
                'order_id'         => $order->id,
                'sales_channel_id' => $channel->id ?? $order->sales_channel_id,
                'company_code'     => $order->company_code,
                'order_amount'     => $order->total_amount,
                'net_receivable'   => $order->total_amount,
            ]);
        } catch (\Exception $e) {
            \Log::warning("FinanceReceivable creation failed for order {$order->order_number}: " . $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════
    //  FILE UPLOAD (CSV / XLSX)
    // ═══════════════════════════════════════════════════════

    /**
     * Process uploaded file rows and create orders
     */
    public function processUploadedRows(array $rows, string $companyCode, ?string $uploadDate = null): array
    {
        $header = array_map(fn ($h) => strtolower(trim($h ?? '')), $rows[0]);
        $colMap = [];

        $colAliases = [
            'order_date'    => ['order date', 'date', 'order_date'],
            'po_number'     => ['po number / order id', 'po number', 'order id', 'po_number', 'order_id'],
            'channel'       => ['sales channel', 'channel', 'platform'],
            'sku'           => ['style code', 'sku', 'style_code', 'vendor sku', 'style code / sku'],
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
            'warehouse_id_number' => ['warehouse id number', 'warehouse number', 'warehouse id'],
        ];

        foreach ($colAliases as $key => $aliases) {
            foreach ($header as $i => $h) {
                if (in_array($h, $aliases)) {
                    $colMap[$key] = $i;
                    break;
                }
            }
        }

        $missing = [];
        foreach (['order_date', 'po_number', 'sku', 'unit_price', 'qty', 'warehouse_id_number', 'channel'] as $req) {
            if (!isset($colMap[$req])) {
                $missing[] = $req;
            }
        }
        if (!empty($missing)) {
            return ['created' => 0, 'errors' => ['Missing required columns: ' . implode(', ', $missing)], 'total_rows' => 0];
        }

        $get = function ($row, $key) use ($colMap) {
            $val = isset($colMap[$key]) ? trim($row[$colMap[$key]] ?? '') : '';
            return preg_replace('/[\r\n]+/', ' ', $val);
        };

        $parseDate = function ($val) {
            if (empty($val)) {
                return null;
            }
            if (is_numeric($val) && $val > 25000 && $val < 60000) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', '1899-12-30')->addDays(intval($val))->toDateString();
            }
            try {
                return \Carbon\Carbon::parse($val)->toDateString();
            } catch (\Exception $e) {
                return null;
            }
        };

        // ── Step 1: Parse and group rows by PO number ──
        $grouped = [];
        $errors = [];
        $skippedPOs = [];
        $totalRows = count($rows) - 1;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNum = $i + 1;
            $sku = $get($row, 'sku');
            if (empty($sku)) {
                continue;
            }

            $poNumber = $get($row, 'po_number');
            $channel = $get($row, 'channel');
            $warehouseCode = $get($row, 'warehouse_id_number');
            $orderDate = $parseDate($get($row, 'order_date'));
            $unitPrice = $this->cleanNum($get($row, 'unit_price'));
            $qty = $this->cleanNum($get($row, 'qty'));

            // Skip rows belonging to already-failed POs
            if (in_array($poNumber, $skippedPOs)) {
                continue;
            }

            if (empty($orderDate)) {
                $errors[] = "Row {$rowNum}: Order Date is empty.";
                $skippedPOs[] = $poNumber;
                continue;
            }
            if (empty($poNumber)) {
                $errors[] = "Row {$rowNum}: PO Number is empty.";
                continue;
            }
            if (empty($channel)) {
                $errors[] = "Row {$rowNum}: Sales Channel is empty.";
                $skippedPOs[] = $poNumber;
                continue;
            }
            if (empty($warehouseCode)) {
                $errors[] = "Row {$rowNum}: Warehouse Number is empty.";
                $skippedPOs[] = $poNumber;
                continue;
            }
            if (!is_numeric($unitPrice)) {
                $errors[] = "Row {$rowNum}: Invalid unit price '{$unitPrice}'.";
                $skippedPOs[] = $poNumber;
                continue;
            }
            if (!is_numeric($qty) || intval($qty) < 1) {
                $errors[] = "Row {$rowNum}: Invalid quantity '{$qty}'.";
                $skippedPOs[] = $poNumber;
                continue;
            }

            $channelId = \App\Models\SalesChannel::where('name', $channel)->value('id');
            if (!$channelId) {
                $errors[] = "Row {$rowNum}: Sales Channel '{$channel}' not found.";
                $skippedPOs[] = $poNumber;
                continue;
            }


            $product = \App\Models\Product::withoutGlobalScopes()->where('sku', $sku)->first();
            if (!$product) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' not found.";
                $skippedPOs[] = $poNumber;
                continue;
            }
            if (empty($product->sap_code)) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' has no SAP code.";
                $skippedPOs[] = $poNumber;
                continue;
            }

            $warehouseId = \App\Models\Warehouse::where('code', $warehouseCode)->value('id');
            if (!$warehouseId) {
                $errors[] = "Row {$rowNum}: Warehouse '{$warehouseCode}' not found.";
                $skippedPOs[] = $poNumber;
                continue;
            }

            $qty = intval($qty);

            // Group by PO number
            if (!isset($grouped[$poNumber])) {
                $existing = \App\Models\Order::withoutGlobalScopes()
                    ->where('platform_order_id', $poNumber)
                    ->where('company_code', $companyCode)
                    ->first();
                if ($existing) {
                    $errors[] = "Row {$rowNum}: PO '{$poNumber}' already exists (Order #{$existing->order_number}).";
                    $skippedPOs[] = $poNumber;
                    continue;
                }

                $grouped[$poNumber] = [
                    'order_data' => [
                        'platform_order_id' => $poNumber,
                        'order_date'        => $orderDate,
                        'sales_channel'     => $get($row, 'channel'),
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
                    ],
                    'items' => [],
                    'total_amount' => 0,
                ];
            }

            $lineTotal = round(floatval($unitPrice) * $qty, 2);
            $grouped[$poNumber]['items'][] = [
                'product'      => $product,
                'sku'          => $sku,
                'qty'          => $qty,
                'unit_price'   => floatval($unitPrice),
                'line_total'   => $lineTotal,
                'warehouse_id' => $warehouseId,
                'row_num'      => $rowNum,
            ];
            $grouped[$poNumber]['total_amount'] += $lineTotal;
        }

        // ── Step 2: Validate inventory for entire PO before creating ──
        $created = 0;

        foreach ($grouped as $poNumber => $group) {
            $warehouseId = $group['order_data']['warehouse_id'];
            $poValid = true;

            // Check ALL items in this PO have sufficient stock
            foreach ($group['items'] as $item) {
                $availableStock = \App\Models\Inventory::where('product_id', $item['product']->id)
                    ->where('warehouse_id', $item['warehouse_id'])
                    ->where('company_code', $companyCode)
                    ->sum('available_quantity');

                if ($availableStock < $item['qty']) {
                    $errors[] = "PO '{$poNumber}' SKIPPED — SKU '{$item['sku']}' (Row {$item['row_num']}): insufficient inventory at warehouse. Available: {$availableStock}, Ordered: {$item['qty']}.";
                    $poValid = false;
                    break; // Skip entire PO
                }
            }

            if (!$poValid) {
                continue;
            }

            try {
                $group['order_data']['total_amount'] = $group['total_amount'];
                $group['order_data']['subtotal'] = $group['total_amount'];

                $this->createOrder($group['order_data'], $group['items'], $companyCode, $warehouseId, $uploadDate);
                $created++;
            } catch (\Exception $e) {
                $errors[] = "PO '{$poNumber}': Failed — " . $e->getMessage();
            }
        }

        return ['created' => $created, 'errors' => $errors, 'total_rows' => $totalRows];
    }

    // ═══════════════════════════════════════════════════════
    //  MANUAL ENTRY
    // ═══════════════════════════════════════════════════════
    /**
     * Process manually entered orders from the grid form
     */
    public function processManualOrders(array $ordersData, string $companyCode): array
    {
        $created = 0;
        $errors = [];

        foreach ($ordersData as $idx => $orderData) {
            $rowNum = $idx + 1;
            $poNumber = trim($orderData['platform_order_id']);
            $warehouseIdNumber = trim($orderData['warehouse_id_number']);
            $existing = Order::where('platform_order_id', $poNumber)->where('company_code', $companyCode)->first();
            if ($existing) {
                $errors[] = "Row {$rowNum}: PO '{$poNumber}' already exists.";
                continue;
            }

            $warehouseId = \App\Models\Warehouse::where('code', $warehouseIdNumber)
                ->when($companyCode, function ($query, $companyCode) {
                    $query->where('company_code', $companyCode);
                })
                ->value('id');

            if (empty($warehouseId)) {
                $companyNote = $companyCode ? " for company " . $companyCode : '';
                $errors[] = "Row {$rowNum}: Warehouse Number '{$warehouseIdNumber}' does not exist{$companyNote}.";
                continue;
            }

            $orderItems = [];
            $totalAmount = 0;
            $hasError = false;

            foreach ($orderData['items'] as $itemData) {
                $sku = trim($itemData['sku']);
                $qty = intval($itemData['quantity']);
                $unitPrice = floatval($itemData['unit_price']);

                $product = Product::where(['sku' => $sku, 'company_code' => $companyCode])->first();
                if (!$product) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' not found for company '{$companyCode}'.";
                    $hasError = true;
                    break;
                }
                if (empty($product->sap_code)) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' has no SAP code.";
                    $hasError = true;
                    break;
                }

                //   $warehouseId = \App\Models\Warehouse::where('code', $warehouseIdNumber)->value('id');
                $qty = intval($qty);
                $availableStock = \App\Models\Inventory::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->where('company_code', $companyCode)->sum('available_quantity');
                if ($availableStock < $qty) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' insufficient inventory. Available: {$availableStock}, Ordered: {$qty}.";
                    $hasError = true;
                    break;
                }

                // $availableStock = Inventory::where('product_id', $product->id)
                //     ->where('company_code', $companyCode)->sum('available_quantity');
                // if ($availableStock < $qty) {
                //     $errors[] = "Row {$rowNum}: SKU '{$sku}' insufficient inventory. Available: {$availableStock}, Ordered: {$qty}.";
                //     $hasError = true;
                //     break;
                // }

                $lineTotal = round($unitPrice * $qty, 2);
                $totalAmount += $lineTotal;

                $orderItems[] = [
                    'product' => $product,
                    'sku' => $sku,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if ($hasError) {
                continue;
            }

            try {
                $this->createOrder([
                    'platform_order_id' => $poNumber,
                    'warehouse_id_number' => $warehouseIdNumber,
                    'order_date'        => $orderData['order_date'],
                    'total_amount'      => floatval($orderData['total_amount']),
                    'subtotal'          => $totalAmount,
                    'sales_channel'     => $orderData['sales_channel'] ?? '',
                    'currency'          => $orderData['currency'] ?? null,
                    'customer_name'     => $orderData['customer_name'] ?? null,
                    'customer_email'    => $orderData['customer_email'] ?? null,
                    'customer_phone'    => $orderData['customer_phone'] ?? null,
                    'customer_type'     => $orderData['customer_type'] ?? null,
                    'company_name'      => $orderData['company_name'] ?? null,
                    'shipping_method'   => $orderData['shipping_method'] ?? null,
                    'shipping_address'  => $orderData['shipping_address'] ?? null,
                    'shipping_city'     => $orderData['shipping_city'] ?? null,
                    'shipping_state'    => $orderData['shipping_state'] ?? null,
                    'shipping_country'  => $orderData['shipping_country'] ?? null,
                    'shipping_pincode'  => $orderData['shipping_pincode'] ?? null,
                    'payment_status'    => $orderData['payment_status'] ?? 'unpaid',
                    'status' => 'open',
                ], $orderItems, $companyCode, $warehouseId ?? 0);

                $created++;
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum}: Failed — " . $e->getMessage();
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    // ═══════════════════════════════════════════════════════
    //  SHIPPING & TRACKING
    // ═══════════════════════════════════════════════════════

    /**
     * Update tracking for an order
     */
    public function updateTracking(Order $order, string $trackingId, ?string $trackingUrl, ?string $provider): Order
    {
        $order->update([
            'tracking_id'       => $trackingId,
            'tracking_url'      => $trackingUrl,
            'shipping_provider' => $provider,
            'shipment_status'   => 'shipped',
            'shipped_date'      => now(),
        ]);

        ActivityLog::log('updated', 'order_tracking', $order, null, ['tracking_id' => $trackingId]);
        return $order;
    }

    /**
     * Mark order as shipped with full details
     */
    /**
     * Mark order as shipped with per-item shipped quantities
     */
    public function shipOrder(Order $order, array $itemShippedQtys, string $trackingId, ?float $shippingCost, string $carrier): Order
    {
        return DB::transaction(function () use ($order, $itemShippedQtys, $trackingId, $shippingCost, $carrier) {

            // Update each item's shipped_qty and deduct inventory
            $orderShippedQty = 0;
            $orderShippedAmount = 0;

            foreach ($itemShippedQtys as $itemId => $shippedQty) {
                $item = OrderItem::find($itemId);
                if (!$item || $item->order_id !== $order->id) {
                    continue;
                }

                $shippedQty = intval($shippedQty);
                if ($shippedQty <= 0) {
                    continue;
                }

                $shippedAmount = round(floatval($item->unit_price) * $shippedQty, 2);
                $orderShippedQty += $shippedQty;
                $orderShippedAmount += $shippedAmount;

                $item->update([
                    'shipped_qty'    => $shippedQty,
                    'shipped_amount' => $shippedAmount,
                ]);

                // Deduct from reserved inventory per item
                if ($item->product_id) {
                    $inventory = Inventory::where('product_id', $item->product_id)
                        ->where('company_code', $order->company_code)->first();

                    if ($inventory) {
                        $inventory->decrement('quantity', $shippedQty);
                        $inventory->decrement('reserved_quantity', $shippedQty);

                        InventoryLog::record($inventory, InventoryLog::ACTION_ORDER_SHIPPED, -$shippedQty, [
                            'change_available' => 0,
                            'change_reserved'  => -$shippedQty,
                            'reference_type'   => 'order',
                            'reference_id'     => $order->id,
                            'reference_code'   => $order->order_number,
                            'metadata'         => [
                                'item_id'     => $item->id,
                                'sku'         => $item->sku,
                                'tracking_id' => $trackingId,
                                'carrier'     => $carrier,
                            ],
                        ]);
                    }

                    Product::where('id', $item->product_id)->decrement('stock_quantity', $shippedQty);
                }
            }

            // Update order-level summary
            $order->update([
                'shipped_qty'       => $orderShippedQty,
                'shipped_amount'    => $orderShippedAmount,
                'tracking_id'       => $trackingId,
                'shipping_cost'     => $shippingCost,
                'carrier'           => $carrier,
                'shipping_provider' => $carrier,
                'shipment_status'   => 'shipped',
                'status'            => 'shipped',
                'shipped_date'      => now(),
            ]);

            ActivityLog::log('shipped', 'order', $order, null, [
                'tracking_id' => $trackingId,
                'carrier'     => $carrier,
                'items'       => $itemShippedQtys,
            ], "Order {$order->order_number} shipped via {$carrier}");

            return $order;
        });
    }

    /**
     * Update order management fields (status, delivery, costs)
     */
    public function updateOrderManagement(Order $order, array $data): Order
    {
        $updateData = array_filter([
            'ship_date'                => $data['ship_date'] ?? null,
            'status'                   => $data['status'] ?? null,
            'material_cost'            => $data['material_cost'] ?? null,
            'order_processing_charges' => $data['order_processing_charges'] ?? null,
            'remarks'                  => $data['remarks'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // Auto-set delivery date when delivered
        if (($data['status'] ?? '') === 'delivered' && !$order->delivery_date) {
            $updateData['delivery_date'] = $data['delivery_date'] ?? now()->toDateString();
            $updateData['status'] = 'delivered';            
            $updateData['current_status'] = 'delivered';

        } elseif (!empty($data['delivery_date'])) {
            $updateData['delivery_date'] = $data['delivery_date'];    
            $updateData['current_status'] = 'delivered';

        }

        if ($order->current_status === 'returned') {
            $updateData['status'] = 'returned';
        }

        // If ship_date is null/empty → status = open
        // (introduced 27-Sep-2026 – recommendation Mr. Umang)
        $shipDate = array_key_exists('ship_date', $data)
            ? $data['ship_date']
            : $order->ship_date;

        if (empty($shipDate)
            && ($data['status'] ?? null) !== 'delivered'
            && ($data['status'] ?? null) !== 'returned'
        ) {
            $updateData['status'] = 'open';
        }

        //END
        $order->update($updateData);

        ActivityLog::log('updated', 'order', $order, null, $updateData, "Order {$order->order_number} management updated");

        return $order;
    }
}
