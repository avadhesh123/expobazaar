<?php

namespace App\Services;

use App\Models\{Order, OrderItem, Customer, SalesChannel, Product, FinanceReceivable, Chargeback, VendorPayout, WarehouseCharge, Vendor, Inventory, ActivityLog, User};
use App\Notifications\{OrderNotification, ChargebackNotification, PayoutNotification};
use Illuminate\Support\Facades\{DB, Notification};

class SalesService
{
    /**
     * Upload sales data from template
     */
    public function uploadSalesData(array $ordersData, string $companyCode, User $uploader): array
    {
        $created = [];
        $errors = [];

        foreach ($ordersData as $index => $data) {
            try {
                // Validate sales channel exists
                $channel = SalesChannel::where('slug', $data['sales_channel'] ?? '')->orWhere('name', $data['sales_channel'] ?? '')->first();
                if (!$channel) {
                    $errors[] = "Row {$index}: Sales channel '{$data['sales_channel']}' not found in Sales Channel Master";
                    continue;
                }

                DB::transaction(function () use ($data, $companyCode, $uploader, $channel, &$created) {
                    // Auto-create customer if not exists
                    $customer = null;
                    if (!empty($data['customer_name'])) {
                        $customer = Customer::firstOrCreate(
                            ['email' => $data['customer_email'] ?? null, 'sales_channel_id' => $channel->id],
                            [
                                'customer_code' => Customer::generateCode(),
                                'name' => $data['customer_name'],
                                'phone' => $data['customer_phone'] ?? null,
                                'address' => $data['shipping_address'] ?? null,
                                'city' => $data['shipping_city'] ?? null,
                                'state' => $data['shipping_state'] ?? null,
                                'country' => $data['shipping_country'] ?? null,
                                'pincode' => $data['shipping_pincode'] ?? null,
                                'company_code' => $companyCode,
                            ]
                        );
                    }

                    $order = Order::create([
                        'order_number' => Order::generateOrderNumber($companyCode),
                        'platform_order_id' => $data['platform_order_id'] ?? null,
                        'sales_channel_id' => $channel->id,
                        'customer_id' => $customer?->id,
                        'company_code' => $companyCode,
                        'order_date' => $data['order_date'] ?? now(),
                        'subtotal' => $data['subtotal'] ?? 0,
                        'shipping_amount' => $data['shipping'] ?? 0,
                        'tax_amount' => $data['tax'] ?? 0,
                        'discount_amount' => $data['discount'] ?? 0,
                        'total_amount' => $data['total_amount'] ?? 0,
                        'currency' => $data['currency'] ?? 'USD',
                        'customer_name' => $data['customer_name'] ?? null,
                        'customer_email' => $data['customer_email'] ?? null,
                        'shipping_address' => $data['shipping_address'] ?? null,
                        'shipping_city' => $data['shipping_city'] ?? null,
                        'shipping_state' => $data['shipping_state'] ?? null,
                        'shipping_country' => $data['shipping_country'] ?? null,
                        'shipping_pincode' => $data['shipping_pincode'] ?? null,
                        'status' => 'confirmed',
                        'uploaded_by' => $uploader->id,
                    ]);

                    // Create order items
                    if (!empty($data['items'])) {
                        foreach ($data['items'] as $itemData) {
                            $product = Product::where('sku', $itemData['sku'])->first();
                            if ($product) {
                                OrderItem::create([
                                    'order_id' => $order->id,
                                    'product_id' => $product->id,
                                    'vendor_id' => $product->vendor_id,
                                    'quantity' => $itemData['quantity'] ?? 1,
                                    'unit_price' => $itemData['unit_price'] ?? 0,
                                    'total_price' => ($itemData['unit_price'] ?? 0) * ($itemData['quantity'] ?? 1),
                                    'sku' => $itemData['sku'],
                                ]);

                                // Reserve inventory
                                Inventory::where('product_id', $product->id)
                                    ->where('available_quantity', '>', 0)
                                    ->first()
                                    ?->decrement('available_quantity', $itemData['quantity'] ?? 1);
                            }
                        }
                    }

                    // Create finance receivable
                    FinanceReceivable::create([
                        'order_id' => $order->id,
                        'sales_channel_id' => $channel->id,
                        'company_code' => $companyCode,
                        'order_amount' => $order->total_amount,
                        'net_receivable' => $order->total_amount,
                    ]);

                    $created[] = $order;
                });
            } catch (\Exception $e) {
                $errors[] = "Row {$index}: " . $e->getMessage();
            }
        }

        // Notify finance team
        if (count($created) > 0) {
            $finance = User::internal()->byDepartment('finance')->active()->get();
            Notification::send($finance, new OrderNotification(count($created), 'new_sales_data'));
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * Update tracking for order
     */
    public function updateTracking(Order $order, string $trackingId, ?string $trackingUrl, ?string $provider): Order
    {
        $order->update([
            'tracking_id' => $trackingId,
            'tracking_url' => $trackingUrl,
            'shipping_provider' => $provider,
            'shipment_status' => 'shipped',
            'shipped_date' => now(),
        ]);

        ActivityLog::log('updated', 'order_tracking', $order, null, ['tracking_id' => $trackingId]);
        return $order;
    }
}
