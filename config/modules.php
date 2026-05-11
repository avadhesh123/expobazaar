<?php

// config/modules.php — Single source of truth for permissions + sidebar
// 'route' maps to the actual Laravel route name (module prefix auto-added)
// 'icon' per entity overrides the module icon in sidebar

return [
    'admin' => [
        'label' => 'Administration',
        'icon'  => 'fas fa-cog',
        'entities' => [
            'access'        => ['label' => 'Access Admin Panel',    'actions' => ['view'], 'route' => 'admin.dashboard',         'icon' => 'fas fa-tachometer-alt'],
            'user'          => ['label' => 'Users',                 'actions' => ['view', 'create', 'edit', 'delete'], 'route' => 'admin.users',         'icon' => 'fas fa-users-cog'],
            'vendor'        => ['label' => 'Vendor Approvals',      'actions' => ['view', 'approve'],                  'route' => 'admin.vendors.pending','icon' => 'fas fa-user-check'],
            'role'          => ['label' => 'Roles & Permissions',   'actions' => ['view', 'create', 'edit', 'delete'], 'route' => 'admin.roles',         'icon' => 'fas fa-shield-alt'],
            'category'      => ['label' => 'Categories',            'actions' => ['view', 'create', 'edit'],           'route' => 'admin.categories',    'icon' => 'fas fa-sitemap'],
            'sales-channel' => ['label' => 'Sales Channels',        'actions' => ['view', 'create', 'edit'],           'route' => 'admin.sales-channels','icon' => 'fas fa-store'],
            'warehouse'     => ['label' => 'Warehouses',            'actions' => ['view', 'create', 'edit'],           'route' => 'admin.warehouses',    'icon' => 'fas fa-warehouse'],
            'activity-log'  => ['label' => 'Activity Log',          'actions' => ['view'],                             'route' => 'admin.activity-log',  'icon' => 'fas fa-history'],
        ],
    ],

    'sourcing' => [
        'label' => 'Sourcing',
        'icon'  => 'fas fa-search',
        'entities' => [
            'access'       => ['label' => 'Access Sourcing Module',  'actions' => ['view'], 'route' => 'sourcing.dashboard',     'icon' => 'fas fa-tachometer-alt', 'sidebar' => false],
            'dashboard'    => ['label' => 'Sourcing Dashboard',      'actions' => ['view'], 'route' => 'sourcing.dashboard',     'icon' => 'fas fa-tachometer-alt'],
            'vendor'       => ['label' => 'Manage Vendors',          'actions' => ['view', 'create'],  'route' => 'sourcing.vendors',       'icon' => 'fas fa-users'],
            'offer-sheet'  => ['label' => 'Offer Sheets',            'actions' => ['view', 'review'],  'route' => 'sourcing.offer-sheets',  'icon' => 'fas fa-file-alt'],
            'live-sheet'   => ['label' => 'Live Sheets',             'actions' => ['view', 'update', 'approve', 'history'], 'route' => 'sourcing.live-sheets', 'icon' => 'fas fa-clipboard-list'],
            'consignment'  => ['label' => 'Consignments',            'actions' => ['view', 'create'],  'route' => 'sourcing.consignments',  'icon' => 'fas fa-box'],
            'inspection'   => ['label' => 'Inspections',             'actions' => ['view', 'upload'],  'route' => 'sourcing.inspections',   'icon' => 'fas fa-search'],
            'chargeback'   => ['label' => 'Chargeback Confirmation', 'actions' => ['view'],            'route' => 'sourcing.chargebacks',   'icon' => 'fas fa-exclamation-triangle'],
        ],
    ],

    'logistics' => [
        'label' => 'Logistics',
        'icon'  => 'fas fa-truck',
        'entities' => [
            'access'              => ['label' => 'Access Logistics Module',  'actions' => ['view'], 'route' => 'logistics.dashboard',             'icon' => 'fas fa-tachometer-alt', 'sidebar' => false],
            'dashboard'           => ['label' => 'Logistics Dashboard',      'actions' => ['view'], 'route' => 'logistics.dashboard',             'icon' => 'fas fa-tachometer-alt'],
            'container-planning'  => ['label' => 'Container Planning',       'actions' => ['view'], 'route' => 'logistics.container-planning',    'icon' => 'fas fa-cubes'],
            'shipment'            => ['label' => 'Shipments',                'actions' => ['view', 'create', 'lock'],   'route' => 'logistics.shipments',    'icon' => 'fas fa-ship'],
            'grn'                 => ['label' => 'GRN Management',           'actions' => ['view', 'upload'],           'route' => 'logistics.grn',          'icon' => 'fas fa-clipboard-check'],
            'inventory'           => ['label' => 'Inventory Management',     'actions' => ['view', 'transfer', 'ageing'], 'route' => 'logistics.inventory', 'icon' => 'fas fa-boxes'],
            'warehouse-charge'    => ['label' => 'Warehouse Charges',        'actions' => ['view', 'run', 'approve'],   'route' => 'logistics.warehouse-charges', 'icon' => 'fas fa-calculator'],
            'warehouse-rate-card' => ['label' => 'WH Rate Card',             'actions' => ['view'],                     'route' => 'logistics.warehouse-rate-cards', 'icon' => 'fas fa-file-contract'],
            'wh-charge-recon'     => ['label' => 'WH Charges & Recon',       'actions' => ['view', 'run', 'approve'],   'route' => 'logistics.warehouse-monthly-charges', 'icon' => 'fas fa-receipt'],
            'vendor-rate-card'    => ['label' => 'Vendor Rate Cards',        'actions' => ['view'],                     'route' => 'logistics.vendor-rate-cards', 'icon' => 'fas fa-users-cog'],
        ],
    ],

    'cataloguing' => [
        'label' => 'Cataloguing',
        'icon'  => 'fas fa-tags',
        'entities' => [
            'access'        => ['label' => 'Access Cataloguing Module',  'actions' => ['view'], 'route' => 'cataloguing.dashboard',      'icon' => 'fas fa-tachometer-alt', 'sidebar' => false],
            'dashboard'     => ['label' => 'Cataloguing Dashboard',      'actions' => ['view'], 'route' => 'cataloguing.dashboard',      'icon' => 'fas fa-tachometer-alt'],
            'pricing-sheet' => ['label' => 'Pricing Sheets',             'actions' => ['view', 'download'], 'route' => 'cataloguing.pricing-sheets', 'icon' => 'fas fa-dollar-sign'],
            'listing-panel' => ['label' => 'Listing Panel',              'actions' => ['view', 'update'],   'route' => 'cataloguing.listing-panel',  'icon' => 'fas fa-list'],
            'sku-dashboard' => ['label' => 'SKU Dashboard',              'actions' => ['view'],             'route' => 'cataloguing.sku-dashboard',  'icon' => 'fas fa-chart-bar'],
        ],
    ],

    'sales' => [
        'label' => 'Sales',
        'icon'  => 'fas fa-shopping-cart',
        'entities' => [
            'access'    => ['label' => 'Access Sales Module',           'actions' => ['view'], 'route' => 'sales.dashboard',  'icon' => 'fas fa-tachometer-alt', 'sidebar' => false],
            'dashboard' => ['label' => 'Sales Dashboard',               'actions' => ['view'], 'route' => 'sales.dashboard',  'icon' => 'fas fa-tachometer-alt'],
            'order'     => ['label' => 'Orders',                        'actions' => ['view'], 'route' => 'sales.orders',     'icon' => 'fas fa-shopping-cart'],
            'upload'    => ['label' => 'Upload Sales Data',             'actions' => ['view'], 'route' => 'sales.upload',     'icon' => 'fas fa-upload'],
            'tracking'  => ['label' => 'Update Tracking',               'actions' => ['update'], 'route' => null, 'sidebar' => false],
        ],
    ],

    'finance' => [
        'label' => 'Finance',
        'icon'  => 'fas fa-money-bill',
        'entities' => [
            'access'           => ['label' => 'Access Finance Module',   'actions' => ['view'], 'route' => 'finance.dashboard',         'icon' => 'fas fa-tachometer-alt', 'sidebar' => false],
            'dashboard'        => ['label' => 'Finance Dashboard',       'actions' => ['view'], 'route' => 'finance.dashboard',         'icon' => 'fas fa-tachometer-alt'],
            'kyc'              => ['label' => 'KYC Review',              'actions' => ['view', 'approve'],              'route' => 'finance.kyc',              'icon' => 'fas fa-id-card'],
            'receivable'       => ['label' => 'Receivables',             'actions' => ['view', 'edit', 'payment'],      'route' => 'finance.receivables',      'icon' => 'fas fa-hand-holding-usd'],
            'chargeback'       => ['label' => 'Chargebacks',             'actions' => ['view', 'create'],               'route' => 'finance.chargebacks',      'icon' => 'fas fa-exclamation-triangle'],
            'payout'           => ['label' => 'Vendor Payouts',          'actions' => ['view', 'calculate', 'process', 'download'], 'route' => 'finance.payouts', 'icon' => 'fas fa-money-check-alt'],
            'pricing-review'   => ['label' => 'Pricing Review',          'actions' => ['view', 'approve'],              'route' => 'finance.pricing-review',   'icon' => 'fas fa-file-invoice-dollar'],
            'live-sheet'       => ['label' => 'Live Sheets & SAP',       'actions' => ['view', 'sap'],                  'route' => 'finance.live-sheets',      'icon' => 'fas fa-clipboard-list'],
            'vendor-rate-card' => ['label' => 'Vendor Rate Cards',       'actions' => ['view'],                         'route' => 'finance.vendor-rate-cards','icon' => 'fas fa-tags'],
            'vendor-charge'    => ['label' => 'Vendor Charges',          'actions' => ['view'],                         'route' => 'finance.vendor-charges',   'icon' => 'fas fa-calculator'],
        ],
    ],

    'hod' => [
        'label' => 'Management',
        'icon'  => 'fas fa-chart-line',
        'entities' => [
            'access' => ['label' => 'Access Management Module', 'actions' => ['view'], 'route' => 'hod.dashboard',  'icon' => 'fas fa-tachometer-alt', 'sidebar' => false],
            'dashboard' => ['label' => 'Dashboard',             'actions' => ['view'], 'route' => 'hod.dashboard',  'icon' => 'fas fa-tachometer-alt'],
            'asn'    => ['label' => 'ASN & Pricing',            'actions' => ['view', 'create', 'approve'], 'route' => 'hod.asn-list', 'icon' => 'fas fa-file-alt'],
        ],
    ],

    'vendor' => [
        'label' => 'Vendor Portal',
        'icon'  => 'fas fa-store',
        'entities' => [
            'access'      => ['label' => 'Access Vendor Portal',    'actions' => ['view'], 'route' => 'vendor.dashboard',     'icon' => 'fas fa-tachometer-alt', 'sidebar' => false],
            'dashboard'   => ['label' => 'Dashboard',               'actions' => ['view'], 'route' => 'vendor.dashboard',     'icon' => 'fas fa-tachometer-alt'],
            'kyc'         => ['label' => 'KYC Submission',          'actions' => ['view'], 'route' => 'vendor.kyc',           'icon' => 'fas fa-id-card'],
            'offer-sheet' => ['label' => 'Offer Sheets',            'actions' => ['view'], 'route' => 'vendor.offer-sheets',  'icon' => 'fas fa-file-alt'],
            'live-sheet'  => ['label' => 'Live Sheets',             'actions' => ['view', 'upload', 'dates'], 'route' => 'vendor.live-sheets', 'icon' => 'fas fa-clipboard-list'],
            'consignment' => ['label' => 'Consignments',            'actions' => ['view'], 'route' => 'vendor.consignments',  'icon' => 'fas fa-box'],
            'inspection'  => ['label' => 'Inspections',             'actions' => ['upload'], 'route' => 'vendor.inspections.index', 'icon' => 'fas fa-search'],
            'grn'         => ['label' => 'GRN',                     'actions' => ['view'], 'route' => 'vendor.grn',           'icon' => 'fas fa-clipboard-check'],
            'inventory'   => ['label' => 'Inventory',               'actions' => ['view'], 'route' => 'vendor.inventory',     'icon' => 'fas fa-boxes'],
            'rate-card'   => ['label' => 'Rate Card',               'actions' => ['view'], 'route' => 'vendor.rate-card',     'icon' => 'fas fa-file-invoice-dollar'],
            'payout'      => ['label' => 'Payouts',                 'actions' => ['view', 'invoice'], 'route' => 'vendor.payouts', 'icon' => 'fas fa-money-check-alt'],
            'chargeback'  => ['label' => 'Chargebacks',             'actions' => ['view'], 'route' => 'vendor.chargebacks',   'icon' => 'fas fa-exclamation-triangle'],
            'sale'        => ['label' => 'Sales Report',            'actions' => ['view'], 'route' => 'vendor.sales',         'icon' => 'fas fa-chart-bar'],
        ],
    ],
];
