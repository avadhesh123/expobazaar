<?php

namespace App\Services;

use App\Models\{Order, OrderItem, Customer, SalesChannel, Product, FinanceReceivable, Inventory, InventoryLog, ActivityLog, User};
use Illuminate\Support\Facades\DB;

class SalesService
{
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
    public function generateOrderNumber(string $companyCode): string
    {
        $count = Order::where('company_code', $companyCode)->count() + 1;
        return 'ORD-' . $companyCode . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
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
    public function createOrder(array $orderData, array $items, string $companyCode): Order
    {
        return DB::transaction(function () use ($orderData, $items, $companyCode) {

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
                'customer_name'     => $orderData['customer_name'] ?? null,
                'customer_email'    => $orderData['customer_email'] ?? null,
                'customer_phone'    => $orderData['customer_phone'] ?? null,
                'customer_type'     => $orderData['customer_type'] ?? null,
                'company_name'      => $orderData['company_name'] ?? null,
                'shipping_address'  => $orderData['shipping_address'] ?? null,
                'shipping_city'     => $orderData['shipping_city'] ?? null,
                'shipping_state'    => $orderData['shipping_state'] ?? null,
                'shipping_country'  => $orderData['shipping_country'] ?? null,
                'shipping_pincode'  => $orderData['shipping_pincode'] ?? null,
                'shipping_method'   => $shipMethod ?? null,
                'warehouse_id'      => $orderData['warehouse_id'] ?? null,
                'payment_status'    => $orderData['payment_status'] ?? 'unpaid',
                'status'            => 'open',
                'uploaded_by'       => auth()->id(),
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
                $this->reserveInventory($product->id, $companyCode, $quantity, $order);
            }

            // Create finance receivable
            $this->createReceivable($order, $channel);

            return $order;
        });
    }

    /**
     * Reserve inventory for an order item
     */
    private function reserveInventory(int $productId, string $companyCode, int $qty, Order $order): void
    {
        $inventory = Inventory::where('product_id', $productId)
            ->where('company_code', $companyCode)
            ->where('available_quantity', '>', 0)
            ->first();

        if ($inventory) {
            $inventory->decrement('available_quantity', $qty);
            $inventory->increment('reserved_quantity', $qty);

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
    public function processUploadedRows(array $rows, string $companyCode): array
    {
        $header = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
        $colMap = [];
        $colAliases = [
            'order_date'    => ['order date', 'date', 'order_date'],
            'po_number'     => ['po number / order id', 'po number', 'order id', 'po_number', 'order_id'],
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

        $parseDate = function ($val) {
            if (empty($val)) return null;
            if (is_numeric($val) && $val > 25000 && $val < 60000) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', '1899-12-30')
                    ->addDays(intval($val))->toDateString();
            }
            try {
                return \Carbon\Carbon::parse($val)->toDateString();
            } catch (\Exception $e) {
                return null;
            }
        };

        // ── Step 1: Group rows by PO number ──
        $grouped = [];
        $errors = [];
        $totalRows = count($rows) - 1;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNum = $i + 1;

            $sku = $get($row, 'sku');
            if (empty($sku)) continue;

            $poNumber = $get($row, 'po_number');
            $orderDate = $parseDate($get($row, 'order_date'));
            $unitPrice = $get($row, 'unit_price');
            $qty = $get($row, 'qty');

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

            $product = \App\Models\Product::where('sku', $sku)->first();
            if (!$product) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' not found.";
                continue;
            }
            if (empty($product->sap_code)) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' has no SAP code.";
                continue;
            }

            $qty = intval($qty);
            $availableStock = \App\Models\Inventory::where('product_id', $product->id)
                ->where('company_code', $companyCode)->sum('available_quantity');
            if ($availableStock < $qty) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' insufficient inventory. Available: {$availableStock}, Ordered: {$qty}.";
                continue;
            }

            // Group by PO number
            if (!isset($grouped[$poNumber])) {
                // Check duplicate PO in database
                $existing = \App\Models\Order::where('platform_order_id', $poNumber)
                    ->where('company_code', $companyCode)->first();
                if ($existing) {
                    $errors[] = "Row {$rowNum}: PO '{$poNumber}' already exists (Order #{$existing->order_number}).";
                    continue;
                }

                $warehouseId = \App\Models\Inventory::where('product_id', $product->id)->value('warehouse_id');

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
                'product'    => $product,
                'sku'        => $sku,
                'qty'        => $qty,
                'unit_price' => floatval($unitPrice),
                'line_total' => $lineTotal,
            ];
            $grouped[$poNumber]['total_amount'] += $lineTotal;
        }

        // ── Step 2: Create one order per PO with all its items ──
        $created = 0;

        foreach ($grouped as $poNumber => $group) {
            try {
                $group['order_data']['total_amount'] = $group['total_amount'];
                $group['order_data']['subtotal'] = $group['total_amount'];

                $this->createOrder($group['order_data'], $group['items'], $companyCode);
                $created++;
            } catch (\Exception $e) {
                $errors[] = "PO '{$poNumber}': Failed — " . $e->getMessage();
            }
        }

        return ['created' => $created, 'errors' => $errors, 'total_rows' => $totalRows];
    }
    public function processUploadedRows1(array $rows, string $companyCode): array
    {
        $header = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
        $colMap = [];
        $colAliases = [
            'order_date'    => ['order date', 'date', 'order_date'],
            'po_number'     => ['po number / order id', 'po number', 'order id', 'po_number', 'order_id'],
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

        $parseDate = function ($val) {
            if (empty($val)) return null;
            if (is_numeric($val) && $val > 25000 && $val < 60000) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', '1899-12-30')
                    ->addDays(intval($val))->toDateString();
            }
            try {
                return \Carbon\Carbon::parse($val)->toDateString();
            } catch (\Exception $e) {
                return null;
            }
        };

        $created = 0;
        $errors = [];
        $totalRows = count($rows) - 1;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNum = $i + 1;

            $sku = $get($row, 'sku');
            if (empty($sku)) continue;

            $orderDate = $parseDate($get($row, 'order_date'));
            $poNumber = $get($row, 'po_number');
            $unitPrice = $get($row, 'unit_price');
            $qty = $get($row, 'qty');

            // Validations
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

            $product = Product::where('sku', $sku)->first();
            if (!$product) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' not found.";
                continue;
            }
            if (empty($product->sap_code)) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' has no SAP code.";
                continue;
            }

            $availableStock = Inventory::where('product_id', $product->id)
                ->where('company_code', $companyCode)->sum('available_quantity');
            if ($availableStock < intval($qty)) {
                $errors[] = "Row {$rowNum}: SKU '{$sku}' insufficient inventory. Available: {$availableStock}, Ordered: {$qty}.";
                continue;
            }

            $existing = Order::where('platform_order_id', $poNumber)->where('company_code', $companyCode)->first();
            if ($existing) {
                $errors[] = "Row {$rowNum}: PO '{$poNumber}' already exists.";
                continue;
            }

            $unitPrice = floatval($unitPrice);
            $qty = intval($qty);
            $orderAmount = round($unitPrice * $qty, 2);

            $warehouseId = Inventory::where('product_id', $product->id)->value('warehouse_id');

            try {
                $this->createOrder([
                    'platform_order_id' => $poNumber,
                    'order_date'        => $orderDate,
                    'total_amount'      => $orderAmount,
                    'subtotal'          => $orderAmount,
                    'sales_channel'     => $get($row, 'channel') ?: null,
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
                ], [
                    ['product' => $product, 'sku' => $sku, 'qty' => $qty, 'unit_price' => $unitPrice, 'line_total' => $orderAmount],
                ], $companyCode);

                $created++;
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNum}: Failed — " . $e->getMessage();
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

            $existing = Order::where('platform_order_id', $poNumber)->where('company_code', $companyCode)->first();
            if ($existing) { $errors[] = "Row {$rowNum}: PO '{$poNumber}' already exists."; continue; }

            $orderItems = [];
            $totalAmount = 0;
            $hasError = false;

            foreach ($orderData['items'] as $itemData) {
                $sku = trim($itemData['sku']);
                $qty = intval($itemData['quantity']);
                $unitPrice = floatval($itemData['unit_price']);

                $product = Product::where('sku', $sku)->first();
                if (!$product) { $errors[] = "Row {$rowNum}: SKU '{$sku}' not found."; $hasError = true; break; }
                if (empty($product->sap_code)) { $errors[] = "Row {$rowNum}: SKU '{$sku}' has no SAP code."; $hasError = true; break; }

                $availableStock = Inventory::where('product_id', $product->id)
                    ->where('company_code', $companyCode)->sum('available_quantity');
                if ($availableStock < $qty) {
                    $errors[] = "Row {$rowNum}: SKU '{$sku}' insufficient inventory. Available: {$availableStock}, Ordered: {$qty}.";
                    $hasError = true; break;
                }

                $lineTotal = round($unitPrice * $qty, 2);
                $totalAmount += $lineTotal;

                $orderItems[] = [
                    'product' => $product, 'sku' => $sku, 'qty' => $qty,
                    'unit_price' => $unitPrice, 'line_total' => $lineTotal,
                ];
            }

            if ($hasError) continue;

            try {
                $this->createOrder([
                    'platform_order_id' => $poNumber,
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
                ], $orderItems, $companyCode);

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
                if (!$item || $item->order_id !== $order->id) continue;

                $shippedQty = intval($shippedQty);
                if ($shippedQty <= 0) continue;

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
    public function shipOrder1(Order $order, int $shippedQty, string $trackingId, ?float $shippingCost, string $carrier): Order
    {
        $firstItem = $order->items->first();
        $unitPrice = $firstItem ? floatval($firstItem->unit_price) : 0;
        $shippedAmount = round($unitPrice * $shippedQty, 2);

        $order->update([
            'shipped_qty'       => $shippedQty,
            'shipped_amount'    => $shippedAmount,
            'tracking_id'       => $trackingId,
            'shipping_cost'     => $shippingCost,
            'carrier'           => $carrier,
            'shipping_provider' => $carrier,
            'shipment_status'   => 'shipped',
            'status'            => 'shipped',
            'shipped_date'      => now(),
        ]);

        // Deduct from reserved inventory
        if ($firstItem && $firstItem->product_id) {
            $inventory = Inventory::where('product_id', $firstItem->product_id)
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
                    'metadata'         => ['tracking_id' => $trackingId, 'carrier' => $carrier],
                ]);
            }

            Product::where('id', $firstItem->product_id)->decrement('stock_quantity', $shippedQty);
        }

        ActivityLog::log('shipped', 'order', $order, null, [
            'tracking_id' => $trackingId,
            'carrier' => $carrier,
            'shipped_qty' => $shippedQty,
        ], "Order {$order->order_number} shipped via {$carrier}");

        return $order;
    }

    /**
     * Update order management fields (status, delivery, costs)
     */
    public function updateOrderManagement(Order $order, array $data): Order
    {
        $updateData = array_filter([
            'ship_date'                => $data['ship_date'] ?? null,
            'current_status'           => $data['current_status'] ?? null,
            'material_cost'            => $data['material_cost'] ?? null,
            'order_processing_charges' => $data['order_processing_charges'] ?? null,
            'remarks'                  => $data['remarks'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        // Auto-set delivery date when delivered
        if (($data['current_status'] ?? '') === 'delivered' && !$order->delivery_date) {
            $updateData['delivery_date'] = $data['delivery_date'] ?? now()->toDateString();
            $updateData['status'] = 'delivered';
        } elseif (!empty($data['delivery_date'])) {
            $updateData['delivery_date'] = $data['delivery_date'];
        }

        if (($data['current_status'] ?? '') === 'returned') {
            $updateData['status'] = 'returned';
        }

        $order->update($updateData);

        ActivityLog::log('updated', 'order', $order, null, $updateData, "Order {$order->order_number} management updated");

        return $order;
    }
}
