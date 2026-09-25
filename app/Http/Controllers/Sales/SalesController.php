<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\{Order, SalesChannel, OrderTracking, Warehouse};
use App\Services\{SalesService, DashboardService};
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function __construct(
        private SalesService $salesService,
        private DashboardService $dashboardService
    ) {
    }

    private function cleanNumeric($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Remove line breaks, spaces, tabs
        $clean = preg_replace('/[\r\n\t\s]+/', '', trim($value));
        return is_numeric($clean) ? floatval($clean) : null;
    }
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

        $baseQuery = Order::when($activeCompany, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->sales_channel_id, fn ($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($q2) use ($v) {
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
            ->when($activeCompany, fn ($q, $v) => $q->where('company_code', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->sales_channel_id, fn ($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($q2) use ($v) {
                $q2->where('order_number', 'like', "%{$v}%")
                    ->orWhere('platform_order_id', 'like', "%{$v}%")
                    ->orWhere('customer_name', 'like', "%{$v}%");
            }))
            ->latest('order_date')
            ->get();

        $csv = "Order Number,PO Number,Invoice Number,Order Date,Sales Channel,SKU,SAP Code,Product Name,Vendor Name,Vendor Type,Warehouse id number,Qty,Unit Price,Order Amount,Vendor Payout Price,Payout Total,Warehouse,Shipping Method,Shipped Qty,Shipped Amount,Tracking ID,Carrier,Shipping Cost,Ship Date,Current Status,Delivery Date,Customer Type,Customer Name,Company Name,Email,Phone,Address,City,State,Zip,Country,Currency,Status\n";

        foreach ($orders as $o) {
            foreach ($o->items as $item) {
                $product = $item->product;
                $vendor = $product?->vendor;
                $qty = $item->quantity ?? 0;
                $payoutPrice = $product?->vendor_wsp ?? $product?->vendor_payout_price ?? 0;

                $csv .= implode(',', [
                    '"' . ($o->order_number ?? '') . '"',
                    '"' . ($o->platform_order_id ?? '') . '"',
                    '"' . ($o->invoice_number ?? '') . '"',
                    $o->order_date?->format('Y-m-d') ?? '',
                    '"' . ($o->salesChannel?->name ?? '') . '"',
                    '"' . ($item->sku ?? $product?->sku ?? '') . '"',
                    '"' . ($product?->sap_code ?? '') . '"',
                    '"' . str_replace('"', '""', $product?->name ?? '') . '"',
                    '"' . str_replace('"', '""', $vendor?->company_name ?? '') . '"',
                    '"' . ($vendor?->vendor_type ?? '') . '"',
                    '"' . ($o->warehouse_id_number ?? '') . '"',
                    $qty,
                    number_format(floatval($item->unit_price ?? 0), 2, '.', ''),
                    number_format(floatval($item->total_price ?? ($item->unit_price * $qty)), 2, '.', ''),
                    number_format(floatval($payoutPrice), 2, '.', ''),
                    number_format($payoutPrice * $qty, 2, '.', ''),
                    '"' . ($o->warehouse?->name ?? '') . '"',
                    '"' . (strtoupper($o->shipping_method ?? '')) . '"',
                    $item->shipped_qty ?? '',
                    number_format(floatval(($item->unit_price ?? 0) * ($item->shipped_qty ?? 0)), 2, '.', ''),
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
                    $o->currency ?? 'NA',
                    '"' . ($o->status ?? '') . '"',
                ]) . "\n";
            }
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
        $activeCode = session('active_company');
        // AJAX SKU check
        if ($request->has('check_sku')) {
            $sku = trim($request->check_sku);
            $product = \App\Models\Product::where(['sku' => $sku, 'company_code' => $activeCode])->first();
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

        $activeCode = session('active_company');

        $warehouses = Warehouse::active()
            ->when($activeCode, fn ($q, $v) => $q->where('company_code', $v))
            ->withCount(['inventory' => fn ($q) => $q->where('quantity', '>', 0)])
            ->withSum(['inventory' => fn ($q) => $q->where('quantity', '>', 0)], 'quantity')
            ->withSum(['inventory' => fn ($q) => $q->where('quantity', '>', 0)], 'available_quantity')
            ->with(['subWarehouses', 'subLocations'])
            ->get();



        $channels = SalesChannel::active()->get();
        return view('sales.upload', compact('channels', 'warehouses'));
    }


    public function downloadTemplate()
    {
        // $csv = "Order Date,PO Number / Order ID,Invoice Number,Sales Channel,Vendor Name,Vendor Type,SAP Code,Style Code / SKU,Per Unit Sales Price,Order Qty,Order Amount,Warehouse ID,Warehouse Name,Shipping Method,Customer Type,Customer Name,Company Name,Shipping Address,City,State / Province,Zip / Postal Code,Country,Phone Number,Email,Notes\n";
        // Sample Data Row
        $headers = [
            'Order Date',
            'PO Number / Order ID',
            'Invoice Number',
            'Sales Channel',
            'Vendor Name',
            'Vendor Type',
            'SAP Code',
            'Style Code / SKU',
            'Per Unit Sales Price',
            'Order Qty',
            'Order Amount',
            'Warehouse ID',
            'Warehouse Name',
            'Shipping Method',
            'Customer Type',
            'Customer Name',
            'Company Name',
            'Shipping Address',
            'City',
            'State / Province',
            'Zip / Postal Code',
            'Country',
            'Phone Number',
            'Email',
            'Notes'
        ];

        $csv = implode(',', $headers) . "\n";
        $vals = [
            date('Y-m-d'),
            'PO-ORD-70981308',
            '', //Invoice Number
            '', //Sales Channel
            '', //Vendor Name
            '', //Vendor Type
            '', //SAP Code
            'CCC1234',
            '2.3',
            '2',
            '4.6',
            'WH-NL-001',
            '', //warehouse name
            'sp/mpl/ebl',
            'b2b', //Customer Type
            'James',
            'Brick House',
            '9/265 Indra Nagra',
            'Ghaziabad',
            'Uttar Pradesh',
            '201301',
            'India',
            '9415464698',
            'james@gmail.com',
            ''
        ];

        $csv .= implode(',', $vals) . "\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Sales_Upload_Template.csv"',
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

            $result = $this->salesService->processUploadedRows($rows, $request->company_code, $request->custom_date ?? null);

            \App\Models\ActivityLog::log('uploaded', 'sales_data', auth()->user(), null, [
                'company_code' => $request->company_code,
                'created' => $result['created'],
                'errors' => count($result['errors']),
            ], "Sales data uploaded: {$result['created']} orders created");

            // return back()->with('upload_result', $result)->with(
            //     $result['created'] > 0 ? 'success' : 'error',
            //     "{$result['created']} order(s) created from {$result['total_rows']} rows." .
            //         (count($result['errors']) > 0 ? ' ' . implode(", ", $result['errors']) : '')
            // );

            // Build message
            $errorCount = count($result['errors'] ?? []);
            $msg = "{$result['created']} order(s) created from {$result['total_rows']} rows." .
                ($errorCount > 0 ? " {$errorCount} error(s)." : '');

            // Store errors in cache if any (not session)
            if ($errorCount > 0) {
                $errorKey = 'upload_errors_' . auth()->id() . '_' . time();
                \Cache::put($errorKey, array_slice($result['errors'], 0, 20), now()->addMinutes(10));

                return back()
                    ->with($result['created'] > 0 ? 'success' : 'error', $msg)
                    ->with('error_key', $errorKey);
            }

            return back()->with($result['created'] > 0 ? 'success' : 'error', $msg);

            // return back()
            //     ->with(
            //         $result['created'] > 0 ? 'success' : 'error',
            //         "{$result['created']} order(s) created from {$result['total_rows']} rows." .
            //         (count($result['errors']) > 0 ? ' ' . count($result['errors']) . ' error(s).' : '')
            //     )
            //     ->with('upload_errors', array_slice($result['errors'] ?? [], 0, 10))
            //     ->with('created_count', $result['created'] ?? 0)
            //     ->with('skipped_count', $result['skipped'] ?? 0);


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
            'orders.*.warehouse_id_number'        => 'required|string',
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

    // ═══ TO BE SHIPPED ═══

    //Two types of orders(mpl and ebl) will show under "To Be Shipped".

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
            ->when($request->channel_id, fn ($q, $v) => $q->where('sales_channel_id', $v))
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

        $itemQtys = collect($request->items)->mapWithKeys(fn ($data, $itemId) => [$itemId => $data['shipped_qty']])->toArray();

        $this->salesService->shipOrder(
            $order,
            $itemQtys,
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
            ->when($request->channel_id, fn ($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('current_status', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($q2) use ($v) {
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
            'critical'     => $orders->getCollection()->filter(fn ($o) => $o->ageing_label === 'CRITICAL')->count(),
            'total'      => $orders->total(),
            'in_transit'  => Order::where('company_code', $activeCompany)->whereNotNull('tracking_id')->where('tracking_id', '!=', '')
                ->where(function ($q) {
                    $q->whereIn('current_status', ['in_transit', 'shipped'])->orWhereNull('current_status');
                })->count(),
            'delivered'   => Order::where('company_code', $activeCompany)->where('current_status', 'delivered')->count(),
            'overdue'     => $orders->getCollection()->filter(fn ($o) => in_array($o->ageing_label, ['OVERDUE', 'CRITICAL']))->count(),
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

    public function storeTracking(Request $request, Order $order)
    {
        $request->validate([
            'shipping_provider' => 'required|string|max:100',
            'tracking_id'       => 'required|string|max:100',
            'tracking_url'      => 'nullable|url|max:500',
            'shipped_date'      => 'nullable|date',
        ]);

        $tracking = OrderTracking::create([
            'order_id'          => $order->id,
            'tracking_id'       => $request->tracking_id,
            'shipping_provider' => $request->shipping_provider,
            'tracking_url'      => $request->tracking_url,
            'shipped_date'      => $request->shipped_date,
            'added_by'          => auth()->id(),
            'notes'             => $request->notes,
        ]);

        return back()->with('success', 'Tracking information added successfully.');
    }

    /**
     * Cancel an Order
     */
    public function cancelOrder(Request $request, Order $order)
    {
        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        // Only allow cancellation for specific statuses
        if (!in_array($order->status, ['pending', 'open', 'label_created', 'processing'])) {
            return response()->json([
                'success' => false,
                'message' => 'This order cannot be cancelled at its current status.'
            ], 422);
        }
        //         echo '<pre>';
        // print_r($order->items);
        // exit;
        $oldStatus = $order->status;

        \DB::beginTransaction();
        try {
            foreach ($order->items as $item) {
                $qty = $item->shipped_qty;
                $inventory = \App\Models\Inventory::where('product_id', $item->product_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->where('company_code', $order->company_code)
                    ->first();

                file_put_contents(storage_path('logs/cancel-orders.log'), print_r($inventory, true) . "\n", FILE_APPEND);

                if ($inventory) {
                    $inventory->increment('quantity', $qty);
                    $inventory->increment('available_quantity', $qty);

                    \App\Models\InventoryLog::record($inventory, 'order_canceled_restock', $qty, [
                        'description'    => "Restocked {$qty} units of {$item->sku} from canceled order {$order->order_number}",
                        'reference_type' => 'order_canceled_restock',
                        'reference_id'   => $order->id,
                        'reference_code' => $order->order_number,
                    ]);

                    file_put_contents(storage_path('logs/cancel-orders.log'), "Restocked {$qty} units of {$item->sku} from canceled order {$order->order_number}" . "\n", FILE_APPEND);

                    \App\Models\Product::where('id', $item->product_id)->increment('stock_quantity', $qty);
                }

                // $item->update(['restock' => true, 'restocked_qty' => $qty]);
            }

            // $order->update([
            //     'status'           => 'cancelled',
            //     'cancellation_reason' => $request->reason,
            //     'cancelled_at'     => now(),
            //     'cancelled_by'     => auth()->id(),
            // ]);

            \App\Models\ActivityLog::log('order_cancellation', 'order', $order ?? auth()->user(), [$oldStatus], [
                'status' => 'cancelled',
                'reason' => $request->reason,
                'user_id' => auth()->id()
            ], "Order #{$order->order_number} cancelled by user");


            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully.'
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $request->validate([
            'status'  => 'required|in:open,pending,label_created,processing,confirmed,shipped,cancelled,returned,exception,lost_in_transit',
            'reason' => 'nullable|string|max:500',
        ]);
        //'open','pending','label_created','processing','shipped','delivered','cancelled','returned','exception','lost_in_transit'
        $oldStatus = $order->status;

        $order->update([
            'status'          => $request->status,
            'remarks'  => $request->remarks,
            'updated_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        // Activity Log
        \App\Models\ActivityLog::log(
            'status_updated',
            'order',
            $order,
            ['status' => $oldStatus],
            [
                'status' => $request->status,
                'remarks' => $request->remarks,
                'user_id' => auth()->id()
            ],
            "Order status changed from {$oldStatus} to {$request->status}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully.'
        ]);
    }
    // ═══ ORDER RETURNS ═══

    public function returns(Request $request)
    {
        $activeCode = session('active_company');

        $returns = \App\Models\OrderReturn::with('order.salesChannel', 'vendor', 'items.product', 'creator')
            ->when($activeCode, fn ($q) => $q->where('company_code', $activeCode))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->reason, fn ($q, $v) => $q->where('reason', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($q2) use ($v) {
                $q2->where('return_number', 'LIKE', "%{$v}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'LIKE', "%{$v}%")->orWhere('platform_order_id', 'LIKE', "%{$v}%"));
            }))
            ->latest('return_date')
            ->paginate(30)->withQueryString();

        $stats = [
            'total'     => \App\Models\OrderReturn::when($activeCode, fn ($q) => $q->where('company_code', $activeCode))->count(),
            'initiated' => \App\Models\OrderReturn::when($activeCode, fn ($q) => $q->where('company_code', $activeCode))->where('status', 'initiated')->count(),
            'received'  => \App\Models\OrderReturn::when($activeCode, fn ($q) => $q->where('company_code', $activeCode))->where('status', 'received')->count(),
            'refunded'  => \App\Models\OrderReturn::when($activeCode, fn ($q) => $q->where('company_code', $activeCode))->where('status', 'refunded')->sum('refund_amount'),
        ];

        return view('sales.returns.index', compact('returns', 'stats'));
    }

    public function createReturn(Request $request)
    {
        // AJAX order lookup
        if ($request->has('lookup_order') && $request->ajax()) {
            $orderNum = trim($request->lookup_order);
            $order = Order::where('order_number', $orderNum)
                ->orWhere('platform_order_id', $orderNum)
                ->with(['items.product', 'salesChannel'])
                ->first();

            if (!$order) {
                return response()->json(['found' => false]);
            }

            return response()->json([
                'found'        => true,
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'channel'      => $order->salesChannel->name ?? '—',
                'date'         => $order->order_date?->format('d M Y'),
                'total'        => floatval($order->total_amount),
                'customer'     => $order->customer_name ?? '—',
                'status'       => $order->status,
                'items'        => $order->items->map(fn ($i) => [
                    'id'         => $i->id,
                    'product_id' => $i->product_id,
                    'sku'        => $i->sku ?? $i->product->sku ?? '—',
                    'name'       => $i->product->name ?? '—',
                    'quantity'   => intval($i->quantity),
                    'shipped_qty' => intval($i->shipped_qty ?? $i->quantity),
                    'unit_price' => floatval($i->unit_price),
                ]),
            ]);
        }

        $warehouses = \App\Models\Warehouse::when(session('active_company'), fn ($q) => $q->where('company_code', session('active_company')))
            ->orderBy('name')->get();

        return view('sales.returns.create', compact('warehouses'));
    }
    public function storeReturn(Request $request)
    {
        $request->validate([
            'order_id'                 => 'required|exists:orders,id',
            'reason'                   => 'required|in:damaged,wrong_item,missing_item,quality_issue,customer_request,short_shipment,other',
            'reason_detail'            => 'nullable|string|max:1000',
            'warehouse_id'             => 'nullable|exists:warehouses,id',
            'tracking_id'              => 'nullable|string|max:100',
            'carrier'                  => 'nullable|string|max:50',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.return_qty'       => 'nullable|integer|min:0',
            'items.*.condition_status' => 'nullable|in:good,damaged,defective,unsellable',
            'return_date'              => 'nullable|date',
        ]);

        $order = Order::withoutGlobalScopes()->with(['items.product' => fn ($q) => $q->withoutGlobalScopes()])->findOrFail($request->order_id);
        $activeCode = session('active_company') ?? $order->company_code;

        // Filter out items with 0 return qty

        $returnItems = collect($request->items)->filter(fn ($item) => intval(data_get($item, 'return_qty', 0)) > 0);

        if ($returnItems->isEmpty()) {
            return back()->with('error', 'Please enter return quantity for at least one item.')->withInput();
        }

        // Validate quantities before anything
        $errors = [];
        $vendorId = null;

        foreach ($returnItems as $itemData) {
            $orderItem = $order->items->firstWhere('product_id', $itemData['product_id']);
            if (!$orderItem) {
                $errors[] = "Product #{$itemData['product_id']} not found in this order.";
                continue;
            }

            $shippedQty = intval($orderItem->shipped_qty > 0 ? $orderItem->shipped_qty : $orderItem->quantity);
            $alreadyReturned = intval($orderItem->returned_qty ?? 0);
            $maxReturnable = $shippedQty - $alreadyReturned;
            $returnQty = intval($itemData['return_qty']);
            $sku = $orderItem->sku ?? $orderItem->product->sku ?? '—';

            if ($returnQty > $maxReturnable) {
                $errors[] = "SKU {$sku}: return qty ({$returnQty}) exceeds returnable ({$maxReturnable}). Shipped: {$shippedQty}, already returned: {$alreadyReturned}.";
            }

            if (!$vendorId) {
                $vendorId = $orderItem->vendor_id;
            }
        }

        if (!empty($errors)) {
            return back()->with('error', implode(' | ', $errors))->withInput();
        }

        try {
            \DB::beginTransaction();

            $totalReturnAmount = 0;
            $warehouseId = $request->warehouse_id ?? $order->warehouse_id;

            $orderReturn = \App\Models\OrderReturn::create([
                'return_number'      => \App\Models\OrderReturn::generateNumber($activeCode),
                'order_id'           => $order->id,
                'company_code'       => $activeCode,
                'vendor_id'          => $vendorId,
                'return_date'        => $request->return_date ?? now()->toDateString(),
                'reason'             => $request->reason,
                'reason_detail'      => $request->reason_detail,
                'status'             => 'initiated',
                'warehouse_id'       => $warehouseId,
                'tracking_id'        => $request->tracking_id,
                'carrier'            => $request->carrier,
                'created_by'         => auth()->id(),
            ]);

            foreach ($returnItems as $itemData) {
                $orderItem = $order->items->firstWhere('product_id', $itemData['product_id']);
                if (!$orderItem) {
                    continue;
                }
                $returnQty = intval($itemData['return_qty'] ?? 0);
                $unitPrice = floatval($orderItem->unit_price);
                $returnAmount = round($unitPrice * $returnQty, 2);
                $totalReturnAmount += $returnAmount;
                $sku = $orderItem->sku ?? $orderItem->product->sku ?? '—';
                $condition = $itemData['condition_status'] ?? 'good';

                // Create return item
                \App\Models\OrderReturnItem::create([
                    'order_return_id'  => $orderReturn->id,
                    'order_item_id'    => $orderItem->id,
                    'product_id'       => $itemData['product_id'],
                    'sku'              => $sku,
                    'return_qty'       => $returnQty,
                    'unit_price'       => $unitPrice,
                    'return_amount'    => $returnAmount,
                    'condition_status' => $condition,
                ]);

                // Update order item returned qty
                $orderItem->increment('returned_qty', $returnQty);

                // Restore inventory if condition is good
                if ($condition === 'good' && $orderItem->product_id) {
                    $inventory = \App\Models\Inventory::where('product_id', $orderItem->product_id)
                        ->where('warehouse_id', $warehouseId)
                        ->first();

                    if ($inventory) {
                        $prevQty = intval($inventory->quantity);
                        $prevAvailable = intval($inventory->available_quantity);
                        $prevReserved = intval($inventory->reserved_quantity ?? 0);

                        $inventory->increment('quantity', $returnQty);
                        $inventory->increment('available_quantity', $returnQty);

                        // Inventory log
                        \DB::table('inventory_logs')->insert([
                            'product_id'         => $orderItem->product_id,
                            'warehouse_id'       => $warehouseId,
                            'company_code'       => $activeCode,
                            'sku'                => $sku,
                            'action'             => 'return',
                            'description'        => "Return {$orderReturn->return_number} from order {$order->order_number} — {$request->reason}",
                            'previous_quantity'  => $prevQty,
                            'change_quantity'    => $returnQty,
                            'updated_quantity'   => $prevQty + $returnQty,
                            'previous_available' => $prevAvailable,
                            'change_available'   => $returnQty,
                            'updated_available'  => $prevAvailable + $returnQty,
                            'previous_reserved'  => $prevReserved,
                            'change_reserved'    => 0,
                            'updated_reserved'   => $prevReserved,
                            'reference_type'     => 'App\\Models\\OrderReturn',
                            'reference_id'       => $orderReturn->id,
                            'reference_code'     => $orderReturn->return_number,
                            'performed_by'       => auth()->id(),
                            'ip_address'         => request()->ip(),
                            'metadata'           => json_encode([
                                'order_number' => $order->order_number,
                                'reason'       => $request->reason,
                                'condition'    => $condition,
                                'return_qty'   => $returnQty,
                                'unit_price'   => $unitPrice,
                            ]),
                            'created_at'         => now(),
                        ]);
                    }
                }
            }

            $orderReturn->update(['total_return_amount' => $totalReturnAmount]);

            // Update order status based on return completeness
            $order->refresh();
            $allReturned = $order->items->every(function ($item) {
                $shipped = intval($item->shipped_qty > 0 ? $item->shipped_qty : $item->quantity);
                return intval($item->returned_qty ?? 0) >= $shipped;
            });

            $anyReturned = $order->items->contains(fn ($item) => intval($item->returned_qty ?? 0) > 0);

            if ($allReturned) {
                $order->update(['status' => 'returned']);
            } elseif ($anyReturned) {
                $order->update(['status' => 'partially_returned']);
            }

            // Activity log
            \App\Models\ActivityLog::log('created', 'order_return', $orderReturn, null, [
                'order_number'  => $order->order_number,
                'return_number' => $orderReturn->return_number,
                'items_count'   => $returnItems->count(),
                'total_amount'  => $totalReturnAmount,
                'reason'        => $request->reason,
                'order_status'  => $order->fresh()->status,
                'inventory_restored' => $returnItems->filter(fn ($i) => ($i['condition_status'] ?? 'good') === 'good')->count() . ' items',
                'created_by'    => auth()->user()->name,
            ], "Return {$orderReturn->return_number} for order {$order->order_number} — {$returnItems->count()} items, amount: {$totalReturnAmount}");

            \DB::commit();

            $cs = config('app.active_currency_symbol', '$');
            return redirect()->route('sales.returns.show', $orderReturn)
                ->with('success', "Return {$orderReturn->return_number} created. Amount: {$cs}" . number_format($totalReturnAmount, 2));
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Return creation failed: ' . $e->getMessage());
            return back()->with('error', 'Failed: ' . $e->getMessage())->withInput();
        }
    }

    public function showReturn(\App\Models\OrderReturn $orderReturn)
    {
        // $orderReturn->load('order.salesChannel', 'vendor', 'items.product', 'creator', 'approver', 'inspector', 'warehouse');

        $orderReturn->load([
            'order',
            'order.salesChannel',
            'vendor',
            'creator',
            'approver',
            'inspector',
            'warehouse',
            'items.product'    // Load product relation
        ]);



        return view('sales.returns.show', compact('orderReturn'));
    }

    public function updateReturnStatus(Request $request, \App\Models\OrderReturn $orderReturn)
    {
        $request->validate([
            'status'           => 'required|in:received,inspected,approved,rejected,refunded',
            'inspection_notes' => 'nullable|string|max:1000',
            'refund_amount'    => 'nullable|numeric|min:0',
        ]);

        $oldStatus = $orderReturn->status;
        $updateData = ['status' => $request->status];

        if ($request->status === 'received') {
            $updateData['received_date'] = now()->toDateString();
        } elseif ($request->status === 'inspected') {
            $updateData['inspected_by'] = auth()->id();
            $updateData['inspected_at'] = now();
            $updateData['inspection_notes'] = $request->inspection_notes;
        } elseif ($request->status === 'approved') {
            $updateData['approved_by'] = auth()->id();
            $updateData['approved_at'] = now();
        } elseif ($request->status === 'refunded') {
            $updateData['refund_amount'] = $request->refund_amount ?? $orderReturn->total_return_amount;
            $updateData['refund_status'] = 'completed';
        }

        $orderReturn->update($updateData);

        \App\Models\ActivityLog::log('updated', 'order_return', $orderReturn, [$oldStatus], [
            'old_status' => $oldStatus,
            'new_status' => $request->status,
        ], "Return {$orderReturn->return_number} status: {$oldStatus} → {$request->status}");

        return back()->with('success', "Return status updated to " . ucfirst(str_replace('_', ' ', $request->status)) . ".");
    }

    public function restockReturn(Request $request, \App\Models\OrderReturn $orderReturn)
    {
        if (!in_array($orderReturn->status, ['approved', 'inspected'])) {
            return back()->with('error', 'Return must be approved or inspected before restocking.');
        }

        $restocked = 0;

        \DB::beginTransaction();
        try {
            foreach ($orderReturn->items as $returnItem) {
                if ($returnItem->restock || $returnItem->condition_status === 'unsellable') {
                    continue;
                }
                if ($returnItem->condition_status !== 'good') {
                    continue;
                }

                $qty = $returnItem->return_qty;
                $inventory = \App\Models\Inventory::where('product_id', $returnItem->product_id)
                    ->where('warehouse_id', $orderReturn->warehouse_id)
                    ->where('company_code', $orderReturn->company_code)
                    ->first();

                if ($inventory) {
                    $inventory->increment('quantity', $qty);
                    $inventory->increment('available_quantity', $qty);

                    \App\Models\InventoryLog::record($inventory, 'return_restock', $qty, [
                        'description'    => "Restocked {$qty} units of {$returnItem->sku} from return {$orderReturn->return_number}",
                        'reference_type' => 'order_return',
                        'reference_id'   => $orderReturn->id,
                        'reference_code' => $orderReturn->return_number,
                    ]);

                    \App\Models\Product::where('id', $returnItem->product_id)->increment('stock_quantity', $qty);
                }

                $returnItem->update(['restock' => true, 'restocked_qty' => $qty]);
                $restocked++;
            }

            $orderReturn->update(['status' => 'restocked']);
            \DB::commit();

            return back()->with('success', "{$restocked} item(s) restocked to inventory.");
        } catch (\Exception $e) {
            \DB::rollBack();
            return back()->with('error', 'Restock failed: ' . $e->getMessage());
        }
    }


    /**
     * Delete order, restore inventory, log everything
     * Route: DELETE sales/orders/{order}
     */
    public function deleteOrder(\App\Models\Order $order)
    {
        if (!auth()->user()->isAdmin() && !\App\Services\PermissionService::can(auth()->user(), 'sales.orders.delete')) {
            abort(403, 'You do not have permission to delete orders.');
        }

        $orderNumber = $order->order_number;
        $itemCount = $order->items()->count();
        $restoredItems = [];

        \DB::transaction(function () use ($order, &$restoredItems) {
            foreach ($order->items as $item) {
                $qty = intval($item->shipped_qty > 0 ? $item->shipped_qty : $item->quantity);
                $sku = $item->sku ?? ($item->product->sku ?? '—');

                if ($qty <= 0 || !$item->product_id) {
                    continue;
                }

                $inventory = \App\Models\Inventory::where('product_id', $item->product_id)
                    ->where('warehouse_id', $order->warehouse_id)
                    ->first();

                if (!$inventory) {
                    continue;
                }

                // Capture before values
                $prevQty = intval($inventory->quantity);
                $prevAvailable = intval($inventory->available_quantity);
                $prevReserved = intval($inventory->reserved_quantity ?? 0);

                // Restore inventory
                $inventory->increment('quantity', $qty);
                $inventory->increment('available_quantity', $qty);

                // After values
                $updatedQty = $prevQty + $qty;
                $updatedAvailable = $prevAvailable + $qty;

                // Inventory log
                \DB::table('inventory_logs')->insert([
                    'product_id'          => $item->product_id,
                    'warehouse_id'        => $order->warehouse_id,
                    'company_code'        => $order->company_code,
                    'sku'                 => $sku,
                    'action'              => 'order_deleted',
                    'description'         => "Order {$order->order_number} deleted — qty restored",
                    'previous_quantity'   => $prevQty,
                    'change_quantity'     => $qty,
                    'updated_quantity'    => $updatedQty,
                    'previous_available'  => $prevAvailable,
                    'change_available'    => $qty,
                    'updated_available'   => $updatedAvailable,
                    'previous_reserved'   => $prevReserved,
                    'change_reserved'     => 0,
                    'updated_reserved'    => $prevReserved,
                    'reference_type'      => 'App\\Models\\Order',
                    'reference_id'        => $order->id,
                    'reference_code'      => $order->order_number,
                    'performed_by'        => auth()->id(),
                    'ip_address'          => request()->ip(),
                    'metadata'            => json_encode([
                        'order_status'    => $order->status,
                        'shipped_qty'     => intval($item->shipped_qty),
                        'ordered_qty'     => intval($item->quantity),
                        'restored_qty'    => $qty,
                        'platform_order'  => $order->platform_order_id,
                        'channel'         => $order->salesChannel->name ?? null,
                    ]),
                    'created_at'          => now(),
                ]);

                $restoredItems[] = [
                    'sku'      => $sku,
                    'qty'      => $qty,
                    'inv_from' => $prevQty,
                    'inv_to'   => $updatedQty,
                ];
            }

            // Delete order items then order
            $order->items()->delete();
            $order->delete();
        });

        // Activity log
        \App\Models\ActivityLog::log('deleted', 'order', $order, null, [
            'order_number'    => $orderNumber,
            'company_code'    => $order->company_code,
            'platform_order'  => $order->platform_order_id,
            'status_at_delete' => $order->status,
            'items_deleted'   => $itemCount,
            'inventory_restored' => $restoredItems,
            'deleted_by'      => auth()->user()->name,
            'ip'              => request()->ip(),
        ], "Order {$orderNumber} deleted — {$itemCount} items, inventory restored for " . count($restoredItems) . " SKUs — by " . auth()->user()->name);

        return back()->with('success', "Order {$orderNumber} deleted. Inventory restored for " . count($restoredItems) . " item(s).");
    }

    public function downloadToBeShipped(Request $request)
    {
        //Two types of orders(mpl and ebl) in a CSV file for download.
        $activeCompany = session('active_company');
        $orders = \App\Models\Order::with('items.product')
            ->where('company_code', $activeCompany)
            ->where(function ($q) {
                $q->whereNull('shipping_method')->orWhere('shipping_method', '!=', 'sp');
            })
            ->where(function ($q) {
                $q->whereNull('tracking_id')->orWhere('tracking_id', '');
            })
            // add your existing filters if needed
            ->when($request->channel_id, fn ($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, function ($q, $v) {
                $q->where(function ($q2) use ($v) {
                    $q2->where('platform_order_id', 'like', "%{$v}%")
                        ->orWhere('order_number', 'like', "%{$v}%");
                });
            })
            ->latest('order_date')
            ->get();


        $filename = 'to-be-shipped-' . date('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');

            // Header row

            fputcsv($file, [
                'Order Pack Type: palletize or non_palletize',
            ]);

            fputcsv($file, [
                'Carrier: any of the following: Fedex, UPS, USPS, LTL, Other',
            ]);

            fputcsv($file, [
                'Order No',
                'Platform Order ID',
                'Style Code',
                'Order Qty',
                'Shipped Qty',
                'Tracking ID',
                'Ship Cost',
                'Order Pack Type',
                'Carrier',
            ]);


            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    fputcsv($file, [
                        $order->order_number ?? $order->id,
                        $order->platform_order_id ?? '',
                        $item->product->sku ?? $item->sku ?? '',
                        $item->quantity ?? 0,
                        $item->shipped_qty ?? 0,
                        $order->tracking_id ?? '',
                        $order->shipping_cost ?? 0,
                        $order->order_pack_type ?? '',
                        $order->carrier ?? '',
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadStorePickup(Request $request)
    {
        // Download orders store pickup orders. Using Order Management tab.
        $activeCompany = session('active_company');
        $orders = \App\Models\Order::with('items.product')
            ->where('company_code', $activeCompany)
            ->where('shipping_method', 'sp')            
            // add your existing filters if needed
            ->when($request->channel_id, fn ($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, function ($q, $v) {
                $q->where(function ($q2) use ($v) {
                    $q2->where('platform_order_id', 'like', "%{$v}%")
                        ->orWhere('order_number', 'like', "%{$v}%");
                });
            })
            ->latest('order_date')
            ->get();

        $filename = 'store-pickup-' . date('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');

            // Header row

            fputcsv($file, [
                'Current Status: any of the following: in_transit, out_for_delivery, delivered, returned, exception',
            ]);

            fputcsv($file, [
                'Order No',
                'Platform Order ID',
                'Style Code',
                'Order Qty',
                'Shipped Qty',
                'Ship Date',
                'Current Status',
                'Delivery Date',
                'Material Cost',
                'Processing Cost',
                'Shipping Cost',
                'Remarks'
            ]);

            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    fputcsv($file, [
                        $order->order_number ?? $order->id,
                        $order->platform_order_id ?? '',
                        $item->product->sku ?? $item->sku ?? '',
                        $item->quantity ?? 0,
                        $item->shipped_qty ?? 0,
                        $order->ship_date?->format('Y-m-d') ?? now()->toDateString(),
                        $order->current_status ?? $order->status ?? 'unknown',
                        $order->delivery_date?->format('Y-m-d') ?? now()->toDateString(),
                        $order->material_cost ?? 0,
                        $order->processing_cost ?? 0,
                        $order->shipping_cost ?? 0,
                        $order->remarks ?? '',
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function uploadToBeShippedCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file   = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        $rowsByOrder = [];   // order_no => [ items + shipping info ]
        $rowNumber   = 0;
        $headerFound = false;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (empty(array_filter($row))) {
                continue;
            }

            // Skip instruction rows
            $first = strtolower(trim($row[0] ?? ''));
            if (str_contains($first, 'order pack type') || str_contains($first, 'carrier:')) {
                continue;
            }

            // Skip header
            if (!$headerFound) {
                if (str_contains($first, 'order no') || str_contains($first, 'order_no')) {
                    $headerFound = true;
                    continue;
                }
            }

            // Columns:
            // 0 Order No | 1 Platform Order ID | 2 Style Code | 3 Order Qty
            // 4 Shipped Qty | 5 Tracking ID | 6 Ship Cost | 7 Order Pack Type | 8 Carrier
            $orderNo    = trim($row[0] ?? '');
            $styleCode  = trim($row[2] ?? '');
            $shippedQty = (int) trim($row[4] ?? 0);
            $trackingId = trim($row[5] ?? '');
            $shipCost   = trim($row[6] ?? '');
            $packType   = trim($row[7] ?? '');
            $carrier    = trim($row[8] ?? '');

            if (!$orderNo || !$styleCode || $shippedQty <= 0) {
                continue;
            }

            if (!isset($rowsByOrder[$orderNo])) {
                $rowsByOrder[$orderNo] = [
                    'items'        => [],          // style_code => shipped_qty
                    'tracking_id'  => $trackingId,
                    'ship_cost'    => $shipCost !== '' ? (float) $shipCost : null,
                    'carrier'      => $carrier ?: 'Other',
                    'pack_type'    => $packType,
                ];
            } else {
                // Keep first non-empty tracking / cost / carrier
                if ($trackingId && empty($rowsByOrder[$orderNo]['tracking_id'])) {
                    $rowsByOrder[$orderNo]['tracking_id'] = $trackingId;
                }
                if ($shipCost !== '' && $rowsByOrder[$orderNo]['ship_cost'] === null) {
                    $rowsByOrder[$orderNo]['ship_cost'] = (float) $shipCost;
                }
                if ($carrier && $rowsByOrder[$orderNo]['carrier'] === 'Other') {
                    $rowsByOrder[$orderNo]['carrier'] = $carrier;
                }
            }

            // Sum qty if same style appears multiple times
            if (!isset($rowsByOrder[$orderNo]['items'][$styleCode])) {
                $rowsByOrder[$orderNo]['items'][$styleCode] = 0;
            }
            $rowsByOrder[$orderNo]['items'][$styleCode] += $shippedQty;
        }

        fclose($handle);

        $updated = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rowsByOrder as $orderNo => $data) {
            // Find order
            $order = \App\Models\Order::where('order_number', $orderNo)
                ->orWhere('platform_order_id', $orderNo)
                ->first();

            if (!$order) {
                $errors[] = "Order not found: {$orderNo}";
                $skipped++;
                continue;
            }

            // Already shipped? skip (optional)
            if ($order->status === 'shipped' && $order->tracking_id) {
                $errors[] = "Already shipped: {$orderNo}";
                $skipped++;
                continue;
            }

            // Build itemShippedQtys: [ item_id => qty ]
            $itemShippedQtys = [];

            foreach ($data['items'] as $styleCode => $qty) {
                $item = $order->items()
                    ->where(function ($q) use ($styleCode) {
                        $q->where('sku', $styleCode)
                            ->orWhereHas('product', fn ($p) => $p->where('sku', $styleCode));
                    })
                    ->first();

                if (!$item) {
                    $errors[] = "Style {$styleCode} not found in order {$orderNo}";
                    continue;
                }

                $itemShippedQtys[$item->id] = $qty;
            }

            if (empty($itemShippedQtys)) {
                $skipped++;
                continue;
            }

            if (empty($data['tracking_id'])) {
                $errors[] = "Missing Tracking ID for order {$orderNo}";
                $skipped++;
                continue;
            }

            try {
                // ✅ Reuse existing service method
                $this->salesService->shipOrder(
                    $order,
                    $itemShippedQtys,
                    $data['tracking_id'],
                    $data['ship_cost'],
                    $data['carrier']
                );

                // Update pack type if your shipOrder doesn't handle it
                if (!empty($data['pack_type'])) {
                    $order->update(['order_pack_type' => $data['pack_type']]);
                }

                $updated++;
            } catch (\Throwable $e) {
                $errors[] = "Failed {$orderNo}: " . $e->getMessage();
                $skipped++;
            }
        }

        $message = "CSV processed. Orders updated: {$updated}, Skipped: {$skipped}";
        if ($errors) {
            $message .= ' | ' . implode(' | ', array_slice($errors, 0, 8));
        }

        return back()->with($updated > 0 ? 'success' : 'error', $message);
    }

    public function uploadStorePickupCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file   = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        $rowsByOrder = [];
        $headerFound = false;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) {
                continue;
            }

            $first = strtolower(trim($row[0] ?? ''));

            // Skip instruction row
            if (str_contains($first, 'current status')) {
                continue;
            }

            // Skip header
            if (!$headerFound) {
                if (str_contains($first, 'order no') || str_contains($first, 'order_no')) {
                    $headerFound = true;
                    continue;
                }
            }

            // Columns:
            // 0 Order No | 1 Platform Order ID | 2 Style Code | 3 Order Qty | 4 Shipped Qty
            // 5 Ship Date | 6 Current Status | 7 Delivery Date
            // 8 Material Cost | 9 Processing Cost | 10 Shipping Cost | 11 Remarks

            $orderNo         = trim($row[0] ?? '');
            $shipDate        = trim($row[5] ?? '');
            $currentStatus   = trim($row[6] ?? '');
            $deliveryDate    = trim($row[7] ?? '');
            $materialCost    = trim($row[8] ?? '');
            $processingCost  = trim($row[9] ?? '');
            $shippingCost    = trim($row[10] ?? '');
            $remarks         = trim($row[11] ?? '');

            if (!$orderNo) {
                continue;
            }

            // Keep first non-empty values per order
            if (!isset($rowsByOrder[$orderNo])) {
                $rowsByOrder[$orderNo] = [
                    'ship_date'                => $shipDate ?: null,
                    'current_status'           => $currentStatus ?: null,
                    'delivery_date'            => $deliveryDate ?: null,
                    'material_cost'            => $materialCost !== '' ? (float) $materialCost : null,
                    'order_processing_charges' => $processingCost !== '' ? (float) $processingCost : null,
                    'shipping_cost'            => $shippingCost !== '' ? (float) $shippingCost : null,
                    'remarks'                  => $remarks ?: null,
                ];
            } else {
                $existing = &$rowsByOrder[$orderNo];

                if ($shipDate && empty($existing['ship_date'])) {
                    $existing['ship_date'] = $shipDate;
                }
                if ($currentStatus && empty($existing['current_status'])) {
                    $existing['current_status'] = $currentStatus;
                }
                if ($deliveryDate && empty($existing['delivery_date'])) {
                    $existing['delivery_date'] = $deliveryDate;
                }
                if ($materialCost !== '' && $existing['material_cost'] === null) {
                    $existing['material_cost'] = (float) $materialCost;
                }
                if ($processingCost !== '' && $existing['order_processing_charges'] === null) {
                    $existing['order_processing_charges'] = (float) $processingCost;
                }
                if ($shippingCost !== '' && $existing['shipping_cost'] === null) {
                    $existing['shipping_cost'] = (float) $shippingCost;
                }
                if ($remarks && empty($existing['remarks'])) {
                    $existing['remarks'] = $remarks;
                }
            }
        }

        fclose($handle);

        $updated = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($rowsByOrder as $orderNo => $data) {
            $order = \App\Models\Order::where('order_number', $orderNo)
                ->orWhere('platform_order_id', $orderNo)
                ->first();

            if (!$order) {
                $errors[] = "Order not found: {$orderNo}";
                $skipped++;
                continue;
            }

            // Skip if nothing to update
            $hasData = collect($data)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
            if (!$hasData) {
                $skipped++;
                continue;
            }

            try {
                // ✅ Reuse existing service method
                $this->salesService->updateOrderManagement($order, $data);

                // shipping_cost is not in updateOrderManagement – update separately if needed
                if ($data['shipping_cost'] !== null) {
                    $order->update(['shipping_cost' => $data['shipping_cost']]);
                }

                $updated++;
            } catch (\Throwable $e) {
                $errors[] = "Failed {$orderNo}: " . $e->getMessage();
                $skipped++;
            }
        }

        $message = "CSV processed. Orders updated: {$updated}, Skipped: {$skipped}";
        if ($errors) {
            $message .= ' | ' . implode(' | ', array_slice($errors, 0, 8));
        }

        return back()->with($updated > 0 ? 'success' : 'error', $message);
    }
}
