<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Admin
            ['name' => 'admin.access',        'module' => 'admin',       'display_name' => 'Access Admin Panel'],
            ['name' => 'admin.users',          'module' => 'admin',       'display_name' => 'Manage Users'],
            ['name' => 'admin.vendors',        'module' => 'admin',       'display_name' => 'Vendor Approvals'],
            ['name' => 'admin.roles',          'module' => 'admin',       'display_name' => 'Roles & Permissions'],
            ['name' => 'admin.categories',     'module' => 'admin',       'display_name' => 'Manage Categories'],
            ['name' => 'admin.sales-channels', 'module' => 'admin',       'display_name' => 'Manage Sales Channels'],
            ['name' => 'admin.warehouses',     'module' => 'admin',       'display_name' => 'Manage Warehouses'],
            ['name' => 'admin.activity-log',   'module' => 'admin',       'display_name' => 'View Activity Log'],

            // Sourcing
            ['name' => 'sourcing.access',       'module' => 'sourcing',    'display_name' => 'Access Sourcing Module'],
            ['name' => 'sourcing.vendors',      'module' => 'sourcing',    'display_name' => 'Manage Vendors'],
            ['name' => 'sourcing.offer-sheets', 'module' => 'sourcing',    'display_name' => 'Manage Offer Sheets'],
            ['name' => 'sourcing.live-sheets',  'module' => 'sourcing',    'display_name' => 'Manage Live Sheets'],
            ['name' => 'sourcing.consignments', 'module' => 'sourcing',    'display_name' => 'Manage Consignments'],
            ['name' => 'sourcing.inspections',  'module' => 'sourcing',    'display_name' => 'Manage Inspections'],
            ['name' => 'sourcing.chargebacks',  'module' => 'sourcing',    'display_name' => 'View Chargebacks'],

            // Logistics
            ['name' => 'logistics.access',             'module' => 'logistics',   'display_name' => 'Access Logistics Module'],
            ['name' => 'logistics.container-planning',  'module' => 'logistics',   'display_name' => 'Container Planning'],
            ['name' => 'logistics.shipments',           'module' => 'logistics',   'display_name' => 'Manage Shipments'],
            ['name' => 'logistics.grn',                 'module' => 'logistics',   'display_name' => 'Goods Receipt Notes'],
            ['name' => 'logistics.inventory',           'module' => 'logistics',   'display_name' => 'Inventory Management'],
            ['name' => 'logistics.warehouse-charges',   'module' => 'logistics',   'display_name' => 'Warehouse Charges'],

            // Cataloguing
            ['name' => 'cataloguing.access',          'module' => 'cataloguing', 'display_name' => 'Access Cataloguing Module'],
            ['name' => 'cataloguing.pricing-sheets',  'module' => 'cataloguing', 'display_name' => 'Pricing Sheets'],
            ['name' => 'cataloguing.listing-panel',   'module' => 'cataloguing', 'display_name' => 'Listing Panel'],
            ['name' => 'cataloguing.sku-dashboard',   'module' => 'cataloguing', 'display_name' => 'SKU Dashboard'],

            // Sales
            ['name' => 'sales.access',  'module' => 'sales',      'display_name' => 'Access Sales Module'],
            ['name' => 'sales.orders',  'module' => 'sales',      'display_name' => 'Manage Orders'],
            ['name' => 'sales.upload',  'module' => 'sales',      'display_name' => 'Upload Sales Data'],

            // Finance
            ['name' => 'finance.access',          'module' => 'finance',     'display_name' => 'Access Finance Module'],
            ['name' => 'finance.kyc',             'module' => 'finance',     'display_name' => 'KYC Review'],
            ['name' => 'finance.live-sheets',     'module' => 'finance',     'display_name' => 'Live Sheets / SAP Codes'],
            ['name' => 'finance.receivables',     'module' => 'finance',     'display_name' => 'Receivables'],
            ['name' => 'finance.chargebacks',     'module' => 'finance',     'display_name' => 'Chargebacks'],
            ['name' => 'finance.payouts',         'module' => 'finance',     'display_name' => 'Vendor Payouts'],
            ['name' => 'finance.pricing-review',  'module' => 'finance',     'display_name' => 'Pricing Review'],

            // HOD
            ['name' => 'hod.access',    'module' => 'hod',        'display_name' => 'Access Management Module'],
            ['name' => 'hod.asn-list',  'module' => 'hod',        'display_name' => 'ASN & Pricing'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(
                ['name' => $p['name']],
                ['module' => $p['module'], 'display_name' => $p['display_name'], 'guard_name' => 'web']
            );
        }

        // Auto-assign all permissions to existing department users
        $this->assignDepartmentDefaults();
    }

    private function assignDepartmentDefaults(): void
    {
        $deptMap = [
            'sourcing'    => ['sourcing.access', 'sourcing.vendors', 'sourcing.offer-sheets', 'sourcing.live-sheets', 'sourcing.consignments', 'sourcing.inspections', 'sourcing.chargebacks'],
            'logistics'   => ['logistics.access', 'logistics.container-planning', 'logistics.shipments', 'logistics.grn', 'logistics.inventory', 'logistics.warehouse-charges'],
            'cataloguing' => ['cataloguing.access', 'cataloguing.pricing-sheets', 'cataloguing.listing-panel', 'cataloguing.sku-dashboard'],
            'sales'       => ['sales.access', 'sales.orders', 'sales.upload'],
            'finance'     => ['finance.access', 'finance.kyc', 'finance.live-sheets', 'finance.receivables', 'finance.chargebacks', 'finance.payouts', 'finance.pricing-review'],
            'hod'         => ['hod.access', 'hod.asn-list'],
        ];

        foreach ($deptMap as $dept => $perms) {
            $users = \App\Models\User::where('department', $dept)->where('user_type', 'internal')->get();
            $permIds = Permission::whereIn('name', $perms)->pluck('id');
            foreach ($users as $user) {
                $user->permissions()->syncWithoutDetaching($permIds);
            }
        }
    }
}
