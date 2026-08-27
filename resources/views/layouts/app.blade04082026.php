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
            height: 100vh;
            width: 260px;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 40;
            border-right: 1px solid rgba(255, 255, 255, .06);
            padding-bottom: 2rem;
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
            /* padding: 1.4rem 1.25rem; */
            padding-top: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            text-align: center;
        }

        .sidebar .logo h1 {
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: -.02em;
        }

        .sidebar .logo img {
            width: 100%;
            max-width: 170px;
            height: 67.74px;
            border-radius: 5px;
            margin-left: 37.5px;
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
        .flag {
    font-family: "Noto Color Emoji", "Segoe UI Emoji", "Apple Color Emoji" !important;
            font-size: 1.25rem;
            vertical-align: middle;
            line-height: 1;
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
        .sidebar .listing-panel{
            /* display: none; */
        }
        .sidebar .home{
             font-size:18px;    color:#dbeafe; 
        }
    </style>
         <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

</head>

<body>
    @if(session('impersonating_from'))
<div style="position:fixed;top:0;left:0;width:100%;z-index:99999;background:#dc2626;color:#fff;padding:6px 16px;font-size:.78rem;display:flex;justify-content:space-between;align-items:center;">
    <span>
        <i class="fas fa-user-secret" style="margin-right:.3rem;"></i>
        <strong>Admin View</strong> — Viewing as <strong>{{ auth()->user()->vendor->company_name ?? auth()->user()->name }}</strong>
    </span>
    <a href="{{ route('admin.stop-impersonating') }}" style="background:#fff;color:#dc2626;padding:3px 12px;border-radius:5px;font-weight:700;text-decoration:none;font-size:.72rem;">
        <i class="fas fa-sign-out-alt" style="margin-right:.2rem;"></i> Back to Admin
    </a>
</div>
<div style="height:32px;"></div>
@endif
    <aside class="sidebar">
        
        <div class="logo">
            <img src="{{ asset('images/logo.svg') }}" alt="ExpoBazaar" loading="eager"  style="background: #fff; padding: 5px; border-radius: 5px;"sizes="200px" data-animate="fadein"> 
            <h1>EB Partner Home</h1>
            <!-- <p style="color:#4a5e6f;font-size:.65rem;margin-top:.15rem;">Supply Chain Management</p> -->
        </div>
        @auth
        @php
        $u = auth()->user();
        $modules = config('modules');
        $isVendor = $u->user_type === 'vendor';
        $isAdmin = $u->isAdmin();
        //echo  $u->user_type ;
        // Get user's permissions (cached) — used for ALL user types except admin
        $userPerms = collect();
        if (!$isVendor) {
        $userPerms = \App\Services\PermissionService::getUserPermissions($u);
        }

        $test = [];

        $sidebarModules = [];

        foreach ($modules as $moduleKey => $moduleConfig) {

        // Vendor module only for vendors, internal modules only for non-vendors
    //    if ($moduleKey === 'vendor' && !$isVendor) continue;
      //  if ($moduleKey !== 'vendor' && $isVendor) continue;

        // ── ADMIN: sees everything from config ──
        if ($isAdmin) {
        $features = [];
        foreach ($moduleConfig['entities'] as $entityKey => $entityConfig) {
        if (isset($entityConfig['sidebar']) && $entityConfig['sidebar'] === false) continue;
        $routeName = $entityConfig['route'] ?? null;
        if (!$routeName || !\Route::has($routeName)) continue;
        $features[] = [
        'label' => $entityConfig['label'],
        'route' => $routeName,
        'icon' => $entityConfig['icon'] ?? $moduleConfig['icon'],
        ];
        }
        if (!empty($features)) {
        $sidebarModules[$moduleKey] = ['label' => $moduleConfig['label'], 'features' => $features];
        }
        continue;
        }

        // ── VENDOR: sees all vendor module items ──
        if ($isVendor && $moduleKey === 'vendor') {
        $features = [];
        foreach ($moduleConfig['entities'] as $entityKey => $entityConfig) {
        if (isset($entityConfig['sidebar']) && $entityConfig['sidebar'] === false) continue;
        $routeName = $entityConfig['route'] ?? null;
        if (!$routeName || !\Route::has($routeName)) continue;
        $features[] = [
        'label' => $entityConfig['label'].'-AAAA',
        'route' => $routeName,
        'icon' => $entityConfig['icon'] ?? $moduleConfig['icon'],
        ];
        }
        if (!empty($features)) {
        $sidebarModules[$moduleKey] = ['label' => $moduleConfig['label'], 'features' => $features];
        }
        continue;
        }

        // ── INTERNAL + EXTERNAL: show only items assigned via roles/permissions ──
        $features = [];

        foreach ($moduleConfig['entities'] as $entityKey => $entityConfig) {
        if (isset($entityConfig['sidebar']) && $entityConfig['sidebar'] === false) continue;
        $routeName = $entityConfig['route'] ?? null;
        if (!$routeName || !\Route::has($routeName)) continue;

        // Check if user has any permission for this entity
        $hasAccess = false;

        // Check module.entity.action format
        foreach ($entityConfig['actions'] as $action) {
        if ($userPerms->contains("{$moduleKey}.{$entityKey}.{$action}")) {
        $hasAccess = true;
        break;
        }
        }

        // Also check legacy format: module.entity (without action)
        if (!$hasAccess && $userPerms->contains("{$moduleKey}.{$entityKey}")) {
        $hasAccess = true;
        }




        // Also check display_name style: "View Shipments" contains "shipment"
        if (!$hasAccess) {


        $entityLower = strtolower(str_replace('-', ' ', $entityKey));
        foreach ($userPerms as $p) {
        if (str_contains(strtolower($p), $moduleKey.'.'.$entityLower)) {
        $hasAccess = true;
        break;
        }
        }
        }

        if ($hasAccess) {
        $features[] = [
        'label' => $entityConfig['label'] ,
        'route' => $routeName,
        'icon' => $entityConfig['icon'] ?? $moduleConfig['icon'],
        ];
        }
        }

        if (!empty($features)) {
        $sidebarModules[$moduleKey] = ['label' => $moduleConfig['label'] , 'features' => $features];
        }
        }
        @endphp
        
        @foreach($sidebarModules as $moduleKey => $module)
        <div class="section-title">{{ $module['label'] }}</div>
        @foreach($module['features'] as $feat)
        @php
    $isActive = request()->routeIs($feat['route'] . '*') || request()->routeIs($feat['route']);
    $activeClass = $isActive ? 'active '.Str::slug($feat['label'])  :Str::slug($feat['label']);
@endphp

<a href="{{ route($feat['route']) }}" class="{{ $activeClass }}">
    <i class="{{ $feat['icon'] }}"></i> {{ $feat['label'] }}
</a>
        @endforeach
        @endforeach
        @endauth
    </aside>

    <div class="main-content">
        <div class="topbar">
            <h2 style="font-size:1.05rem;font-weight:700;color:#0d1b2a;">@yield('page-title', 'Dashboard')</h2>
            <div style="display:flex;align-items:center;gap:1.25rem;" >
                {{-- Active Company Badge --}}
                @if(auth()->check())
                @php
                   $activeCode = session('active_company');
                   $companyLabels = [ 
    '2100' => '<img src="https://flagcdn.com/w40/us.png" width="25" alt="United States" style="float: left;margin-right: 2px;margin-top: 2px;"> ExpoBazaar USA', 
    '2200' => '<img src="https://flagcdn.com/w40/eu.png" width="25" alt="European Union" style="float: left;margin-right: 2px;margin-top: 2px;"> ExpoBazaar EU', 
    '2400' => '<img src="https://flagcdn.com/w40/gb.png" width="25" alt="United Kingdom" style="float: left;margin-right: 2px;margin-top: 2px;"> ExpoBazaar UK' 
    ];
                   $companyColors = ['2100' => '#1e40af', '2200' => '#16a34a','2400' => '#dc2626'];  
                @endphp
                <div style="padding:.25rem .6rem;background:{{ $companyColors[$activeCode] ?? '#64748b' }}15;border:1px solid {{ $companyColors[$activeCode] ?? '#64748b' }}40;border-radius:6px;font-size:.72rem;font-weight:700;color:{{ $companyColors[$activeCode] ?? '#64748b' }};">
                    {!! $companyLabels[$activeCode] ?? '🌐 All' !!} · {{ $activeCode ?? '' }} 
                </div> 
                @endif
                {{-- Notifications --}}
                <a href="{{ route('notifications') }}" class="notification-bell" style="color:#64748b;"><i class="fas fa-bell" style="font-size:1.05rem;"></i>@if(auth()->check() && auth()->user()->unreadNotifications->count()>0)<span class="count">{{ auth()->user()->unreadNotifications->count() }}</span>@endif</a>
                 {{-- Profile Dropdown --}}
                <div style="position:relative;" id="profileDropdown">
                    <button onclick="document.getElementById('profileMenu').classList.toggle('show')" style="display:flex;align-items:center;gap:.5rem;background:none;border:none;cursor:pointer;padding:.25rem;">
                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1e3a5f,#2d6a4f);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:700;">{{ strtoupper(substr(auth()->user()->name??'U',0,1)) }}</div>
                        <div style="text-align:left;">
                            <div style="font-size:.78rem;font-weight:600;color:#0d1b2a;">{{ auth()->user()->name??'' }}</div>
                            <div style="font-size:.58rem;color:#94a3b8;">{{ ucfirst(auth()->user()->department??auth()->user()->user_type??'') }}</div>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size:.55rem;color:#94a3b8;"></i>
                    </button>

                    {{-- Dropdown Menu --}}
                    <div id="profileMenu" class="profile-dropdown-menu" style="display:none;position:absolute;right:0;top:100%;margin-top:.4rem;width:260px;background:#fff;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.12);border:1px solid #e2e8f0;z-index:100;overflow:hidden;">

                        {{-- User Info --}}
                        <div style="padding:.75rem 1rem;border-bottom:1px solid #f1f5f9;background:#fafbfc;">
                            <div style="font-size:.82rem;font-weight:700;color:#0d1b2a;">{{ auth()->user()?->name }}</div>
                            <div style="font-size:.68rem;color:#64748b;">{{ auth()->user()?->email }}</div>
                            <div style="font-size:.62rem;color:#94a3b8;margin-top:.15rem;">{{ ucfirst(auth()->user()?->user_type) }} · {{ ucfirst(auth()->user()?->department ?? '') }}</div>
                        </div>

                        {{-- Company Switcher --}}
                        @if(auth()->check())
                        @php
                            $userCodes = auth()->user()->company_codes ?? [];
                            if (is_string($userCodes)) $userCodes = json_decode($userCodes, true) ?? [];
                            if (auth()->user()->isAdmin()) $userCodes = ['2100', '2200', '2400']; // Admin can switch to all companies
                            $companyNames = [ '2100' => '🇺🇸 ExpoBazaar USA', '2200' => '🇪🇺 ExpoBazaar EU','2400' => '🇬🇧 ExpoBazaar UK'];
                        @endphp
                        <div style="padding:.5rem 1rem;border-bottom:1px solid #f1f5f9;">
                            <div style="font-size:.6rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.35rem;">Switch Company</div>
                            @foreach($userCodes as $code)
                            <a href="?switch_company={{ $code }}" style="display:flex;align-items:center;gap:.5rem;padding:.35rem .5rem;border-radius:6px;text-decoration:none;font-size:.78rem;color:#334155;{{ $activeCode === $code ? 'background:#eff6ff;font-weight:700;color:#1e40af;' : '' }}" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='{{ $activeCode === $code ? '#eff6ff' : 'transparent' }}'">
                                <span style="width:8px;height:8px;border-radius:50%;background:{{ $companyColors[$code] ?? '#94a3b8' }};{{ $activeCode === $code ? 'box-shadow:0 0 0 3px '.$companyColors[$code].'30;' : '' }}"></span>
                                {!! $companyLabels[$code] ?? $code !!}
                                @if($activeCode === $code)<i class="fas fa-check" style="margin-left:auto;font-size:.6rem;color:#1e40af;"></i>@endif
                            </a>
                            @endforeach
                        </div>
                        @endif

                        {{-- Menu Items --}}
                        <div style="padding:.4rem .5rem;">
                           <a href="{{ route('auth.change-password') }}" style=" text-align:center;display: flex; align-items: center;  gap: .5rem;  padding: .4rem .5rem;  border-radius: 6px;  text-decoration: none;  font-size: .78rem;">
                                <i class="fas fa-key"></i> Change Password
                            </a>
                            <a href="{{ route('auth.logout') }}" onclick="return confirm('Logout?')" style="display:flex;align-items:center;gap:.5rem;padding:.4rem .5rem;border-radius:6px;text-decoration:none;font-size:.78rem;color:#dc2626;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                                <i class="fas fa-sign-out-alt" style="width:16px;text-align:center;"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
                 
            </div>
        </div>
        {{-- Click outside to close dropdown --}}
      {{-- Profile Dropdown Script --}}
<script>
document.addEventListener('DOMContentLoaded', function() {

    const profileBtn = document.querySelector('#profileDropdown button');
    const profileMenu = document.getElementById('profileMenu');

    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileMenu.style.display = (profileMenu.style.display === 'block') ? 'none' : 'block';
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileBtn.contains(e.target)) {
                profileMenu.style.display = 'none';
            }
        });
    }

});
</script>
        <div style="padding:1.25rem 2rem 0;">
            @if(session('success'))<div class="alert alert-success" id="successAlert"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i>{!! session('error') !!}</div>@endif
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


{{-- Global Form Loader --}}
<div id="globalLoader" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); z-index: 99999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 35px 45px; border-radius: 12px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.3); min-width: 280px;">
        <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: #3b82f6; margin-bottom: 18px;"></i>
        <div style="font-size: 1.15rem; font-weight: 600; color: #1e3a5f;">Processing...</div>
        <div style="font-size: 0.88rem; color: #64748b; margin-top: 8px;">Please wait, do not refresh the page</div>
    </div>
</div>

<script>
$(document).ready(function() {

    // Global Loader for ALL Forms
    $(document).on('submit', 'form', function() {
        
        // Show loader
        $('#globalLoader').fadeIn(200);

        // Optional: Disable submit button to prevent double submit
        const $btn = $(this).find('button[type="submit"]');
        if ($btn.length) {
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        }
    });

    // Hide loader after AJAX requests (if using AJAX)
    $(document).ajaxComplete(function() {
        $('#globalLoader').fadeOut(300);
    });

});
</script>


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