<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\{Order, SalesChannel};
use App\Services\{SalesService, DashboardService};
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function __construct(
        private SalesService $salesService,
        private DashboardService $dashboardService
    ) {}

    // ═══ DASHBOARD ═══
    public function dashboard(Request $request)
    {
        $data = $this->dashboardService->getSalesDashboard(
            session('active_company'),
            $request->date_from,
            $request->date_to
        );
        return view('sales.dashboard', compact('data'));
    }

    // ═══ ORDERS LIST ═══

    public function orders(Request $request)
    {
        $activeCompany = session('active_company');

        $baseQuery = Order::when($activeCompany, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->sales_channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($q2) use ($v) {
                $q2->where('order_number', 'like', "%{$v}%")
                    ->orWhere('platform_order_id', 'like', "%{$v}%")
                    ->orWhere('customer_name', 'like', "%{$v}%");
            }));

        $orders = (clone $baseQuery)->with('salesChannel', 'items.product')
            ->latest('order_date')->paginate(30)->withQueryString();

        $stats = [
            'total_orders'   => (clone $baseQuery)->count(),
            'total_revenue'  => (clone $baseQuery)->sum('total_amount'),
            'pending_orders' => (clone $baseQuery)->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'pending');
            })->count(),
            'today_orders'   => (clone $baseQuery)->whereDate('order_date', today())->count(),
            'today_revenue'  => (clone $baseQuery)->whereDate('order_date', today())->sum('total_amount'),
        ];


        $channels = SalesChannel::active()->get();
        return view('sales.orders', compact('orders', 'channels', 'stats'));
    }

    public function showOrder(Order $order)
    {
        $order->load('items.product', 'salesChannel');
        return view('sales.show', compact('order'));
    }

    public function downloadOrders(Request $request)
    {
        $activeCompany = session('active_company');
        $orders = Order::with('salesChannel', 'items.product.vendor', 'warehouse')
            ->when($activeCompany, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->sales_channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($q2) use ($v) {
                $q2->where('order_number', 'like', "%{$v}%")
                    ->orWhere('platform_order_id', 'like', "%{$v}%")
                    ->orWhere('customer_name', 'like', "%{$v}%");
            }))
            ->latest('order_date')
            ->get();

        $csv = "Order Number,PO Number,Invoice Number,Order Date,Sales Channel,SKU,SAP Code,Product Name,Vendor Name,Vendor Type,Qty,Unit Price,Order Amount,Vendor Payout Price,Payout Total,Warehouse,Shipping Method,Shipped Qty,Shipped Amount,Tracking ID,Carrier,Shipping Cost,Ship Date,Current Status,Delivery Date,Customer Type,Customer Name,Company Name,Email,Phone,Address,City,State,Zip,Country,Currency,Status\n";

        foreach ($orders as $o) {
            $firstItem = $o->items->first();
            $product = $firstItem?->product;
            $vendor = $product?->vendor;

            $shipMethods = ['1' => 'Store Pickup', '2' => 'Marketplace Label', '3' => 'Seller Label'];
            $qty = $firstItem?->quantity ?? 0;
            $payoutPrice = $product?->vendor_payout_price ?? 0;

            $csv .= implode(',', [
                '"' . ($o->order_number ?? '') . '"',
                '"' . ($o->platform_order_id ?? '') . '"',
                '"' . ($o->invoice_number ?? '') . '"',
                $o->order_date?->format('Y-m-d') ?? '',
                '"' . ($o->salesChannel?->name ?? '') . '"',
                '"' . ($firstItem?->sku ?? $product?->sku ?? '') . '"',
                '"' . ($product?->sap_code ?? '') . '"',
                '"' . str_replace('"', '""', $product?->name ?? '') . '"',
                '"' . str_replace('"', '""', $vendor?->company_name ?? '') . '"',
                '"' . ($vendor?->vendor_type ?? '') . '"',
                $qty,
                number_format(floatval($firstItem?->unit_price ?? 0), 2, '.', ''),
                number_format(floatval($o->total_amount ?? 0), 2, '.', ''),
                number_format(floatval($payoutPrice), 2, '.', ''),
                number_format($payoutPrice * $qty, 2, '.', ''),
                '"' . ($o->warehouse?->name ?? '') . '"',
                '"' . (strtoupper($o->shipping_method ?? '')) . '"',
                $o->shipped_qty ?? '',
                number_format(floatval($o->shipped_amount ?? 0), 2, '.', ''),
                '"' . ($o->tracking_id ?? '') . '"',
                '"' . ($o->carrier ?? '') . '"',
                number_format(floatval($o->shipping_cost ?? 0), 2, '.', ''),
                $o->ship_date?->format('Y-m-d') ?? '',
                '"' . ($o->current_status ?? '') . '"',
                $o->delivery_date?->format('Y-m-d') ?? '',
                '"' . ($o->customer_type ?? '') . '"',
                '"' . str_replace('"', '""', $o->customer_name ?? '') . '"',
                '"' . str_replace('"', '""', $o->company_name ?? '') . '"',
                '"' . ($o->customer_email ?? '') . '"',
                '"' . ($o->customer_phone ?? '') . '"',
                '"' . str_replace('"', '""', $o->shipping_address ?? '') . '"',
                '"' . ($o->shipping_city ?? '') . '"',
                '"' . ($o->shipping_state ?? '') . '"',
                '"' . ($o->shipping_pincode ?? '') . '"',
                '"' . ($o->shipping_country ?? '') . '"',
                $o->currency ?? 'USD',
                '"' . ($o->status ?? '') . '"',
            ]) . "\n";
        }

        $filename = 'Sales-Orders-' . now()->format('Y-m-d') . '.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ═══ UPLOAD (FILE) ═══

    public function uploadSales(Request $request)
    {
        // AJAX SKU check
        if ($request->has('check_sku')) {
            $sku = trim($request->check_sku);
            $product = \App\Models\Product::where('sku', $sku)->first();
            if ($product) {
                $stock = \App\Models\Inventory::where('product_id', $product->id)->sum('available_quantity');
                return response()->json([
                    'found'    => true,
                    'name'     => $product->name,
                    'sap_code' => $product->sap_code ?? '',
                    'stock'    => intval($stock),
                    'price'    => floatval($product->fob_price ?? $product->vendor_price ?? 0),
                ]);
            }
            return response()->json(['found' => false]);
        }

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
            'company_code' => 'required|in:2000,2100,2200,2400',
            'sales_file'   => 'required|file|max:10240',
        ]);

        $file = $request->file('sales_file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            return back()->with('error', 'File must be CSV or XLSX format.');
        }

        try {
            $fullPath = $file->getRealPath();

            if (in_array($ext, ['xlsx', 'xls'])) {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(false);
                $spreadsheet = $reader->load($fullPath);
                $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            } else {
                $rows = [];
                if (($handle = fopen($fullPath, 'r')) !== false) {
                    while (($row = fgetcsv($handle)) !== false) {
                        $rows[] = $row;
                    }
                    fclose($handle);
                }
            }

            if (count($rows) < 2) {
                return back()->with('error', 'File is empty or has no data rows.');
            }

            $result = $this->salesService->processUploadedRows($rows, $request->company_code);

            \App\Models\ActivityLog::log('uploaded', 'sales_data', auth()->user(), null, [
                'company_code' => $request->company_code,
                'created' => $result['created'],
                'errors' => count($result['errors']),
            ], "Sales data uploaded: {$result['created']} orders created");

            return back()->with('upload_result', $result)->with(
                $result['created'] > 0 ? 'success' : 'error',
                "{$result['created']} order(s) created from {$result['total_rows']} rows." .
                    (count($result['errors']) > 0 ? ' ' . implode(", ", $result['errors'])   : '')
            );
        } catch (\Exception $e) {
            \Log::error('Sales upload failed: ' . $e->getMessage());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    // ═══ MANUAL ENTRY ═══

    public function storeManualOrders(Request $request)
    {
        $request->validate([
            'company_code'                      => 'required|in:2000,2100,2200,2400',
            'orders'                            => 'required|array|min:1',
            'orders.*.platform_order_id'        => 'required|string',
            'orders.*.order_date'               => 'required|date',
            'orders.*.total_amount'             => 'required|numeric|min:0.01',
            'orders.*.shipping_method'          => 'nullable|in:SP,MPL,EBL',
            'orders.*.payment_status'           => 'nullable|in:unpaid,paid,partial,refunded',
            'orders.*.customer_name'            => 'nullable|string|max:200',
            'orders.*.customer_email'           => 'nullable|email|max:200',
            'orders.*.customer_phone'           => 'nullable|string|max:50',
            'orders.*.customer_type'            => 'nullable|string|max:50',
            'orders.*.company_name'             => 'nullable|string|max:200',
            'orders.*.shipping_address'         => 'nullable|string|max:500',
            'orders.*.shipping_city'            => 'nullable|string|max:100',
            'orders.*.shipping_state'           => 'nullable|string|max:100',
            'orders.*.shipping_country'         => 'nullable|string|max:100',
            'orders.*.shipping_pincode'         => 'nullable|string|max:20',
            'orders.*.items'                    => 'required|array|min:1',
            'orders.*.items.*.sku'              => 'required|string',
            'orders.*.items.*.quantity'          => 'required|integer|min:1',
            'orders.*.items.*.unit_price'        => 'required|numeric|min:0',
        ]);

        $result = $this->salesService->processManualOrders($request->orders, $request->company_code);

        \App\Models\ActivityLog::log('created', 'order', auth()->user(), null, [
            'company_code' => $request->company_code,
            'created' => $result['created'],
            'errors' => count($result['errors']),
        ], "Manual sales entry: {$result['created']} orders created");

        $msg = "{$result['created']} order(s) created.";
        if (!empty($result['errors'])) {
            $msg .= ' ' . count($result['errors']) . ' error(s): ' . implode('; ', array_slice($result['errors'], 0, 5));
        }

        return redirect()->route('sales.orders')->with($result['created'] > 0 ? 'success' : 'error', $msg);
    }

    public function storeManualOrders1(Request $request)
    {
        $request->validate([
            'company_code'                      => 'required|in:2000,2100,,2400',
            'orders'                            => 'required|array|min:1',
            'orders.*.platform_order_id'        => 'required|string',
            'orders.*.order_date'               => 'required|date',
            'orders.*.total_amount'             => 'required|numeric|min:0.01',
            'orders.*.items'                    => 'required|array|min:1',
            'orders.*.items.*.sku'              => 'required|string',
            'orders.*.items.*.quantity'          => 'required|integer|min:1',
            'orders.*.items.*.unit_price'        => 'required|numeric|min:0',
        ]);

        $result = $this->salesService->processManualOrders($request->orders, $request->company_code);

        \App\Models\ActivityLog::log('created', 'order', auth()->user(), null, [
            'company_code' => $request->company_code,
            'created' => $result['created'],
            'errors' => count($result['errors']),
        ], "Manual sales entry: {$result['created']} orders created");

        $msg = "{$result['created']} order(s) created.";
        if (!empty($result['errors'])) {
            $msg .= ' ' . count($result['errors']) . ' error(s): ' . implode('; ', array_slice($result['errors'], 0, 5));
        }
        if ($result['created'] > 0) {
            return redirect()->route('sales.orders')->with($result['created'] > 0 ? 'success' : 'error', $msg);
        }
        return back()->with($result['created'] > 0 ? 'success' : 'error', $msg);

        //return redirect('/sales/orders')->with($result['created'] > 0 ? 'success' : 'error', $msg);
    }

    // ═══ TO BE SHIPPED ═══

    public function toBeShipped(Request $request)
    {

        $activeCompany = session('active_company');

        $orders = Order::with('items.product', 'salesChannel', 'warehouse')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->whereNull('shipping_method')->orWhere('shipping_method', '!=', 'sp');
            })
            ->where(function ($q) {
                $q->whereNull('tracking_id')->orWhere('tracking_id', '');
            })
            ->where('company_code', $activeCompany)
            ->when($request->channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->latest('order_date')
            ->paginate(30)->withQueryString();

        $orders->getCollection()->transform(function ($order) {
            $orderDate = $order->order_date ? \Carbon\Carbon::parse($order->order_date) : now();
            $order->ageing_days = $orderDate->diffInDays(now());
            $order->is_overdue = $order->ageing_days > 2;
            return $order;
        });

        $channels = SalesChannel::active()->get();
        $stats = [
            'total_pending' => $orders->total(),
            'overdue'       => $orders->getCollection()->where('is_overdue', true)->count(),
            'total_value'   => $orders->getCollection()->sum('total_amount'),
        ];

        return view('sales.to-be-shipped', compact('orders', 'channels', 'stats'));
    }
    public function updateShipping(Request $request, Order $order)
    {
        $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.shipped_qty'    => 'required|integer|min:0',
            'tracking_id'            => 'required|string|max:100',
            'shipping_cost'          => 'nullable|numeric|min:0',
            'carrier'                => 'required|in:Fedex,UPS,USPS,LTL,Other',
        ]);

        $itemQtys = collect($request->items)->mapWithKeys(fn($data, $itemId) => [$itemId => $data['shipped_qty']])->toArray();

        $this->salesService->shipOrder(
            $order,
            $itemQtys,
            $request->tracking_id,
            $request->shipping_cost ? floatval($request->shipping_cost) : null,
            $request->carrier
        );

        return back()->with('success', "Order {$order->order_number} marked as shipped. Tracking: {$request->tracking_id}");
    }
    public function updateShipping1(Request $request, Order $order)
    {
        $request->validate([
            'shipped_qty'   => 'required|integer|min:1',
            'tracking_id'   => 'required|string|max:100',
            'shipping_cost' => 'nullable|numeric|min:0',
            'carrier'       => 'required|in:Fedex,UPS,USPS,LTL,Other',
        ]);

        $this->salesService->shipOrder(
            $order,
            intval($request->shipped_qty),
            $request->tracking_id,
            $request->shipping_cost ? floatval($request->shipping_cost) : null,
            $request->carrier
        );

        return back()->with('success', "Order {$order->order_number} marked as shipped. Tracking: {$request->tracking_id}");
    }

    // ═══ ORDER MANAGEMENT ═══

    public function orderManagement(Request $request)
    {
        $activeCompany = session('active_company');
        $orders = Order::with('items.product', 'salesChannel', 'warehouse')
            ->where(function ($q) {
                $q->whereNotNull('tracking_id')->where('tracking_id', '!=', '');
            })
            ->where('company_code', $activeCompany)
            ->when($request->channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('current_status', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($q2) use ($v) {
                $q2->where('platform_order_id', 'like', "%{$v}%")
                    ->orWhere('tracking_id', 'like', "%{$v}%")
                    ->orWhere('invoice_number', 'like', "%{$v}%");
            }))
            ->latest('ship_date')->latest('shipped_date')
            ->paginate(30)->withQueryString();

        $orders->getCollection()->transform(function ($order) {
            $shipDate = $order->ship_date ?? $order->shipped_date;
            $order->ageing_days = 0;
            $order->ageing_label = '';
            $order->ageing_color = '#16a34a';

            if ($shipDate && $order->current_status !== 'delivered') {
                $days = \Carbon\Carbon::parse($shipDate)->diffInDays(now());
                $order->ageing_days = $days;
                if ($days >= 10) {
                    $order->ageing_label = 'CRITICAL';
                    $order->ageing_color = '#7c2d12';
                } elseif ($days >= 7) {
                    $order->ageing_label = 'OVERDUE';
                    $order->ageing_color = '#dc2626';
                } elseif ($days >= 5) {
                    $order->ageing_label = 'DUE';
                    $order->ageing_color = '#e8a838';
                } else {
                    $order->ageing_label = 'ON TIME';
                    $order->ageing_color = '#16a34a';
                }
            } elseif ($order->current_status === 'delivered') {
                $order->ageing_label = 'DELIVERED';
                $order->ageing_color = '#16a34a';
            }
            return $order;
        });

        $channels = SalesChannel::active()->get();
        $stats = [
            'total_shipped'  => $orders->getCollection()->whereNotNull('tracking_id')->where('tracking_id', '!=', '')->count(),
            'critical'     => $orders->getCollection()->filter(fn($o) => $o->ageing_label === 'CRITICAL')->count(),
            'total'      => $orders->total(),
            'in_transit'  => Order::whereNotNull('tracking_id')->where('tracking_id', '!=', '')
                ->where(function ($q) {
                    $q->whereIn('current_status', ['in_transit', 'shipped'])->orWhereNull('current_status');
                })->count(),
            'delivered'   => Order::where('current_status', 'delivered')->count(),
            'overdue'     => $orders->getCollection()->filter(fn($o) => in_array($o->ageing_label, ['OVERDUE', 'CRITICAL']))->count(),
        ];

        return view('sales.order-management', compact('orders', 'channels', 'stats'));
    }

    public function updateOrderManagement(Request $request, Order $order)
    {
        $request->validate([
            'ship_date'                => 'nullable|date',
            'current_status'           => 'nullable|in:in_transit,out_for_delivery,delivered,returned,exception',
            'delivery_date'            => 'nullable|date',
            'material_cost'            => 'nullable|numeric|min:0',
            'order_processing_charges' => 'nullable|numeric|min:0',
            'remarks'                  => 'nullable|string|max:500',
        ]);

        $this->salesService->updateOrderManagement($order, $request->only([
            'ship_date',
            'current_status',
            'delivery_date',
            'material_cost',
            'order_processing_charges',
            'remarks',
        ]));

        return back()->with('success', "Order {$order->order_number} updated.");
    }

    // ═══ TRACKING ═══

    public function updateTracking(Request $request, Order $order)
    {
        $request->validate(['tracking_id' => 'required|string']);
        $this->salesService->updateTracking($order, $request->tracking_id, $request->tracking_url, $request->shipping_provider);
        return back()->with('success', 'Tracking updated.');
    }
}
