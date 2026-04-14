<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\{Order, OrderItem, SalesChannel, Product};
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

        $data['order_stats'] = [
            'total_orders'    => Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->count(),
            'pending_shipment' => Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->where('shipment_status', 'pending')->count(),
            'shipped'         => Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->where('shipment_status', 'shipped')->count(),
            'delivered'       => Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->where('shipment_status', 'delivered')->count(),
            'total_revenue'   => Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->sum('total_amount'),
            'this_month'      => Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('total_amount'),
        ];

        $data['by_channel'] = SalesChannel::active()->get()->map(fn($ch) => [
            'channel' => $ch,
            'orders'  => Order::where('sales_channel_id', $ch->id)->when($companyCode, fn($q) => $q->where('company_code', $companyCode))->count(),
            'revenue' => Order::where('sales_channel_id', $ch->id)->when($companyCode, fn($q) => $q->where('company_code', $companyCode))->sum('total_amount'),
        ]);

        $data['recent_orders'] = Order::with('salesChannel')
            ->when($companyCode, fn($q) => $q->where('company_code', $companyCode))
            ->latest('order_date')->take(5)->get();

        return view('sales.dashboard', compact('data', 'companyCode'));
    }

    // =====================================================================
    //  ORDERS — List, Detail, Search
    // =====================================================================

    public function orders(Request $request)
    {
        $orders = Order::with('salesChannel', 'items.product', 'uploader')
            ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
            ->when($request->sales_channel_id, fn($q, $v) => $q->where('sales_channel_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->shipment_status, fn($q, $v) => $q->where('shipment_status', $v))
            ->when($request->payment_status, fn($q, $v) => $q->where('payment_status', $v))
            ->when($request->search, fn($q, $v) => $q->where(function ($sq) use ($v) {
                $sq->where('order_number', 'like', "%{$v}%")
                   ->orWhere('platform_order_id', 'like', "%{$v}%")
                   ->orWhere('customer_name', 'like', "%{$v}%")
                   ->orWhere('tracking_id', 'like', "%{$v}%");
            }))
            ->when($request->date_from, fn($q, $v) => $q->whereDate('order_date', '>=', $v))
            ->when($request->date_to, fn($q, $v) => $q->whereDate('order_date', '<=', $v))
            ->latest('order_date')->paginate(30)->appends($request->query());

        $channels = SalesChannel::active()->get();

        $stats = [
            'total'    => Order::when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->count(),
            'pending'  => Order::where('shipment_status', 'pending')->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->count(),
            'shipped'  => Order::where('shipment_status', 'shipped')->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->count(),
            'delivered' => Order::where('shipment_status', 'delivered')->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))->count(),
        ];

        return view('sales.orders.index', compact('orders', 'channels', 'stats'));
    }

    public function showOrder(Order $order)
    {
        $order->load('salesChannel', 'items.product.vendor', 'customer', 'receivable', 'chargebacks', 'uploader');
        return view('sales.orders.show', compact('order'));
    }

    // =====================================================================
    //  SALES UPLOAD — Template download, CSV/manual entry
    // =====================================================================

    public function uploadSales()
    {
        $channels = SalesChannel::active()->orderBy('name')->get();
        return view('sales.upload', compact('channels'));
    }

    public function storeSales(Request $request)
    {
        $request->validate([
            'company_code'              => 'required|in:2000,2100,2200',
            'orders'                    => 'required|array|min:1',
            'orders.*.sales_channel'    => 'required|string',
            'orders.*.platform_order_id' => 'required|string',
            'orders.*.order_date'       => 'required|date',
            'orders.*.total_amount'     => 'required|numeric|min:0',
        ]);

        $result = $this->salesService->uploadSalesData($request->orders, $request->company_code, auth()->user());

        $createdCount = count($result['created']);
        $errorCount = count($result['errors']);
        $msg = "{$createdCount} orders created.";
        if ($errorCount > 0) {
            $msg .= " {$errorCount} errors.";
        }

        return redirect()->route('sales.orders')
            ->with('success', $msg)
            ->with('upload_errors', $result['errors']);
    }

    /**
     * Download upload template as CSV
     */
    public function downloadTemplate()
    {
        $csv = "sales_channel,platform_order_id,order_date,customer_name,customer_email,customer_phone,shipping_address,shipping_city,shipping_state,shipping_country,shipping_pincode,sku,quantity,unit_price,subtotal,shipping,tax,discount,total_amount,currency\n";
        $csv .= "Amazon,AMZ-12345,2026-03-01,John Doe,john@example.com,+1234567890,123 Main St,New York,NY,US,10001,SKU-001,2,29.99,59.98,5.00,3.60,0.00,68.58,USD\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sales-upload-template.csv"',
        ]);
    }

    // =====================================================================
    //  ORDER TRACKING — Update tracking per order + bulk
    // =====================================================================

    public function updateTracking(Request $request, Order $order)
    {
        $request->validate([
            'tracking_id'       => 'required|string|max:100',
            'tracking_url'      => 'nullable|url|max:500',
            'shipping_provider' => 'nullable|string|max:100',
        ]);

        $this->salesService->updateTracking($order, $request->tracking_id, $request->tracking_url, $request->shipping_provider);
        return back()->with('success', "Tracking updated for order {$order->order_number}.");
    }

    /**
     * Bulk update tracking for multiple orders
     */
    public function bulkUpdateTracking(Request $request)
    {
        $request->validate([
            'tracking'                     => 'required|array|min:1',
            'tracking.*.order_id'          => 'required|exists:orders,id',
            'tracking.*.tracking_id'       => 'required|string',
            'tracking.*.shipping_provider' => 'nullable|string',
            'tracking.*.tracking_url'      => 'nullable|string',
        ]);

        $updated = 0;
        foreach ($request->tracking as $data) {
            $order = Order::find($data['order_id']);
            if ($order && !empty($data['tracking_id'])) {
                $this->salesService->updateTracking($order, $data['tracking_id'], $data['tracking_url'] ?? null, $data['shipping_provider'] ?? null);
                $updated++;
            }
        }

        return back()->with('success', "{$updated} order(s) tracking updated.");
    }
}
