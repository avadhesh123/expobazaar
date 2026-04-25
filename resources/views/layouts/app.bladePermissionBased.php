<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Expo Bazaar SCM')</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --eb-primary: #1e3a5f;
            --eb-secondary: #e8a838;
            --eb-accent: #2d6a4f;
            --eb-light: #f8f9fa;
            --eb-dark: #0d1b2a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f1f5f9;
        }

        .sidebar {
            background: linear-gradient(180deg, #0d1b2a 0%, #1e3a5f 100%);
            min-height: 100vh;
            width: 260px;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 40;
            border-right: 1px solid rgba(255, 255, 255, .06);
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: .7rem 1.25rem;
            color: #8899aa;
            font-size: .82rem;
            transition: all .2s;
            border-left: 3px solid transparent;
            text-decoration: none;
        }

        .sidebar a:hover,
        .sidebar a.active {
            color: #fff;
            background: rgba(255, 255, 255, .07);
            border-left-color: #e8a838;
        }

        .sidebar a i {
            width: 1.5rem;
            text-align: center;
            margin-right: .7rem;
            font-size: .85rem;
        }

        .sidebar .logo {
            padding: 1.4rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .sidebar .logo h1 {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .sidebar .logo span {
            color: #e8a838;
        }

        .sidebar .section-title {
            padding: .9rem 1.25rem .25rem;
            font-size: .6rem;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #4a5e6f;
            font-weight: 700;
        }

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }

        .topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: .65rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 30;
            backdrop-filter: blur(8px);
        }

        .kpi-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.4rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
            border: 1px solid #e8ecf1;
            transition: transform .2s, box-shadow .2s;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .07);
        }

        .kpi-value {
            font-size: 1.7rem;
            font-weight: 800;
            color: #0d1b2a;
            letter-spacing: -.02em;
        }

        .kpi-label {
            font-size: .68rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .2rem;
            font-weight: 600;
        }

        .kpi-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table th {
            background: #f8fafc;
            padding: .7rem 1rem;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #64748b;
            font-weight: 700;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .data-table td {
            padding: .7rem 1rem;
            font-size: .83rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: .2rem .65rem;
            border-radius: 9999px;
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-gray {
            background: #f1f5f9;
            color: #475569;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .5rem 1.1rem;
            border-radius: 8px;
            font-size: .82rem;
            font-weight: 600;
            transition: all .2s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: #1e3a5f;
            color: #fff;
        }

        .btn-primary:hover {
            background: #152d4a;
        }

        .btn-secondary {
            background: #e8a838;
            color: #fff;
        }

        .btn-secondary:hover {
            background: #d69a30;
        }

        .btn-success {
            background: #2d6a4f;
            color: #fff;
        }

        .btn-danger {
            background: #dc2626;
            color: #fff;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-sm {
            padding: .3rem .65rem;
            font-size: .75rem;
        }

        .card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
            border: 1px solid #e8ecf1;
        }

        .card-header {
            padding: 1.1rem 1.4rem;
            border-bottom: 1px solid #e8ecf1;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h3 {
            font-size: .95rem;
            font-weight: 700;
            color: #0d1b2a;
        }

        .card-body {
            padding: 1.4rem;
        }

        .alert {
            padding: .85rem 1.1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            font-size: .85rem;
            font-weight: 500;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert i {
            margin-right: .5rem;
        }

        .notification-bell {
            position: relative;
        }

        .notification-bell .count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc2626;
            color: #fff;
            font-size: .55rem;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: .78rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: .3rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: .5rem .75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: .85rem;
            font-family: inherit;
            transition: border-color .2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1e3a5f;
            box-shadow: 0 0 0 3px rgba(30, 58, 95, .08);
        }

        .grid-kpi {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.25rem;
        }

        @media(max-width:1024px) {

            .grid-2,
            .grid-3 {
                grid-template-columns: 1fr;
            }

            .sidebar {
                width: 0;
                overflow: hidden;
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <aside class="sidebar">
        <div class="logo">
            <h1>Expo<span>Bazaar</span></h1>
            <p style="color:#4a5e6f;font-size:.65rem;margin-top:.15rem;">Supply Chain Management</p>
        </div>
        @auth
        @php
            $u = auth()->user();
            $dept = $u->department;
            $isVendor = $u->user_type === 'vendor';
            $isInternal = $u->user_type === 'internal' || $u->user_type === 'admin';
            $kycApproved = $isVendor && $u->vendor && $u->vendor->kyc_status === 'approved';

            $userPerms = collect();
            $hasRoles = false;
            try {
                $directPerms = $u->permissions()->pluck('name');
                $roleIds = $u->roles()->pluck('roles.id');
                $hasRoles = $roleIds->isNotEmpty();
                $rolePerms = \App\Models\Permission::whereHas('roles', function($q) use ($roleIds) {
                    $q->whereIn('roles.id', $roleIds);
                })->pluck('name');
               

                $userPerms = $rolePerms->unique()->map(fn($p) => strtolower(trim($p)));
                

            } catch (\Exception $e) {}

            $featureKeywords = [
                'admin.users'          => ['user management', 'manage user', 'users'],
                'admin.vendors'        => ['vendor approval', 'approve vendor'],
                'admin.roles'          => ['role', 'permission'],
                'admin.categories'     => ['categor'],
                'admin.sales-channels' => ['sales channel', 'channel'],
                'admin.warehouses'     => ['warehouse'],
                'admin.activity-log'   => ['activity', 'log'],
                'sourcing.vendors'     => ['vendor'],
                'sourcing.offer-sheets'=> ['offer sheet'],
                'sourcing.live-sheets' => ['live sheet'],
                'sourcing.consignments'=> ['consignment'],
                'sourcing.inspections' => ['inspection'],
                'sourcing.chargebacks' => ['chargeback'],
                'logistics.container-planning' => ['container'],
                'logistics.shipments'  => ['shipment'],
                'logistics.grn'        => ['grn', 'goods receipt'],
                'logistics.inventory'  => ['inventory'],
                'logistics.warehouse-charges' => ['warehouse charge'],
                'cataloguing.pricing-sheets' => ['pricing sheet'],
                'cataloguing.listing-panel'  => ['listing'],
                'cataloguing.sku-dashboard'  => ['sku'],
                'sales.orders'         => ['order'],
                'sales.upload'         => ['upload sale', 'sales upload'],
                'finance.kyc'          => ['kyc'],
                'finance.live-sheets'  => ['live sheet', 'sap'],
                'finance.receivables'  => ['receivable'],
                'finance.chargebacks'  => ['chargeback'],
                'finance.payouts'      => ['payout'],
                'finance.pricing-review' => ['pricing review'],
                'hod.asn-list'         => ['asn'],
            ];

            $can = function($perm) use ($u, $userPerms, $hasRoles, $dept, $featureKeywords) {
                if ($u->isAdmin()) return true;
                if (!$hasRoles) {
                    $module = explode('.', $perm)[0] ?? '';
                    return $dept === $module;
                }
                if ($userPerms->contains(strtolower($perm))) return true;
                $parts = explode('.', $perm);
                $keywords = $featureKeywords[$perm] ?? [str_replace('-', ' ', end($parts))];
                foreach ($userPerms as $up) {
                    foreach ($keywords as $kw) {
                        if (str_contains($up, $kw)) return true;
                    }
                    $stripped = preg_replace('/^(view|create|edit|delete|approve|lock|unlock|manage)\s+/i', '', $up);
                    $stripped = rtrim($stripped, 's');
                    foreach ($keywords as $kw) {
                        $kwClean = rtrim($kw, 's');
                        if ($stripped === $kwClean || str_contains($stripped, $kwClean)) return true;
                    }
                }
                return false;
            };

            $moduleFeatures = [
                'admin'       => ['admin.users','admin.vendors','admin.roles','admin.categories','admin.sales-channels','admin.warehouses','admin.activity-log'],
                'sourcing'    => ['sourcing.vendors','sourcing.offer-sheets','sourcing.live-sheets','sourcing.consignments','sourcing.inspections','sourcing.chargebacks'],
                'logistics'   => ['logistics.container-planning','logistics.shipments','logistics.grn','logistics.inventory','logistics.warehouse-charges'],
                'cataloguing' => ['cataloguing.pricing-sheets','cataloguing.listing-panel','cataloguing.sku-dashboard'],
                'sales'       => ['sales.orders','sales.upload'],
                'finance'     => ['finance.kyc','finance.live-sheets','finance.receivables','finance.chargebacks','finance.payouts','finance.pricing-review'],
                'hod'         => ['hod.asn-list'],
            ];

            $hasModuleAccess = function($module) use ($u, $can, $hasRoles, $dept, $moduleFeatures) {
                if ($u->isAdmin()) return true;
                if (!$hasRoles) return $dept === $module;
                foreach ($moduleFeatures[$module] ?? [] as $feat) {
                    if ($can($feat)) return true;
                }
                return false;
            };
        @endphp

        {{-- ADMIN --}}
        @if($u->isAdmin() || ($isInternal && $can('admin.access')))
        <div class="section-title">Administration</div>
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        @if($can('admin.users'))<a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users*') ? 'active' : '' }}"><i class="fas fa-users-cog"></i> User Management</a>@endif
        @if($can('admin.vendors'))<a href="{{ route('admin.vendors.pending') }}" class="{{ request()->routeIs('admin.vendors*') ? 'active' : '' }}"><i class="fas fa-user-check"></i> Vendor Approvals</a>@endif
        @if($can('admin.roles'))<a href="{{ route('admin.roles') }}" class="{{ request()->routeIs('admin.roles*') ? 'active' : '' }}"><i class="fas fa-shield-alt"></i> Roles & Permissions</a>@endif
        @if($can('admin.categories'))<a href="{{ route('admin.categories') }}" class="{{ request()->routeIs('admin.categories*') ? 'active' : '' }}"><i class="fas fa-tags"></i> Categories</a>@endif
        @if($can('admin.sales-channels'))<a href="{{ route('admin.sales-channels') }}" class="{{ request()->routeIs('admin.sales-channels*') ? 'active' : '' }}"><i class="fas fa-store"></i> Sales Channels</a>@endif
        @if($can('admin.warehouses'))<a href="{{ route('admin.warehouses') }}" class="{{ request()->routeIs('admin.warehouses*') ? 'active' : '' }}"><i class="fas fa-warehouse"></i> Warehouses</a>@endif
        @if($can('admin.activity-log'))<a href="{{ route('admin.activity-log') }}" class="{{ request()->routeIs('admin.activity-log*') ? 'active' : '' }}"><i class="fas fa-history"></i> Activity Log</a>@endif
        @endif

        {{-- VENDOR --}}
        @if($isVendor)
        <div class="section-title">Vendor Portal</div>
        <a href="{{ route('vendor.dashboard') }}" class="{{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="{{ route('vendor.kyc') }}" class="{{ request()->routeIs('vendor.kyc*') ? 'active' : '' }}"><i class="fas fa-id-card"></i> KYC Documents</a>
        @if($kycApproved)
        <a href="{{ route('vendor.offer-sheets') }}" class="{{ request()->routeIs('vendor.offer-sheets*') ? 'active' : '' }}"><i class="fas fa-file-alt"></i> Offer Sheets</a>
        <a href="{{ route('vendor.live-sheets') }}" class="{{ request()->routeIs('vendor.live-sheets*') ? 'active' : '' }}"><i class="fas fa-clipboard-list"></i> Live Sheets</a>
        <a href="{{ route('vendor.consignments') }}" class="{{ request()->routeIs('vendor.consignments*') ? 'active' : '' }}"><i class="fas fa-box"></i> Consignments</a>
        <a href="{{ route('vendor.inspections.index') }}" class="{{ request()->routeIs('vendor.inspections*') ? 'active' : '' }}"><i class="fas fa-search"></i> Inspections</a>
        <a href="{{ route('vendor.grn') }}" class="{{ request()->routeIs('vendor.grn*') ? 'active' : '' }}"><i class="fas fa-clipboard-check"></i> GRN</a>
        <a href="{{ route('vendor.inventory') }}" class="{{ request()->routeIs('vendor.inventory*') ? 'active' : '' }}"><i class="fas fa-boxes"></i> Inventory</a>
        <a href="{{ route('vendor.sales') }}" class="{{ request()->routeIs('vendor.sales*') ? 'active' : '' }}"><i class="fas fa-chart-line"></i> Sales Report</a>
        <a href="{{ route('vendor.chargebacks') }}" class="{{ request()->routeIs('vendor.chargebacks*') ? 'active' : '' }}"><i class="fas fa-exclamation-triangle"></i> Chargebacks</a>
        <a href="{{ route('vendor.payouts') }}" class="{{ request()->routeIs('vendor.payouts*') ? 'active' : '' }}"><i class="fas fa-money-check-alt"></i> Payouts</a>
        @else
        <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Complete KYC to unlock</span>
        @endif
        @endif

        {{-- SOURCING --}}
        @if($isInternal && $hasModuleAccess('sourcing'))
        <div class="section-title">Sourcing</div>
        <a href="{{ route('sourcing.dashboard') }}" class="{{ request()->routeIs('sourcing.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        @if($can('sourcing.vendors'))<a href="{{ route('sourcing.vendors') }}" class="{{ request()->routeIs('sourcing.vendors*') ? 'active' : '' }}"><i class="fas fa-users"></i> Vendors</a>@endif
        @if($can('sourcing.offer-sheets'))<a href="{{ route('sourcing.offer-sheets') }}" class="{{ request()->routeIs('sourcing.offer*') ? 'active' : '' }}"><i class="fas fa-file-alt"></i> Offer Sheets</a>@endif
        @if($can('sourcing.live-sheets'))<a href="{{ route('sourcing.live-sheets') }}" class="{{ request()->routeIs('sourcing.live*') ? 'active' : '' }}"><i class="fas fa-clipboard-list"></i> Live Sheets</a>@endif
        @if($can('sourcing.consignments'))<a href="{{ route('sourcing.consignments') }}" class="{{ request()->routeIs('sourcing.consignment*') ? 'active' : '' }}"><i class="fas fa-box"></i> Consignments</a>@endif
        @if($can('sourcing.inspections'))<a href="{{ route('sourcing.inspections') }}" class="{{ request()->routeIs('sourcing.inspections*') ? 'active' : '' }}"><i class="fas fa-search"></i> Inspections</a>@endif
        @if($can('sourcing.chargebacks'))<a href="{{ route('sourcing.chargebacks') }}" class="{{ request()->routeIs('sourcing.chargeback*') ? 'active' : '' }}"><i class="fas fa-exclamation-triangle"></i> Chargebacks</a>@endif
        @endif

        {{-- LOGISTICS --}}
        @if($isInternal && $hasModuleAccess('logistics'))
        <div class="section-title">Logistics</div>
        <a href="{{ route('logistics.dashboard') }}" class="{{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        @if($can('logistics.container-planning'))<a href="{{ route('logistics.container-planning') }}" class="{{ request()->routeIs('logistics.container-planning*') ? 'active' : '' }}"><i class="fas fa-cubes"></i> Container Planning</a>@endif
        @if($can('logistics.shipments'))<a href="{{ route('logistics.shipments') }}" class="{{ request()->routeIs('logistics.shipments*') ? 'active' : '' }}"><i class="fas fa-ship"></i> Shipments</a>@endif
        @if($can('logistics.grn'))<a href="{{ route('logistics.grn') }}" class="{{ request()->routeIs('logistics.grn*') ? 'active' : '' }}"><i class="fas fa-clipboard-check"></i> GRN</a>@endif
        @if($can('logistics.inventory'))<a href="{{ route('logistics.inventory') }}" class="{{ request()->routeIs('logistics.inventory*') ? 'active' : '' }}"><i class="fas fa-boxes"></i> Inventory</a>@endif
        @if($can('logistics.warehouse-charges'))<a href="{{ route('logistics.warehouse-charges') }}" class="{{ request()->routeIs('logistics.warehouse-charges*') ? 'active' : '' }}"><i class="fas fa-calculator"></i> Warehouse Charges</a>@endif
        @endif

        {{-- CATALOGUING --}}
        @if($isInternal && $hasModuleAccess('cataloguing'))
        <div class="section-title">Cataloguing</div>
        <a href="{{ route('cataloguing.dashboard') }}" class="{{ request()->routeIs('cataloguing.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        @if($can('cataloguing.pricing-sheets'))<a href="{{ route('cataloguing.pricing-sheets') }}" class="{{ request()->routeIs('cataloguing.pricing-sheets*') ? 'active' : '' }}"><i class="fas fa-dollar-sign"></i> Pricing Sheets</a>@endif
        @if($can('cataloguing.listing-panel'))<a href="{{ route('cataloguing.listing-panel') }}" class="{{ request()->routeIs('cataloguing.listing-panel*') ? 'active' : '' }}"><i class="fas fa-list"></i> Listing Panel</a>@endif
        @if($can('cataloguing.sku-dashboard'))<a href="{{ route('cataloguing.sku-dashboard') }}" class="{{ request()->routeIs('cataloguing.sku-dashboard*') ? 'active' : '' }}"><i class="fas fa-chart-bar"></i> SKU Dashboard</a>@endif
        @endif

        {{-- SALES --}}
        @if($isInternal && $hasModuleAccess('sales'))
        <div class="section-title">Sales</div>
        <a href="{{ route('sales.dashboard') }}" class="{{ request()->routeIs('sales.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        @if($can('sales.orders'))<a href="{{ route('sales.orders') }}" class="{{ request()->routeIs('sales.orders*') ? 'active' : '' }}"><i class="fas fa-shopping-cart"></i> Orders</a>@endif
        @if($can('sales.upload'))<a href="{{ route('sales.upload') }}" class="{{ request()->routeIs('sales.upload*') ? 'active' : '' }}"><i class="fas fa-upload"></i> Upload Sales</a>@endif
        @endif

        {{-- FINANCE --}}
        @if($isInternal && $hasModuleAccess('finance'))
        <div class="section-title">Finance</div>
        <a href="{{ route('finance.dashboard') }}" class="{{ request()->routeIs('finance.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        @if($can('finance.kyc'))<a href="{{ route('finance.kyc') }}" class="{{ request()->routeIs('finance.kyc*') ? 'active' : '' }}"><i class="fas fa-id-card"></i> KYC Review</a>@endif
        @if($can('finance.live-sheets'))<a href="{{ route('finance.live-sheets') }}" class="{{ request()->routeIs('finance.live*') ? 'active' : '' }}"><i class="fas fa-clipboard-list"></i> Live Sheets / SAP</a>@endif
        @if($can('finance.receivables'))<a href="{{ route('finance.receivables') }}" class="{{ request()->routeIs('finance.receivable*') ? 'active' : '' }}"><i class="fas fa-hand-holding-usd"></i> Receivables</a>@endif
        @if($can('finance.chargebacks'))<a href="{{ route('finance.chargebacks') }}" class="{{ request()->routeIs('finance.chargeback*') ? 'active' : '' }}"><i class="fas fa-exclamation-triangle"></i> Chargebacks</a>@endif
        @if($can('finance.payouts'))<a href="{{ route('finance.payouts') }}" class="{{ request()->routeIs('finance.payout*') ? 'active' : '' }}"><i class="fas fa-money-check-alt"></i> Vendor Payouts</a>@endif
        @if($can('finance.pricing-review'))<a href="{{ route('finance.pricing-review') }}" class="{{ request()->routeIs('finance.pricing*') ? 'active' : '' }}"><i class="fas fa-file-invoice-dollar"></i> Pricing Review</a>@endif
        @endif

        {{-- HOD --}}
        @if($isInternal && $hasModuleAccess('hod'))
        <div class="section-title">Management</div>
        <a href="{{ route('hod.dashboard') }}" class="{{ request()->routeIs('hod.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        @if($can('hod.asn-list'))<a href="{{ route('hod.asn-list') }}" class="{{ request()->routeIs('hod.asn-list*') ? 'active' : '' }}"><i class="fas fa-file-alt"></i> ASN & Pricing</a>@endif
        @endif

        @endauth
    </aside>

    <div class="main-content">
        <div class="topbar">
            <h2 style="font-size:1.05rem;font-weight:700;color:#0d1b2a;">@yield('page-title', 'Dashboard')</h2>
            <div style="display:flex;align-items:center;gap:1.25rem;">
                @if(auth()->check() && !auth()->user()->isVendor())
                <form method="GET" action="{{ url()->current() }}" style="display:flex;align-items:center;gap:.4rem;">
                    <span style="font-size:.7rem;color:#64748b;font-weight:600;">Company:</span>
                    <select name="company_code" onchange="this.form.submit()" style="padding:.35rem .6rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:inherit;">
                        <option value="">All</option>
                        <option value="2000" {{ request('company_code')=='2000'?'selected':'' }}>2000 – India</option>
                        <option value="2100" {{ request('company_code')=='2100'?'selected':'' }}>2100 – USA</option>
                        <option value="2200" {{ request('company_code')=='2200'?'selected':'' }}>2200 – Netherlands</option>
                    </select>
                </form>
                @endif
                <a href="{{ route('notifications') }}" class="notification-bell" style="color:#64748b;"><i class="fas fa-bell" style="font-size:1.05rem;"></i>@if(auth()->check() && auth()->user()->unreadNotifications->count()>0)<span class="count">{{ auth()->user()->unreadNotifications->count() }}</span>@endif</a>
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#1e3a5f,#2d6a4f);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:700;">{{ strtoupper(substr(auth()->user()->name??'U',0,1)) }}</div>
                    <div>
                        <div style="font-size:.78rem;font-weight:600;color:#0d1b2a;">{{ auth()->user()->name??'' }}</div>
                        <div style="font-size:.6rem;color:#94a3b8;">{{ ucfirst(auth()->user()->department??auth()->user()->user_type??'') }}</div>
                    </div>
                    <!-- <form method="POST" action="{{ route('auth.logout') }}" style="margin-left:.3rem;">@csrf<button type="submit" style="background:none;border:none;cursor:pointer;color:#94a3b8;" title="Logout"><i class="fas fa-sign-out-alt"></i></button></form> -->
                    <a href="{{ route('auth.logout') }}" style="margin-left:.3rem;color:#94a3b8;text-decoration:none;" title="Logout"
                        onclick="return confirm('Are you sure you want to logout?')">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
        <div style="padding:1.25rem 2rem 0;">
            @if(session('success'))<div class="alert alert-success" id="successAlert"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>@endif
            @if($errors->any())
            <div class="alert alert-error" style="padding:.75rem 1rem;">
                <div style="font-weight:700;margin-bottom:.3rem;"><i class="fas fa-exclamation-triangle"></i> Please fix the following errors:</div>
                <ul style="margin:0;padding-left:1.2rem;font-size:.82rem;line-height:1.7;">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
        <div style="padding:1.25rem 2rem 3rem;">@yield('content')</div>
    </div>
    <script>
        // Only auto-hide SUCCESS alerts after 5 seconds, NEVER hide errors
        var sa = document.getElementById('successAlert');
        if (sa) setTimeout(function() {
            sa.style.display = 'none';
        }, 5000);
    </script>
    @stack('scripts')
</body>

</html>