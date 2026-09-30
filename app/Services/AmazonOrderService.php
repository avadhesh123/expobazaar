<?php

namespace App\Services;

use SellingPartnerApi\SellingPartnerApi;
use SellingPartnerApi\Enums\Endpoint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Inventory;
use Carbon\Carbon;

class AmazonOrderService
{
    private $api;
    private string $marketplaceId;
    private string $companyCode;

    public function __construct(string $companyCode = '2100')
    {
        $this->companyCode = $companyCode;
        $this->marketplaceId = config('services.amazon.marketplace_id', 'ATVPDKIKX0DER');

        $this->api = SellingPartnerApi::seller(
            clientId: config('services.amazon.client_id'),
            clientSecret: config('services.amazon.client_secret'),
            refreshToken: config('services.amazon.refresh_token'),
            endpoint: Endpoint::NA,
        );
    }

    public function testConnection(): array
    {
        try {
            $response = $this->api->ordersV0()->getOrders(
                marketplaceIds: [$this->marketplaceId],
                createdAfter: now()->subDays(1)->toISOString(),
                maxResultsPerPage: 1,
            );

            $data = $response->json();
            return [
                'success' => true,
                'message' => 'Connected. Orders found: ' . count($data['payload']['Orders'] ?? []),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function pullOrders(string $dateFrom = null, string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? now()->subDays(7)->toISOString();
        $dateTo = $dateTo ?? now()->toISOString();

        $created = 0;
        $skipped = 0;
        $errors = [];
        $nextToken = null;

        do {
            try {
                $params = [
                    'marketplaceIds' => [$this->marketplaceId],
                    'maxResultsPerPage' => 50,
                ];

                if ($nextToken) {
                    $params['nextToken'] = $nextToken;
                } else {
                    $params['createdAfter'] = $dateFrom;
                    $params['createdBefore'] = $dateTo;
                    $params['orderStatuses'] = ['Unshipped', 'PartiallyShipped', 'Shipped'];
                }

                $response = $this->api->ordersV0()->getOrders(...$params);
                $data = $response->json();

                $orders = $data['payload']['Orders'] ?? [];
                print_r(  $orders);exit;
                $nextToken = $data['payload']['NextToken'] ?? null;

                foreach ($orders as $amzOrder) {
                    try {
                        $result = $this->processOrder($amzOrder);
                        if ($result === 'created') {
                            $created++;
                        } else {
                            $skipped++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = ($amzOrder['AmazonOrderId'] ?? '?') . ': ' . $e->getMessage();
                    }
                }

                if ($nextToken) {
                    sleep(6);
                }

            } catch (\Exception $e) {
                $errors[] = 'API Error: ' . $e->getMessage();
                break;
            }
        } while ($nextToken);

        \App\Models\ActivityLog::log('amazon_pull', 'order', auth()->user(), null, [
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => count($errors),
            'date_range' => "{$dateFrom} to {$dateTo}",
        ], "Amazon order pull: {$created} created, {$skipped} skipped — by " . (auth()->user()->name ?? 'system'));

        return compact('created', 'skipped', 'errors');
    }

    private function processOrder(array $amzOrder): string
    {
        $amazonOrderId = $amzOrder['AmazonOrderId'];

        $existing = Order::withoutGlobalScopes()
            ->where('platform_order_id', $amazonOrderId)
            ->where('company_code', $this->companyCode)
            ->first();

        if ($existing) {
            return 'skipped';
        }

        // Get order items — throttle
        sleep(2);
        $itemsResponse = $this->api->ordersV0()->getOrderItems(orderId: $amazonOrderId);
        $amzItems = $itemsResponse->json()['payload']['OrderItems'] ?? [];

        if (empty($amzItems)) {
            return 'skipped';
        }

        $salesChannel = \App\Models\SalesChannel::where('name', 'like', '%Amazon%')
            ->where('company_code', $this->companyCode)
            ->first();

        $statusMap = [
            'Pending'          => 'pending',
            'Unshipped'        => 'open',
            'PartiallyShipped' => 'partially_shipped',
            'Shipped'          => 'shipped',
            'Canceled'         => 'cancelled',
            'Delivered'        => 'delivered',
        ];

        $address = $amzOrder['ShippingAddress'] ?? [];
        $orderNumber = (new \App\Services\SalesService())->generateOrderNumber($this->companyCode);

        \DB::transaction(function () use (
            $amzOrder,
            $amzItems,
            $amazonOrderId,
            $orderNumber,
            $salesChannel,
            $statusMap,
            $address
        ) {
            $order = Order::create([
                'order_number'      => $orderNumber,
                'platform_order_id' => $amazonOrderId,
                'sales_channel_id'  => $salesChannel->id ?? null,
                'company_code'      => $this->companyCode,
                'order_date'        => Carbon::parse($amzOrder['PurchaseDate']),
                'status'            => $statusMap[$amzOrder['OrderStatus']] ?? 'open',
                'currency'          => $amzOrder['OrderTotal']['CurrencyCode'] ?? 'USD',
                'total_amount'      => floatval($amzOrder['OrderTotal']['Amount'] ?? 0),
                'subtotal'          => floatval($amzOrder['OrderTotal']['Amount'] ?? 0),
                'shipping_amount'   => 0,
                'tax_amount'        => 0,
                'discount_amount'   => 0,
                'customer_name'     => $this->sanitize($address['Name'] ?? '—'),
                'customer_email'    => $amzOrder['BuyerInfo']['BuyerEmail'] ?? null,
                'customer_phone'    => $address['Phone'] ?? null,
                'customer_type'     => ($amzOrder['OrderType'] ?? '') === 'StandardOrder' ? 'b2c' : 'b2b',
                'shipping_address'  => $this->sanitize(implode(', ', array_filter([
                    $address['AddressLine1'] ?? '',
                    $address['AddressLine2'] ?? '',
                    $address['AddressLine3'] ?? '',
                ]))),
                'shipping_city'     => $this->sanitize($address['City'] ?? null),
                'shipping_state'    => $address['StateOrRegion'] ?? null,
                'shipping_country'  => $address['CountryCode'] ?? null,
                'shipping_pincode'  => $address['PostalCode'] ?? null,
                'shipping_method'   => $amzOrder['ShipmentServiceLevelCategory'] ?? 'standard',
                'payment_status'    => !empty($amzOrder['PaymentMethod']) ? 'paid' : 'unpaid',
                'uploaded_by'       => auth()->id() ?? 1,
            ]);

            foreach ($amzItems as $amzItem) {
                $sku = $amzItem['SellerSKU'] ?? '';
                $qty = intval($amzItem['QuantityOrdered'] ?? 0);
                $price = floatval($amzItem['ItemPrice']['Amount'] ?? 0);
                $unitPrice = $qty > 0 ? round($price / $qty, 2) : 0;

                $product = Product::withoutGlobalScopes()
                    ->where('sku', $sku)
                    ->where('company_code', $this->companyCode)
                    ->first();

                OrderItem::create([
                    'order_id'    => $order->id,
                    'product_id'  => $product->id ?? null,
                    'sku'         => $sku,
                    'name'        => $this->sanitize($amzItem['Title'] ?? ''),
                    'quantity'    => $qty,
                    'shipped_qty' => intval($amzItem['QuantityShipped'] ?? 0),
                    'unit_price'  => $unitPrice,
                    'total_price' => $price,
                    'vendor_id'   => $product->vendor_id ?? null,
                ]);

                if (($amzItem['QuantityShipped'] ?? 0) > 0 && $product) {
                    $inventory = Inventory::where('product_id', $product->id)
                        ->where('company_code', $this->companyCode)
                        ->first();
                    if ($inventory) {
                        $shippedQty = intval($amzItem['QuantityShipped']);
                        $inventory->decrement('quantity', $shippedQty);
                        $inventory->decrement('available_quantity', $shippedQty);
                    }
                }
            }
        });

        return 'created';
    }

    /**
     * Sanitize string — handle Windows-1252 special chars
     */
    private function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        return $value;
    }
}
