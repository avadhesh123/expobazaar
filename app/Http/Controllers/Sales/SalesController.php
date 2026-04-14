<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\{Order, SalesChannel};
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
            ->when($request->search, fn($q, $v) => $q->where(function ($s) use ($v) {
                $s->where('order_number', 'like', "%{$v}%")
                    ->orWhere('platform_order_id', 'like', "%{$v}%");
            }))
            ->latest('order_date')->paginate(30)->withQueryString();

        $channels = SalesChannel::active()->orderBy('name')->get();

        // KPI stats for the page
        try {
            $baseQuery = Order::query()
                ->when($request->company_code, fn($q, $v) => $q->where('company_code', $v))
                ->when($request->sales_channel_id, fn($q, $v) => $q->where('sales_channel_id', $v));

            $stats = [
                'total_orders'    => (clone $baseQuery)->count(),
                'total_revenue'   => (float) (clone $baseQuery)->sum('total_amount'),
                'pending_orders'  => (clone $baseQuery)->whereIn('status', ['pending', 'processing', 'new'])->count(),
                'today_orders'    => (clone $baseQuery)->whereDate('order_date', today())->count(),
                'today_revenue'   => (float) (clone $baseQuery)->whereDate('order_date', today())->sum('total_amount'),
                'shipped_orders'  => (clone $baseQuery)->whereIn('status', ['shipped', 'delivered', 'completed'])->count(),
                'cancelled_orders' => (clone $baseQuery)->whereIn('status', ['cancelled', 'refunded'])->count(),
                'avg_order_value' => (float) (clone $baseQuery)->avg('total_amount') ?: 0,
            ];
        } catch (\Exception $e) {
            \Log::warning('Sales orders stats failed: ' . $e->getMessage());
            $stats = [
                'total_orders'     => 0,
                'total_revenue'    => 0,
                'pending_orders'   => 0,
                'today_orders'     => 0,
                'today_revenue'    => 0,
                'shipped_orders'   => 0,
                'cancelled_orders' => 0,
                'avg_order_value'  => 0,
            ];
        }

        return view('sales.orders.index', compact('orders', 'channels', 'stats'));
    }
    public function showOrder(Order $order)
    {
        $order->load('salesChannel', 'items.product.vendor', 'customer', 'receivable', 'chargebacks', 'uploader');
        return view('sales.orders.show', compact('order'));
    }
    public function uploadSales()
    {
        $channels = SalesChannel::active()->get();
        return view('sales.upload', compact('channels'));
    }

    public function storeSales(Request $request)
    {
        $request->validate([
            'company_code' => 'required|in:2000,2100,2200',
            'orders' => 'required|array|min:1',
        ]);
        $result = $this->salesService->uploadSalesData($request->orders, $request->company_code, auth()->user());
        $msg = count($result['created']) . ' orders created.';
        if (count($result['errors']) > 0) {
            $msg .= ' ' . count($result['errors']) . ' errors.';
        }
        return redirect()->route('sales.orders')->with('success', $msg)->with('upload_errors', $result['errors']);
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
    public function updateTracking(Request $request, Order $order)
    {
        $request->validate(['tracking_id' => 'required|string']);
        $this->salesService->updateTracking($order, $request->tracking_id, $request->tracking_url, $request->shipping_provider);
        return back()->with('success', 'Tracking updated.');
    }
}
