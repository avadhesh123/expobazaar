<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Expo Bazaar SCM')</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
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
<div id="globalLoader">
    <div class="loader-box">
        <div class="loader-spinner"></div>
        <div class="loader-text">Processing...</div>
        <div class="loader-sub">Please wait, do not refresh the page</div>
    </div>
</div>

<script>
$(document).ready(function() {

    // Global Loader for ALL Forms (except AJAX, downloads, and excluded forms)
    $(document).on('submit', 'form', function(e) {
        var $form = $(this);

        // Skip loader for these cases
        if ($form.hasClass('no-loader')) return;
        if ($form.attr('target') === '_blank') return;
        if ($form.data('ajax')) return;

        // Skip for file download forms (they don't navigate away)
        var action = ($form.attr('action') || '').toLowerCase();
        if (action.includes('download') || action.includes('export') || action.includes('template')) return;

        // Show loader with flex display for centering
        $('#globalLoader').css('display', 'flex').hide().fadeIn(200);

        // Disable submit button to prevent double submit
        var $btn = $form.find('button[type="submit"]');
        if ($btn.length) {
            $btn.data('original-html', $btn.html());
            $btn.prop('disabled', true)
                .html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        }
    });

    // Hide loader after AJAX requests
    $(document).ajaxComplete(function() {
        hideLoader();
    });

    // Hide loader on page load (handles browser back button)
    hideLoader();

    // Hide loader if page becomes visible again (tab switch back)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) hideLoader();
    });

    function hideLoader() {
        $('#globalLoader').fadeOut(300, function() {
            $(this).css('display', 'none');
        });
        // Restore all disabled submit buttons
        $('button[type="submit"]:disabled').each(function() {
            var original = $(this).data('original-html');
            if (original) $(this).prop('disabled', false).html(original);
        });
    }
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