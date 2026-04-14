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
        :root{--eb-primary:#1e3a5f;--eb-secondary:#e8a838;--eb-accent:#2d6a4f;--eb-light:#f8f9fa;--eb-dark:#0d1b2a;}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:#f1f5f9;}
        .sidebar{background:linear-gradient(180deg,#0d1b2a 0%,#1e3a5f 100%);min-height:100vh;width:260px;position:fixed;left:0;top:0;overflow-y:auto;z-index:40;border-right:1px solid rgba(255,255,255,.06);}
        .sidebar a{display:flex;align-items:center;padding:.7rem 1.25rem;color:#8899aa;font-size:.82rem;transition:all .2s;border-left:3px solid transparent;text-decoration:none;}
        .sidebar a:hover,.sidebar a.active{color:#fff;background:rgba(255,255,255,.07);border-left-color:#e8a838;}
        .sidebar a i{width:1.5rem;text-align:center;margin-right:.7rem;font-size:.85rem;}
        .sidebar .logo{padding:1.4rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.08);}
        .sidebar .logo h1{color:#fff;font-size:1.2rem;font-weight:800;letter-spacing:-.02em;}
        .sidebar .logo span{color:#e8a838;}
        .sidebar .section-title{padding:.9rem 1.25rem .25rem;font-size:.6rem;text-transform:uppercase;letter-spacing:.12em;color:#4a5e6f;font-weight:700;}
        .main-content{margin-left:260px;min-height:100vh;}
        .topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:.65rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:30;backdrop-filter:blur(8px);}
        .kpi-card{background:#fff;border-radius:14px;padding:1.4rem;box-shadow:0 1px 3px rgba(0,0,0,.04);border:1px solid #e8ecf1;transition:transform .2s,box-shadow .2s;}
        .kpi-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.07);}
        .kpi-value{font-size:1.7rem;font-weight:800;color:#0d1b2a;letter-spacing:-.02em;}
        .kpi-label{font-size:.68rem;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.2rem;font-weight:600;}
        .kpi-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;}
        .data-table{width:100%;border-collapse:separate;border-spacing:0;}
        .data-table th{background:#f8fafc;padding:.7rem 1rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;text-align:left;border-bottom:2px solid #e2e8f0;}
        .data-table td{padding:.7rem 1rem;font-size:.83rem;border-bottom:1px solid #f1f5f9;color:#334155;}
        .data-table tbody tr:hover{background:#f8fafc;}
        .badge{display:inline-flex;align-items:center;padding:.2rem .65rem;border-radius:9999px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
        .badge-success{background:#dcfce7;color:#166534;}.badge-warning{background:#fef3c7;color:#92400e;}.badge-danger{background:#fee2e2;color:#991b1b;}.badge-info{background:#dbeafe;color:#1e40af;}.badge-gray{background:#f1f5f9;color:#475569;}
        .btn{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1.1rem;border-radius:8px;font-size:.82rem;font-weight:600;transition:all .2s;cursor:pointer;border:none;text-decoration:none;}
        .btn-primary{background:#1e3a5f;color:#fff;}.btn-primary:hover{background:#152d4a;}
        .btn-secondary{background:#e8a838;color:#fff;}.btn-secondary:hover{background:#d69a30;}
        .btn-success{background:#2d6a4f;color:#fff;}.btn-danger{background:#dc2626;color:#fff;}
        .btn-outline{background:transparent;border:1px solid #d1d5db;color:#374151;}.btn-sm{padding:.3rem .65rem;font-size:.75rem;}
        .card{background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.04);border:1px solid #e8ecf1;}
        .card-header{padding:1.1rem 1.4rem;border-bottom:1px solid #e8ecf1;display:flex;align-items:center;justify-content:space-between;}
        .card-header h3{font-size:.95rem;font-weight:700;color:#0d1b2a;}
        .card-body{padding:1.4rem;}
        .alert{padding:.85rem 1.1rem;border-radius:8px;margin-bottom:1rem;display:flex;align-items:center;font-size:.85rem;font-weight:500;}
        .alert-success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
        .alert i{margin-right:.5rem;}
        .notification-bell{position:relative;}
        .notification-bell .count{position:absolute;top:-5px;right:-5px;background:#dc2626;color:#fff;font-size:.55rem;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;}
        .form-group{margin-bottom:1rem;}
        .form-group label{display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.3rem;}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:.5rem .75rem;border:1px solid #d1d5db;border-radius:8px;font-size:.85rem;font-family:inherit;transition:border-color .2s;}
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#1e3a5f;box-shadow:0 0 0 3px rgba(30,58,95,.08);}
        .grid-kpi{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;}
        .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.25rem;}
        @media(max-width:1024px){.grid-2,.grid-3{grid-template-columns:1fr;}.sidebar{width:0;overflow:hidden;}.main-content{margin-left:0;}}
    </style>
</head>
<body>
    <aside class="sidebar">
 <div class="logo" style="text-align:center;">
            <img src="{{ asset('images/logo.jpeg') }}" alt="ExpoBazaar" style="height: 180px;margin-bottom:.2rem;">
         </div>        @auth
            @if(auth()->user()->isAdmin())
                <div class="section-title">Administration</div>
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users*') ? 'active' : '' }}"><i class="fas fa-users-cog"></i> User Management</a>
                <a href="{{ route('admin.vendors.pending') }}" class="{{ request()->routeIs('admin.vendors*') ? 'active' : '' }}"><i class="fas fa-user-check"></i> Vendor Approvals</a>
                <a href="{{ route('admin.roles') }}" class="{{ request()->routeIs('admin.roles*') ? 'active' : '' }}"><i class="fas fa-shield-alt"></i> Roles & Permissions</a>
                <div class="section-title">Masters</div>
                <a href="{{ route('admin.categories') }}"><i class="fas fa-tags"></i> Categories</a>
                <a href="{{ route('admin.sales-channels') }}"><i class="fas fa-store"></i> Sales Channels</a>
                <a href="{{ route('admin.warehouses') }}"><i class="fas fa-warehouse"></i> Warehouses</a>
                <div class="section-title">System</div>
                <a href="{{ route('admin.activity-log') }}"><i class="fas fa-history"></i> Activity Log</a>
            @elseif(auth()->user()->isVendor())
                @php $kycApproved = auth()->user()->vendor && auth()->user()->vendor->kyc_status === 'approved'; @endphp
                <div class="section-title">Vendor Panel</div>
                <a href="{{ route('vendor.dashboard') }}" class="{{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="{{ route('vendor.kyc') }}" class="{{ request()->routeIs('vendor.kyc*') ? 'active' : '' }}"><i class="fas fa-id-card"></i> KYC Documents</a>

                @if($kycApproved)
                    <a href="{{ route('vendor.offer-sheets') }}" class="{{ request()->routeIs('vendor.offer-sheets*') ? 'active' : '' }}"><i class="fas fa-file-alt"></i> Offer Sheets</a>
                    <a href="{{ route('vendor.live-sheets') }}" class="{{ request()->routeIs('vendor.live-sheets*') ? 'active' : '' }}"><i class="fas fa-clipboard-list"></i> Live Sheets</a>
                    <a href="{{ route('vendor.consignments') }}" class="{{ request()->routeIs('vendor.consignments*') ? 'active' : '' }}"><i class="fas fa-box"></i> Consignments</a>
                    <a href="{{ route('vendor.sales') }}" class="{{ request()->routeIs('vendor.sales*') ? 'active' : '' }}"><i class="fas fa-chart-line"></i> Sales Report</a>
                    <a href="{{ route('vendor.chargebacks') }}" class="{{ request()->routeIs('vendor.chargebacks*') ? 'active' : '' }}"><i class="fas fa-exclamation-triangle"></i> Chargebacks</a>
                    <a href="{{ route('vendor.payouts') }}" class="{{ request()->routeIs('vendor.payouts*') ? 'active' : '' }}"><i class="fas fa-money-check-alt"></i> Payouts</a>
                @else
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Offer Sheets</span>
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Live Sheets</span>
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Consignments</span>
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Sales Report</span>
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Chargebacks</span>
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Payouts</span>
                    <div style="margin:.5rem .85rem;padding:.5rem .65rem;background:#fef3c7;border-radius:8px;font-size:.68rem;color:#92400e;line-height:1.3;">
                        <i class="fas fa-info-circle" style="margin-right:.2rem;"></i> Complete KYC & get Finance approval to unlock all modules.
                    </div>
                @endif
            @elseif(auth()->user()->department === 'sourcing')
                <div class="section-title">Sourcing</div>
                <a href="{{ route('sourcing.dashboard') }}" class="{{ request()->routeIs('sourcing.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="{{ route('sourcing.vendors') }}"><i class="fas fa-users"></i> Vendors</a>
                <a href="{{ route('sourcing.offer-sheets') }}"><i class="fas fa-file-alt"></i> Offer Sheets</a>
                <a href="{{ route('sourcing.live-sheets') }}"><i class="fas fa-clipboard-list"></i> Live Sheets</a>
                <a href="{{ route('sourcing.consignments') }}"><i class="fas fa-box"></i> Consignments</a>
                <a href="{{ route('sourcing.chargebacks') }}"><i class="fas fa-exclamation-triangle"></i> Chargebacks</a>
            @elseif(auth()->user()->department === 'logistics')
                <div class="section-title">Logistics</div>
                <a href="{{ route('logistics.dashboard') }}" class="{{ request()->routeIs('logistics.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="{{ route('logistics.container-planning') }}"><i class="fas fa-cubes"></i> Container Planning</a>
                <a href="{{ route('logistics.shipments') }}"><i class="fas fa-ship"></i> Shipments & ASN</a>
                <a href="{{ route('logistics.grn') }}"><i class="fas fa-clipboard-check"></i> GRN</a>
                <a href="{{ route('logistics.inventory') }}"><i class="fas fa-boxes"></i> Inventory</a>
                <a href="{{ route('logistics.warehouse-charges') }}"><i class="fas fa-calculator"></i> Warehouse Charges</a>
            @elseif(auth()->user()->department === 'cataloguing')
                <div class="section-title">Cataloguing</div>
                <a href="{{ route('cataloguing.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="{{ route('cataloguing.pricing-sheets') }}"><i class="fas fa-dollar-sign"></i> Pricing Sheets</a>
                <a href="{{ route('cataloguing.listing-panel') }}"><i class="fas fa-list"></i> Listing Panel</a>
                <a href="{{ route('cataloguing.sku-dashboard') }}"><i class="fas fa-chart-bar"></i> SKU Dashboard</a>
            @elseif(auth()->user()->department === 'sales')
                <div class="section-title">Sales</div>
                <a href="{{ route('sales.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="{{ route('sales.orders') }}"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="{{ route('sales.upload') }}"><i class="fas fa-upload"></i> Upload Sales</a>
            @elseif(auth()->user()->department === 'finance')
                <div class="section-title">Finance</div>
                <a href="{{ route('finance.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="{{ route('finance.kyc') }}"><i class="fas fa-id-card"></i> KYC Review</a>
                <a href="{{ route('finance.receivables') }}"><i class="fas fa-hand-holding-usd"></i> Receivables</a>
                <a href="{{ route('finance.chargebacks') }}"><i class="fas fa-exclamation-triangle"></i> Chargebacks</a>
                <a href="{{ route('finance.payouts') }}"><i class="fas fa-money-check-alt"></i> Vendor Payouts</a>
                <a href="{{ route('finance.pricing-review') }}"><i class="fas fa-file-invoice-dollar"></i> Pricing Review</a>
            @elseif(auth()->user()->department === 'hod')
                <div class="section-title">Management</div>
                <a href="{{ route('hod.dashboard') }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="{{ route('hod.asn-list') }}"><i class="fas fa-file-alt"></i> ASN & Pricing</a>
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
                    <div><div style="font-size:.78rem;font-weight:600;color:#0d1b2a;">{{ auth()->user()->name??'' }}</div><div style="font-size:.6rem;color:#94a3b8;">{{ ucfirst(auth()->user()->department??auth()->user()->user_type??'') }}</div></div>
                    <form method="POST" action="{{ route('auth.logout') }}" style="margin-left:.3rem;">@csrf<button type="submit" style="background:none;border:none;cursor:pointer;color:#94a3b8;" title="Logout"><i class="fas fa-sign-out-alt"></i></button></form>
                </div>
            </div>
        </div>
        <div style="padding:1.25rem 2rem 0;">
            @if(session('success'))<div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>@endif
            @if($errors->any())<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}</div>@endif
        </div>
        <div style="padding:1.25rem 2rem 3rem;">@yield('content')</div>
    </div>
    <script>document.querySelectorAll('.alert').forEach(a=>setTimeout(()=>a.style.display='none',5000));</script>
    @stack('scripts')
</body>
</html>
