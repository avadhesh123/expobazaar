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
            {{-- Sidebar Search --}}
        <div style="padding:0 .75rem .5rem; margin-top:.5rem;">
            <div style="position:relative;">
                <i class="fas fa-search" style="position:absolute;left:8px;top:50%;transform:translateY(-50%);font-size:.65rem;color:#4a5e6f;"></i>
                <input type="text" id="sidebarSearch" placeholder="Search menu..." autocomplete="off"
                    style="width:100%;padding:.35rem .5rem .35rem 26px;border:1px solid #1e3a5f;border-radius:6px;font-size:.72rem;font-family:inherit;background:rgba(255,255,255,.05);color:#c8d6e0;outline:none;"
                    onfocus="this.style.borderColor='#3b82f6';this.style.background='rgba(255,255,255,.1)'"
                    onblur="this.style.borderColor='#1e3a5f';this.style.background='rgba(255,255,255,.05)'"
                    oninput="filterSidebar(this.value)">
            </div>
        </div>

        <script>
        function filterSidebar(query) {
            var q = query.toLowerCase().trim();
            var sections = document.querySelectorAll('.section-title');
            var links = document.querySelectorAll('.sidebar a[href]');

            if (q === '') {
                // Show everything
                sections.forEach(function(s) { s.style.display = ''; });
                links.forEach(function(a) { if (!a.closest('.user-info-box')) a.style.display = ''; });
                return;
            }

            // Hide all first
            sections.forEach(function(s) { s.style.display = 'none'; });
            links.forEach(function(a) {
                if (a.closest('.user-info-box')) return;
                a.style.display = 'none';
            });

            // Show matching links and their section headers
            links.forEach(function(a) {
                if (a.closest('.user-info-box')) return;
                var text = a.textContent.toLowerCase().trim();
                if (text.indexOf(q) > -1) {
                    a.style.display = '';
                    // Show parent section title
                    var prev = a.previousElementSibling;
                    var el = a;
                    while (el.previousElementSibling) {
                        el = el.previousElementSibling;
                        if (el.classList && el.classList.contains('section-title')) {
                            el.style.display = '';
                            break;
                        }
                    }
                    // Also check parent traversal
                    var parent = a.parentElement;
                    if (parent) {
                        var sectionTitle = null;
                        var sibling = a;
                        while (sibling = sibling.previousElementSibling) {
                            if (sibling.classList.contains('section-title')) {
                                sibling.style.display = '';
                                break;
                            }
                        }
                    }
                }
            });
        }

        // Keyboard shortcut: / to focus search
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                document.getElementById('sidebarSearch').focus();
            }
        });
        </script>
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

{{-- Global SKU Search — Internal Users Only (Ctrl+F) --}}
@if(auth()->check() && auth()->user()->user_type !== 'external')
<div id="skuSearchModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:99998;align-items:flex-start;justify-content:center;padding-top:10vh;">
    <div style="background:#fff;border-radius:14px;width:640px;max-width:94%;max-height:75vh;box-shadow:0 12px 48px rgba(0,0,0,.2);display:flex;flex-direction:column;">
        {{-- Header --}}
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:.6rem;">
            <i class="fas fa-search" style="color:#1e3a5f;font-size:1.1rem;"></i>
            <input type="text" id="skuSearchInput" placeholder="Search SKU, product name, barcode..."
                style="flex:1;border:none;outline:none;font-size:1rem;font-family:inherit;padding:.3rem 0;"
                autocomplete="off" spellcheck="false">
            <kbd style="background:#f1f5f9;border:1px solid #d1d5db;border-radius:4px;padding:1px 6px;font-size:.65rem;color:#64748b;">ESC</kbd>
            <button onclick="closeSkuSearch()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:#94a3b8;">&times;</button>
        </div>

        {{-- Results --}}
        <div id="skuSearchResults" style="flex:1;overflow-y:auto;padding:.5rem;">
            <div style="text-align:center;padding:2rem;color:#94a3b8;font-size:.85rem;">
                <i class="fas fa-search" style="font-size:1.5rem;display:block;margin-bottom:.5rem;"></i>
                Type a SKU, product name, or barcode to search
                <div style="font-size:.7rem;margin-top:.5rem;color:#cbd5e1;">
                    <kbd style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:3px;padding:0 4px;">Ctrl</kbd> +
                    <kbd style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:3px;padding:0 4px;">F</kbd> to open anytime
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Open with Ctrl+F
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        openSkuSearch();
    }
});

function openSkuSearch() {
    var modal = document.getElementById('skuSearchModal');
    modal.style.display = 'flex';
    var input = document.getElementById('skuSearchInput');
    input.value = '';
    input.focus();
    document.getElementById('skuSearchResults').innerHTML =
        '<div style="text-align:center;padding:2rem;color:#94a3b8;font-size:.85rem;">' +
        '<i class="fas fa-search" style="font-size:1.5rem;display:block;margin-bottom:.5rem;"></i>' +
        'Type a SKU, product name, or barcode to search</div>';
}

function closeSkuSearch() {
    document.getElementById('skuSearchModal').style.display = 'none';
}

// Close on ESC or backdrop click
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSkuSearch();
});
document.getElementById('skuSearchModal').addEventListener('click', function(e) {
    if (e.target === this) closeSkuSearch();
});

// Debounced search
var skuSearchTimer = null;
document.getElementById('skuSearchInput').addEventListener('input', function() {
    clearTimeout(skuSearchTimer);
    var q = this.value.trim();
    if (q.length < 2) {
        document.getElementById('skuSearchResults').innerHTML =
            '<div style="text-align:center;padding:2rem;color:#94a3b8;font-size:.85rem;">Type at least 2 characters...</div>';
        return;
    }
    document.getElementById('skuSearchResults').innerHTML =
        '<div style="text-align:center;padding:1.5rem;color:#94a3b8;"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';

    skuSearchTimer = setTimeout(function() {
        fetch('/api/sku-search?q=' + encodeURIComponent(q), {
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) { renderSkuResults(data); })
        .catch(function(err) {
            document.getElementById('skuSearchResults').innerHTML =
                '<div style="text-align:center;padding:1.5rem;color:#dc2626;">Search failed: ' + err.message + '</div>';
        });
    }, 300);
});

function renderSkuResults(data) {
    var container = document.getElementById('skuSearchResults');
    if (!data.results || data.results.length === 0) {
        container.innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8;">' +
            '<i class="fas fa-inbox" style="font-size:1.5rem;display:block;margin-bottom:.5rem;"></i>No results found</div>';
        return;
    }

    var html = '<div style="font-size:.7rem;color:#94a3b8;padding:.2rem .5rem;margin-bottom:.3rem;">' +
        data.results.length + ' result(s) found</div>';

    data.results.forEach(function(item) {
        var lsHtml = '';
        if (item.live_sheets && item.live_sheets.length > 0) {
            item.live_sheets.forEach(function(ls) {
                var statusColor = ls.status === 'locked' ? '#16a34a' : (ls.status === 'approved' ? '#1e40af' : '#e8a838');
                lsHtml += '<div style="display:flex;align-items:center;gap:.4rem;padding:.2rem 0;">' +
                    '<a href="' + ls.url + '" style="font-family:monospace;font-size:.72rem;color:#1e40af;text-decoration:none;font-weight:600;">' + ls.number + '</a>' +
                    '<span style="font-size:.55rem;padding:1px 5px;border-radius:3px;background:' + statusColor + '15;color:' + statusColor + ';font-weight:700;">' + ls.status + '</span>' +
                    '<span style="font-size:.65rem;color:#94a3b8;">Qty: ' + ls.qty + '</span>' +
                    (ls.download_url ? '<a href="' + ls.download_url + '" style="font-size:.62rem;color:#16a34a;text-decoration:none;margin-left:auto;" title="Download XLSX"><i class="fas fa-download"></i> XLSX</a>' : '') +
                    '</div>';
            });
        } else {
            lsHtml = '<span style="font-size:.7rem;color:#94a3b8;">No live sheets</span>';
        }

        html += '<div style="padding:.6rem .75rem;border-bottom:1px solid #f1f5f9;display:flex;gap:.75rem;align-items:flex-start;">' +
            '<div style="flex:1;">' +
                '<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.2rem;">' +
                    '<span style="font-family:monospace;font-weight:700;font-size:.85rem;color:#0d1b2a;">' + item.sku + '</span>' +
                    (item.barcode ? '<span style="font-size:.6rem;color:#94a3b8;font-family:monospace;">' + item.barcode + '</span>' : '') +
                '</div>' +
                '<div style="font-size:.78rem;color:#334155;margin-bottom:.3rem;">' + item.name + '</div>' +
                '<div style="font-size:.68rem;color:#94a3b8;">Vendor: ' + item.vendor + ' · ' + item.company + '</div>' +
            '</div>' +
            '<div style="min-width:200px;">' +
                '<div style="font-size:.65rem;font-weight:600;color:#64748b;margin-bottom:.15rem;">Live Sheets</div>' +
                lsHtml +
            '</div>' +
        '</div>';
    });

    container.innerHTML = html;
}
</script>
@endif

</body>

</html>