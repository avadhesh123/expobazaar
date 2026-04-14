<?php

namespace App\Services;

use App\Models\{Vendor, Product, Order, Shipment, Inventory, Consignment, FinanceReceivable, VendorPayout, WarehouseCharge, ProductCatalogue, SalesChannel, Category, Grn, LiveSheet};
use Illuminate\Support\Facades\DB;

class DashboardService
{
    // ─── ADMIN DASHBOARD ──────────────────────────────────────────
    public function getAdminDashboard(?string $companyCode = null): array
    {
        $vendorQuery = Vendor::query();
        $productQuery = Product::query();
        $orderQuery = Order::query();
        $shipmentQuery = Shipment::query();

        if ($companyCode) {
            $vendorQuery->byCompanyCode($companyCode);
            $productQuery->byCompanyCode($companyCode);
            $orderQuery->byCompanyCode($companyCode);
            $shipmentQuery->byCompanyCode($companyCode);
        }

        return [
            'kpis' => [
                'total_vendors' => (clone $vendorQuery)->count(),
                'active_vendors' => (clone $vendorQuery)->active()->count(),
                'total_skus' => (clone $productQuery)->count(),
                'listed_skus' => (clone $productQuery)->listed()->count(),
                'shipments_in_transit' => (clone $shipmentQuery)->inTransit()->count(),
                'inventory_value' => Inventory::when($companyCode, fn($q) => $q->byCompanyCode($companyCode))
                    ->join('products', 'inventory.product_id', '=', 'products.id')
                    ->sum(DB::raw('inventory.quantity * products.vendor_price')),
                'monthly_sales' => (clone $orderQuery)->whereMonth('order_date', now()->month)->sum('total_amount'),
                'ytd_sales' => (clone $orderQuery)->whereYear('order_date', now()->year)->sum('total_amount'),
                'pending_payouts' => VendorPayout::when($companyCode, fn($q) => $q->where('company_code', $companyCode))
                    ->pending()->sum('net_payout'),
            ],
            'vendor_activity' => [
                'onboarded_this_month' => Vendor::whereMonth('created_at', now()->month)->count(),
                'pending_kyc' => Vendor::pendingKyc()->count(),
                'pending_contract' => Vendor::pendingContract()->count(),
                'pending_approval' => Vendor::pendingApproval()->count(),
            ],
            'operations' => [
                'consignments_in_production' => Consignment::where('status', 'in_production')->count(),
                'containers_planned' => Shipment::where('status', 'planning')->count(),
                'shipments_in_transit' => Shipment::inTransit()->count(),
            ],
            'inventory_ageing' => $this->getInventoryAgeing($companyCode),
            'sales_by_platform' => $this->getSalesByPlatform($companyCode),
            'sales_by_country' => $this->getSalesByCountry(),
        ];
    }

    // ─── VENDOR DASHBOARD ─────────────────────────────────────────
    public function getVendorDashboard(int $vendorId): array
    {
        $vendor = Vendor::findOrFail($vendorId);

        return [
            'kpis' => [
                'products_approved' => Product::where('vendor_id', $vendorId)->whereIn('status', ['approved', 'listed'])->count(),
                'inventory_available' => Inventory::whereHas('product', fn($q) => $q->where('vendor_id', $vendorId))->sum('available_quantity'),
                'units_sold' => Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendorId))
                    ->whereMonth('order_date', now()->month)->withSum('items', 'quantity')->get()->sum('items_sum_quantity'),
                'monthly_sales' => Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendorId))
                    ->whereMonth('order_date', now()->month)->sum('total_amount'),
                'pending_payout' => VendorPayout::where('vendor_id', $vendorId)->pending()->sum('net_payout'),
                'chargebacks' => \App\Models\Chargeback::where('vendor_id', $vendorId)
                    ->whereIn('status', ['raised', 'pending_confirmation', 'confirmed'])->sum('amount'),
            ],
            'product_status' => [
                'submitted' => Product::where('vendor_id', $vendorId)->where('status', 'submitted')->count(),
                'approved' => Product::where('vendor_id', $vendorId)->where('status', 'approved')->count(),
                'rejected' => Product::where('vendor_id', $vendorId)->where('status', 'rejected')->count(),
                'listed' => Product::where('vendor_id', $vendorId)->where('status', 'listed')->count(),
            ],
            'consignments' => Consignment::where('vendor_id', $vendorId)->orderBy('created_at', 'desc')->take(10)->get(),
            'inventory_ageing' => $this->getVendorInventoryAgeing($vendorId),
            'charges' => [
                'storage' => WarehouseCharge::where('vendor_id', $vendorId)->where('charge_type', 'storage')
                    ->whereMonth('created_at', now()->month)->sum('calculated_amount'),
                'inward' => WarehouseCharge::where('vendor_id', $vendorId)->where('charge_type', 'inward')
                    ->whereMonth('created_at', now()->month)->sum('calculated_amount'),
                'logistics' => WarehouseCharge::where('vendor_id', $vendorId)
                    ->whereIn('charge_type', ['pick_pack', 'consumable', 'last_mile'])
                    ->whereMonth('created_at', now()->month)->sum('calculated_amount'),
            ],
            'payouts' => VendorPayout::where('vendor_id', $vendorId)->orderBy('payout_year', 'desc')->orderBy('payout_month', 'desc')->take(12)->get(),
        ];
    }

    // ─── SOURCING DASHBOARD ───────────────────────────────────────
    public function getSourcingDashboard(): array
    {
        return [
            'kpis' => [
                'vendors_this_month' => Vendor::whereMonth('created_at', now()->month)->count(),
                'offer_sheets_pending' => \App\Models\OfferSheet::where('status', 'submitted')->count(),
                'live_sheets_pending' => LiveSheet::where('status', 'submitted')->count(),
                'products_selected' => Product::where('status', 'selected')->count(),
            ],
            'vendor_onboarding' => [
                'pending_approval' => Vendor::pendingApproval()->count(),
                'pending_kyc' => Vendor::pendingKyc()->count(),
            ],
            'offer_sheets' => \App\Models\OfferSheet::whereIn('status', ['submitted', 'under_review'])->with('vendor')->latest()->take(20)->get(),
            'consignment_pipeline' => Consignment::whereIn('status', ['created', 'live_sheet_pending', 'live_sheet_submitted'])->with('vendor')->latest()->take(20)->get(),
        ];
    }

    // ─── LOGISTICS DASHBOARD ──────────────────────────────────────
    public function getLogisticsDashboard(?string $companyCode = null): array
    {
        return [
            'kpis' => [
                'containers_planned' => Shipment::where('status', 'planning')->when($companyCode, fn($q) => $q->byCompanyCode($companyCode))->count(),
                'in_transit' => Shipment::inTransit()->when($companyCode, fn($q) => $q->byCompanyCode($companyCode))->count(),
                'grn_pending' => Shipment::where('status', 'grn_pending')->when($companyCode, fn($q) => $q->byCompanyCode($companyCode))->count(),
                'received_this_month' => Grn::whereMonth('receipt_date', now()->month)->count(),
            ],
            'container_planning' => [
                'live_sheets_ready' => LiveSheet::locked()->with('consignment')->get(),
                'fcl_count' => Shipment::where('shipment_type', 'FCL')->when($companyCode, fn($q) => $q->byCompanyCode($companyCode))->count(),
                'lcl_count' => Shipment::where('shipment_type', 'LCL')->when($companyCode, fn($q) => $q->byCompanyCode($companyCode))->count(),
            ],
            'shipments' => Shipment::with('consignments.vendor')->when($companyCode, fn($q) => $q->byCompanyCode($companyCode))->latest()->take(20)->get(),
            'warehouse_charges' => [
                'storage' => WarehouseCharge::where('charge_type', 'storage')->whereMonth('created_at', now()->month)->sum('calculated_amount'),
                'variance' => WarehouseCharge::whereMonth('created_at', now()->month)->sum('variance'),
            ],
        ];
    }

    // ─── CATALOGUING DASHBOARD ────────────────────────────────────
    public function getCataloguingDashboard(?string $companyCode = null): array
    {
        $channels = SalesChannel::active()->get();
        $listingsByPlatform = [];

        foreach ($channels as $channel) {
            $listingsByPlatform[$channel->slug] = [
                'name' => $channel->name,
                'listed' => ProductCatalogue::where('sales_channel_id', $channel->id)
                    ->when($companyCode, fn($q) => $q->where('company_code', $companyCode))
                    ->listed()->count(),
                'pending' => ProductCatalogue::where('sales_channel_id', $channel->id)
                    ->when($companyCode, fn($q) => $q->where('company_code', $companyCode))
                    ->pending()->count(),
            ];
        }

        return [
            'kpis' => [
                'total_skus' => Product::when($companyCode, fn($q) => $q->byCompanyCode($companyCode))->count(),
                'listed' => ProductCatalogue::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->listed()->count(),
                'pending' => ProductCatalogue::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->pending()->count(),
            ],
            'by_platform' => $listingsByPlatform,
        ];
    }

    // ─── SALES DASHBOARD ──────────────────────────────────────────
    public function getSalesDashboard(?string $companyCode = null): array
    {
        $orderQuery = Order::when($companyCode, fn($q) => $q->byCompanyCode($companyCode));

        return [
            'kpis' => [
                'daily_sales' => (clone $orderQuery)->whereDate('order_date', today())->sum('total_amount'),
                'monthly_sales' => (clone $orderQuery)->whereMonth('order_date', now()->month)->sum('total_amount'),
                'orders_received' => (clone $orderQuery)->whereMonth('order_date', now()->month)->count(),
                'orders_shipped' => (clone $orderQuery)->where('shipment_status', 'shipped')->whereMonth('order_date', now()->month)->count(),
            ],
            'by_platform' => Order::when($companyCode, fn($q) => $q->byCompanyCode($companyCode))
                ->select('sales_channel_id', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as count'))
                ->whereMonth('order_date', now()->month)
                ->groupBy('sales_channel_id')->with('salesChannel')->get(),
            'pending_shipment' => (clone $orderQuery)->pendingShipment()->count(),
            'pending_tracking' => (clone $orderQuery)->where('shipment_status', 'shipped')->whereNull('tracking_id')->count(),
        ];
    }

    // ─── FINANCE DASHBOARD ────────────────────────────────────────
    public function getFinanceDashboard(?string $companyCode = null): array
    {
        return [
            'kpis' => [
                'receivables' => FinanceReceivable::when($companyCode, fn($q) => $q->byCompanyCode($companyCode))->unpaid()->sum('net_receivable'),
                'payouts_pending' => VendorPayout::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->pending()->sum('net_payout'),
                'platform_deductions' => FinanceReceivable::when($companyCode, fn($q) => $q->byCompanyCode($companyCode))
                    ->whereMonth('created_at', now()->month)
                    ->sum(DB::raw('platform_commission + platform_fee')),
                'chargebacks' => \App\Models\Chargeback::when($companyCode, fn($q) => $q->where('company_code', $companyCode))
                    ->whereMonth('created_at', now()->month)->sum('amount'),
            ],
            'unpaid_by_platform' => FinanceReceivable::when($companyCode, fn($q) => $q->byCompanyCode($companyCode))
                ->unpaid()
                ->select('sales_channel_id', DB::raw('SUM(net_receivable) as total'), DB::raw('COUNT(*) as count'))
                ->groupBy('sales_channel_id')->with('salesChannel')->get(),
            'vendor_settlements' => VendorPayout::when($companyCode, fn($q) => $q->where('company_code', $companyCode))
                ->byMonth(now()->month, now()->year)->with('vendor')->get(),
        ];
    }

    // ─── HOD / MANAGEMENT DASHBOARD ──────────────────────────────
    public function getHodDashboard(): array
    {
        return [
            'kpis' => [
                'total_revenue' => Order::whereYear('order_date', now()->year)->sum('total_amount'),
                'gross_margin' => $this->calculateGrossMargin(),
                'inventory_value' => Inventory::join('products', 'inventory.product_id', '=', 'products.id')
                    ->sum(DB::raw('inventory.quantity * products.vendor_price')),
                'top_vendors' => $this->getTopVendors(5),
                'top_platforms' => $this->getTopPlatforms(5),
                'top_categories' => $this->getTopCategories(5),
            ],
            'sales_performance' => [
                'by_platform' => $this->getSalesByPlatform(),
                'by_country' => $this->getSalesByCountry(),
                'by_category' => $this->getSalesByCategory(),
            ],
            'inventory_efficiency' => [
                'fast_moving' => $this->getFastMovingSkus(10),
                'dead_stock' => $this->getDeadStock(10),
                'ageing' => $this->getInventoryAgeing(),
            ],
            'vendor_performance' => $this->getVendorPerformance(10),
        ];
    }

    // ─── HELPER METHODS ──────────────────────────────────────────

    protected function getInventoryAgeing(?string $companyCode = null): array
    {
        $query = Inventory::when($companyCode, fn($q) => $q->byCompanyCode($companyCode));
        return [
            '0_30' => (clone $query)->where('received_date', '>=', now()->subDays(30))->sum('quantity'),
            '31_60' => (clone $query)->whereBetween('received_date', [now()->subDays(60), now()->subDays(30)])->sum('quantity'),
            '61_90' => (clone $query)->whereBetween('received_date', [now()->subDays(90), now()->subDays(60)])->sum('quantity'),
            '91_120' => (clone $query)->whereBetween('received_date', [now()->subDays(120), now()->subDays(90)])->sum('quantity'),
            '120_plus' => (clone $query)->where('received_date', '<', now()->subDays(120))->sum('quantity'),
        ];
    }

    protected function getVendorInventoryAgeing(int $vendorId): array
    {
        $query = Inventory::whereHas('product', fn($q) => $q->where('vendor_id', $vendorId));
        return [
            '0_30' => (clone $query)->where('received_date', '>=', now()->subDays(30))->sum('quantity'),
            '31_60' => (clone $query)->whereBetween('received_date', [now()->subDays(60), now()->subDays(30)])->sum('quantity'),
            '61_90' => (clone $query)->whereBetween('received_date', [now()->subDays(90), now()->subDays(60)])->sum('quantity'),
            '90_plus' => (clone $query)->where('received_date', '<', now()->subDays(90))->sum('quantity'),
        ];
    }

    protected function getSalesByPlatform(?string $companyCode = null): array
    {
        return Order::when($companyCode, fn($q) => $q->byCompanyCode($companyCode))
            ->select('sales_channel_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as orders'))
            ->whereYear('order_date', now()->year)
            ->groupBy('sales_channel_id')
            ->with('salesChannel')
            ->get()->toArray();
    }

    protected function getSalesByCountry(): array
    {
        return Order::select('company_code', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as orders'))
            ->whereYear('order_date', now()->year)
            ->groupBy('company_code')->get()->toArray();
    }

    protected function getSalesByCategory(): array
    {
        return DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('SUM(order_items.total_price) as revenue'))
            ->whereYear('orders.order_date', now()->year)
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->get()->toArray();
    }

    protected function getTopVendors(int $limit): array
    {
        return DB::table('order_items')
            ->join('vendors', 'order_items.vendor_id', '=', 'vendors.id')
            ->select('vendors.company_name', DB::raw('SUM(order_items.total_price) as revenue'))
            ->groupBy('vendors.company_name')
            ->orderByDesc('revenue')
            ->limit($limit)->get()->toArray();
    }

    protected function getTopPlatforms(int $limit): array
    {
        return Order::select('sales_channel_id', DB::raw('SUM(total_amount) as revenue'))
            ->whereYear('order_date', now()->year)
            ->groupBy('sales_channel_id')
            ->with('salesChannel')
            ->orderByDesc('revenue')
            ->limit($limit)->get()->toArray();
    }

    protected function getTopCategories(int $limit): array
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('SUM(order_items.total_price) as revenue'))
            ->groupBy('categories.name')
            ->orderByDesc('revenue')
            ->limit($limit)->get()->toArray();
    }

    protected function getFastMovingSkus(int $limit): array
    {
        return DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.sku', 'products.name', DB::raw('SUM(order_items.quantity) as sold'))
            ->whereExists(fn($q) => $q->selectRaw(1)->from('orders')->whereColumn('orders.id', 'order_items.order_id')->where('orders.order_date', '>=', now()->subDays(30)))
            ->groupBy('products.sku', 'products.name')
            ->orderByDesc('sold')
            ->limit($limit)->get()->toArray();
    }

    protected function getDeadStock(int $limit): array
    {
        return Product::where('stock_quantity', '>', 0)
            ->whereDoesntHave('orderItems', fn($q) => $q->whereHas('order', fn($q2) => $q2->where('order_date', '>=', now()->subDays(90))))
            ->select('sku', 'name', 'stock_quantity')
            ->limit($limit)->get()->toArray();
    }

    protected function getVendorPerformance(int $limit): array
    {
        return DB::table('vendors')
            ->leftJoin('order_items', 'vendors.id', '=', 'order_items.vendor_id')
            ->select('vendors.company_name', 'vendors.vendor_code',
                DB::raw('COALESCE(SUM(order_items.total_price), 0) as revenue'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as units_sold'))
            ->where('vendors.status', 'active')
            ->groupBy('vendors.id', 'vendors.company_name', 'vendors.vendor_code')
            ->orderByDesc('revenue')
            ->limit($limit)->get()->toArray();
    }

    protected function calculateGrossMargin(): float
    {
        $revenue = Order::whereYear('order_date', now()->year)->sum('total_amount');
        $cost = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereYear('orders.order_date', now()->year)
            ->sum(DB::raw('order_items.quantity * products.vendor_price'));

        return $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 2) : 0;
    }
}
