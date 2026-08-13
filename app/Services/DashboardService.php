<?php

namespace App\Services;

use App\Models\{Vendor, Product, Order, Shipment, Inventory, Consignment, FinanceReceivable, VendorPayout, WarehouseCharge, ProductCatalogue, SalesChannel, Category, Grn, LiveSheet, Warehouse};
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
            //   $orderQuery->byCompanyCode($companyCode);
            $shipmentQuery->byCompanyCode($companyCode);
        }


        // ==================== OPTIMIZED SALES CALCULATION ====================
        $currentMonth = now()->month;
        $currentYear  = now()->year;

        // Get monthly and YTD sales for all companies in one go
        $salesData = Order::select('company_code')
            ->selectRaw('SUM(CASE WHEN MONTH(order_date) = ? THEN total_amount ELSE 0 END) as monthly_sales', [$currentMonth])
            ->selectRaw('SUM(CASE WHEN YEAR(order_date) = ? THEN total_amount ELSE 0 END) as ytd_sales', [$currentYear])
            ->groupBy('company_code')
            ->get()
            ->keyBy('company_code');

        // Extract values with fallback
        $usMonthly = $salesData['2100']->monthly_sales ?? 0;
        $euMonthly = $salesData['2200']->monthly_sales ?? 0;
        $ukMonthly = $salesData['2400']->monthly_sales ?? 0;

        $usYtd = $salesData['2100']->ytd_sales ?? 0;
        $euYtd = $salesData['2200']->ytd_sales ?? 0;
        $ukYtd = $salesData['2400']->ytd_sales ?? 0;


        // $usSales =  (clone $orderQuery)->byCompanyCode('2100')->whereMonth('order_date', now()->month)->sum('total_amount');
        // $euSales =   (clone $orderQuery)->byCompanyCode('2200')->whereMonth('order_date', now()->month)->sum('total_amount');
        // $ukSales =   (clone $orderQuery)->byCompanyCode('2400')->whereMonth('order_date', now()->month)->sum('total_amount');

        // $usYtdSales =  (clone $orderQuery)->byCompanyCode('2100')->whereYear('order_date', now()->year)->sum('total_amount');
        // $euYtdSales =   (clone $orderQuery)->byCompanyCode('2200')->whereYear('order_date', now()->year)->sum('total_amount');
        // $ukYtdSales =   (clone $orderQuery)->byCompanyCode('2400')->whereYear('order_date', now()->year)->sum('total_amount');



        return [
            'kpis' => [
                'total_vendors' => (clone $vendorQuery)->count(),
                'active_vendors' => (clone $vendorQuery)->active()->count(),
                'total_skus' => (clone $productQuery)->count(),
                'listed_skus' => (clone $productQuery)->listed()->count(),

                // 'inventory_value' => Inventory::when($companyCode, fn($q) => $q->byCompanyCode($companyCode))
                //     ->join('products', 'inventory.product_id', '=', 'products.id')
                //     ->sum(DB::raw('inventory.quantity * products.vendor_price')),

                'inventory_value' => Inventory::when($companyCode, function ($q) use ($companyCode) {
                    $q->where('inventory.company_code', $companyCode);
                })
                    ->join('products', 'inventory.product_id', '=', 'products.id')
                    ->sum(DB::raw('inventory.quantity * products.vendor_price')),

                'sales' => [
                    'us_sales' => ['monthly' => $usMonthly, 'ytd' => $usYtd],
                    'eu_sales' => ['monthly' => $euMonthly, 'ytd' => $euYtd],
                    'uk_sales' => ['monthly' => $ukMonthly, 'ytd' => $ukYtd],
                ],

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
        $activeCode = session('active_company');
     
        return [
            'kpis' => [
                'products_approved' => Product::where('vendor_id', $vendorId)->where('company_code', $activeCode)->whereIn('status', ['approved', 'listed'])->count(),
                'inventory_available' => Inventory::whereHas('product', fn($q) => $q->where('vendor_id', $vendorId))->where('company_code', $activeCode)->sum('available_quantity'),
                'units_sold' => Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendorId))
                    ->where('company_code', $activeCode)
                    ->whereMonth('order_date', now()->month)->withSum('items', 'quantity')->get()->sum('items_sum_quantity'),
                'monthly_sales' => Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendorId))
                    ->where('company_code', $activeCode)
                    ->whereMonth('order_date', now()->month)->sum('total_amount'),
                'pending_payout' => VendorPayout::where('vendor_id', $vendorId)->where('status', 'approved')->sum('net_payout'),
                'chargebacks' => \App\Models\Chargeback::where('vendor_id', $vendorId)
                    ->whereIn('status', ['raised', 'pending_confirmation', 'confirmed'])->sum('amount'),
            ],
            'product_status' => [
                'submitted' => Product::where('vendor_id', $vendorId)->where('company_code', $activeCode)->where('status', 'submitted')->count(),
                'approved' => Product::where('vendor_id', $vendorId)->where('company_code', $activeCode)->where('status', 'approved')->count(),
                'rejected' => Product::where('vendor_id', $vendorId)->where('company_code', $activeCode)->where('status', 'rejected')->count(),
                'listed' => Product::where('vendor_id', $vendorId)->where('company_code', $activeCode)->where('status', 'listed')->count(),
            ],
            'consignments' => Consignment::where('vendor_id', $vendorId)->where('company_code', $activeCode)->orderBy('created_at', 'desc')->take(10)->get(),
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
    public function getLogisticsDashboard(): array
    {
        $activeCode = session('active_company');

        return [
            'kpis' => [
                'containers_planned' => Shipment::whereIn('status', ['planning', 'shipment'])->when($activeCode, fn($q) => $q->byCompanyCode($activeCode))->count(),
                'in_transit' => Shipment::inTransit()->when($activeCode, fn($q) => $q->byCompanyCode($activeCode))->count(),
                'grn_pending' => Shipment::where('status', 'grn_pending')->when($activeCode, fn($q) => $q->byCompanyCode($activeCode))->count(),
                'received_this_month' => Grn::where('company_code', $activeCode)->whereMonth('receipt_date', now()->month)->count(),
            ],
            'container_planning' => [
                'live_sheets_ready' => LiveSheet::locked()->with('consignment')->where('company_code', $activeCode)->get(),
                'fcl_count' => Shipment::where('shipment_type', 'FCL')->when($activeCode, fn($q) => $q->byCompanyCode($activeCode))->count(),
                'lcl_count' => Shipment::where('shipment_type', 'LCL')->when($activeCode, fn($q) => $q->byCompanyCode($activeCode))->count(),
            ],
            'shipments' => Shipment::with('consignments.vendor')->where('company_code', $activeCode)->latest()->take(20)->get(),
            'warehouse_charges' => [
                'storage' => WarehouseCharge::where('charge_type', 'storage')->whereMonth('created_at', now()->month)->sum('calculated_amount'),
                'variance' => WarehouseCharge::whereMonth('created_at', now()->month)->sum('variance'),
            ],
        ];
    }

    // ─── CATALOGUING DASHBOARD ────────────────────────────────────
    public function getCataloguingDashboard(?string $companyCode = null): array
    {
        $companyCode = $companyCode ?? session('active_company');
        $channels = SalesChannel::active()
            ->when($companyCode, fn($q) => $q->whereJsonContains('company_codes', $companyCode))
            ->orderBy('name')->get();

        $totalSkus = Product::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->count();

        $listingsByPlatform = [];
        foreach ($channels as $channel) {
            $baseQ = ProductCatalogue::where('sales_channel_id', $channel->id)
                ->when($companyCode, fn($q) => $q->where('company_code', $companyCode));

            $listed = (clone $baseQ)->where('listing_status', 'listed')->count();
            $pending = (clone $baseQ)->where('listing_status', 'pending')->count();
            $unlisted = (clone $baseQ)->where('listing_status', 'unlisted')->count();

            $listingsByPlatform[$channel->slug] = [
                'id'        => $channel->id,
                'name'      => $channel->name,
                'type'      => $channel->type,
                'listed'    => $listed,
                'pending'   => $pending,
                'unlisted'  => $unlisted,
                'not_listed' => max(0, $totalSkus - $listed - $pending - $unlisted),
                'coverage'  => $totalSkus > 0 ? round(($listed / $totalSkus) * 100, 1) : 0,
            ];
        }

        $totalListed = ProductCatalogue::when($companyCode, fn($q) => $q->where('company_code', $companyCode))
            ->where('listing_status', 'listed')->count();
        $totalPending = ProductCatalogue::when($companyCode, fn($q) => $q->where('company_code', $companyCode))
            ->where('listing_status', 'pending')->count();

        // SKUs not listed on any channel
        $notListedAnywhere = Product::when($companyCode, fn($q) => $q->where('company_code', $companyCode))
            ->whereDoesntHave('catalogues', fn($q) => $q->where('listing_status', 'listed'))
            ->count();

        // Recently listed (last 7 days)
        $recentlyListed = ProductCatalogue::when($companyCode, fn($q) => $q->where('company_code', $companyCode))
            ->where('listing_status', 'listed')
            ->where('listed_at', '>=', now()->subDays(7))
            ->count();

        // Top vendors by listed count
        $topVendors = Product::select('vendor_id', \DB::raw('COUNT(*) as total_products'))
            ->when($companyCode, fn($q) => $q->where('company_code', $companyCode))
            ->whereHas('catalogues', fn($q) => $q->where('listing_status', 'listed'))
            ->groupBy('vendor_id')
            ->orderByDesc('total_products')
            ->limit(5)
            ->with('vendor:id,company_name')
            ->get()
            ->map(fn($p) => [
                'vendor_name' => $p->vendor->company_name ?? '—',
                'count'       => $p->total_products,
            ]);

        return [
            'kpis' => [
                'total_skus'        => $totalSkus,
                'listed'            => $totalListed,
                'pending'           => $totalPending,
                'not_listed'        => $notListedAnywhere,
                'recently_listed'   => $recentlyListed,
                'overall_coverage' => ($totalSkus > 0 && $channels->count() > 0)
                    ? round(($totalListed / ($totalSkus * $channels->count())) * 100, 1)
                    : 0,
            ],
            'by_platform'  => $listingsByPlatform,
            'top_vendors'  => $topVendors,
            'channel_count' => $channels->count(),
        ];
    }

    // ─── SALES DASHBOARD ──────────────────────────────────────────
    public function getSalesDashboard(?string $companyCode = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? now()->startOfMonth()->toDateString();
        $dateTo = $dateTo ?? now()->toDateString();

        $orderQuery = Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode));

        return [
            'kpis' => [
                'daily_sales'      => (clone $orderQuery)->whereDate('order_date', today())->sum('total_amount'),
                'period_sales'     => (clone $orderQuery)->whereBetween('order_date', [$dateFrom, $dateTo])->sum('total_amount'),
                'orders_received'  => (clone $orderQuery)->whereBetween('order_date', [$dateFrom, $dateTo])->count(),
                'pending_shipment' => (clone $orderQuery)->where(function ($q) {
                    $q->whereNull('shipment_status')->orWhere('shipment_status', 'pending');
                })->count(),
                'orders_shipped'   => (clone $orderQuery)->where('shipment_status', 'shipped')
                    ->whereBetween('order_date', [$dateFrom, $dateTo])->count(),
            ],
            'by_platform' => Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode))
                ->select('sales_channel_id', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as count'))
                ->whereBetween('order_date', [$dateFrom, $dateTo])
                ->groupBy('sales_channel_id')
                ->with('salesChannel')
                ->get(),
            'pending_shipment' => (clone $orderQuery)->where(function ($q) {
                $q->whereNull('shipment_status')->orWhere('shipment_status', 'pending');
            })->count(),
            'pending_tracking' => (clone $orderQuery)->where('shipment_status', 'shipped')
                ->where(function ($q) {
                    $q->whereNull('tracking_id')->orWhere('tracking_id', '');
                })->count(),
            'recent_orders' => (clone $orderQuery)->with('salesChannel')
                ->latest('order_date')->limit(10)->get(),
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ];
    }
    public function getSalesDashboard1(?string $companyCode = null): array
    {
        $orderQuery = Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode));

        return [
            'kpis' => [
                'daily_sales'      => (clone $orderQuery)->whereDate('order_date', today())->sum('total_amount'),
                'monthly_sales'    => (clone $orderQuery)->whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->sum('total_amount'),
                'orders_received'  => (clone $orderQuery)->whereMonth('order_date', now()->month)->whereYear('order_date', now()->year)->count(),
                'pending_shipment' => (clone $orderQuery)->where(function ($q) {
                    $q->whereNull('shipment_status')->orWhere('shipment_status', 'pending');
                })->count(),
                'orders_shipped'   => (clone $orderQuery)->where('shipment_status', 'shipped')->count(),
            ],
            'by_platform' => Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode))
                ->select('sales_channel_id', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as count'))
                ->whereMonth('order_date', now()->month)
                ->whereYear('order_date', now()->year)
                ->groupBy('sales_channel_id')
                ->with('salesChannel')
                ->get(),
            'pending_tracking' => (clone $orderQuery)->where('shipment_status', 'shipped')
                ->where(function ($q) {
                    $q->whereNull('tracking_id')->orWhere('tracking_id', '');
                })->count(),
            'recent_orders' => (clone $orderQuery)->with('salesChannel')
                ->latest('order_date')
                ->limit(10)
                ->get(),
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
    public function getHodDashboard(?string $companyCode = null): array
    {
        return [
            'kpis' => [
                'total_revenue' => Order::when($companyCode, fn($q) => $q->where('company_code', $companyCode))->whereYear('order_date', now()->year)->sum('total_amount'),
                'gross_margin' => $this->calculateGrossMargin(),
                'inventory_value' => Inventory::when($companyCode, fn($q) => $q->where('inventory.company_code', $companyCode))->join('products', 'inventory.product_id', '=', 'products.id')
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
            ->select(
                'vendors.company_name',
                'vendors.vendor_code',
                DB::raw('COALESCE(SUM(order_items.total_price), 0) as revenue'),
                DB::raw('COALESCE(SUM(order_items.quantity), 0) as units_sold')
            )
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
