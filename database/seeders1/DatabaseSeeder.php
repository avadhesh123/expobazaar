<?php

namespace Database\Seeders;

use App\Models\{User, Role, Permission, Category, SalesChannel, Warehouse};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── PERMISSIONS ─────────────────────────────────────
        $modules = [
            'vendors' => ['view', 'create', 'edit', 'delete', 'approve', 'kyc_review'],
            'products' => ['view', 'create', 'edit', 'delete', 'approve'],
            'offer_sheets' => ['view', 'create', 'edit', 'review', 'select'],
            'consignments' => ['view', 'create', 'edit'],
            'live_sheets' => ['view', 'create', 'edit', 'approve', 'lock', 'unlock'],
            'shipments' => ['view', 'create', 'edit', 'lock'],
            'grn' => ['view', 'create', 'upload', 'verify'],
            'inventory' => ['view', 'transfer', 'download'],
            'cataloguing' => ['view', 'create', 'edit', 'list'],
            'pricing' => ['view', 'create', 'edit', 'approve'],
            'orders' => ['view', 'create', 'edit', 'upload', 'tracking'],
            'finance' => ['view', 'receivables', 'deductions', 'payments', 'chargebacks'],
            'payouts' => ['view', 'calculate', 'approve', 'process'],
            'warehouse_charges' => ['view', 'calculate', 'upload_receipt'],
            'users' => ['view', 'create', 'edit', 'delete'],
            'roles' => ['view', 'create', 'edit'],
            'masters' => ['view', 'create', 'edit'],
            'reports' => ['view', 'download'],
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'web',
                ], [
                    'module' => $module,
                    'display_name' => ucfirst(str_replace('_', ' ', $action)) . ' ' . ucfirst(str_replace('_', ' ', $module)),
                ]);
            }
        }

        // ─── ROLES ───────────────────────────────────────────
        $roles = [
            ['name' => 'admin', 'display_name' => 'System Administrator', 'company_codes' => ['2000', '2100', '2200']],
            ['name' => 'sourcing_manager', 'display_name' => 'Sourcing Manager', 'company_codes' => ['2000', '2100', '2200']],
            ['name' => 'sourcing_executive', 'display_name' => 'Sourcing Executive', 'company_codes' => ['2000', '2100', '2200']],
            ['name' => 'logistics_manager', 'display_name' => 'Logistics Manager', 'company_codes' => ['2000', '2100', '2200']],
            ['name' => 'logistics_executive', 'display_name' => 'Logistics Executive', 'company_codes' => ['2000', '2100', '2200']],
            ['name' => 'cataloguing_manager', 'display_name' => 'Cataloguing Manager', 'company_codes' => ['2000', '2100', '2200']],
            ['name' => 'sales_manager', 'display_name' => 'Sales Manager', 'company_codes' => ['2000', '2100', '2200']],
            ['name' => 'finance_manager', 'display_name' => 'Finance Manager', 'company_codes' => ['2000', '2100', '2200']],
            ['name' => 'finance_executive', 'display_name' => 'Finance Executive', 'company_codes' => ['2000', '2100', '2200']],
            ['name' => 'hod', 'display_name' => 'Head of Department', 'company_codes' => ['2000', '2100', '2200']],
            ['name' => 'vendor', 'display_name' => 'Vendor', 'company_codes' => null],
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(['name' => $roleData['name'], 'guard_name' => 'web'], $roleData);
            if ($role->name === 'admin') {
                $role->permissions()->sync(Permission::all()->pluck('id'));
            }
        }

        // ─── ADMIN USER ─────────────────────────────────────
        $admin = User::firstOrCreate(['email' => 'admin@expobazaar.com'], [
            'name' => 'System Admin',
            'user_type' => 'admin',
            'company_codes' => ['2000', '2100', '2200'],
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // ─── SAMPLE INTERNAL USERS ──────────────────────────
        $teams = [
            ['name' => 'Sourcing Lead', 'email' => 'sourcing@expobazaar.com', 'department' => 'sourcing', 'role' => 'sourcing_manager'],
            ['name' => 'Logistics Lead', 'email' => 'logistics@expobazaar.com', 'department' => 'logistics', 'role' => 'logistics_manager'],
            ['name' => 'Catalogue Lead', 'email' => 'cataloguing@expobazaar.com', 'department' => 'cataloguing', 'role' => 'cataloguing_manager'],
            ['name' => 'Sales Lead', 'email' => 'sales@expobazaar.com', 'department' => 'sales', 'role' => 'sales_manager'],
            ['name' => 'Finance Lead', 'email' => 'finance@expobazaar.com', 'department' => 'finance', 'role' => 'finance_manager'],
            ['name' => 'HOD', 'email' => 'hod@expobazaar.com', 'department' => 'hod', 'role' => 'hod'],
        ];

        foreach ($teams as $team) {
            $user = User::firstOrCreate(['email' => $team['email']], [
                'name' => $team['name'],
                'user_type' => 'internal',
                'department' => $team['department'],
                'company_codes' => ['2000', '2100', '2200'],
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $user->assignRole($team['role']);
        }

        // ─── CATEGORIES ─────────────────────────────────────
        $categories = ['Home Décor', 'Furniture', 'Fashion', 'Textile', 'Kitchen & Dining', 'Garden & Outdoor', 'Lighting', 'Bath & Bedding', 'Art & Collectibles', 'Storage & Organization'];
        foreach ($categories as $cat) {
            Category::firstOrCreate(['slug' => Str::slug($cat)], ['name' => $cat, 'is_active' => true]);
        }

        // ─── SALES CHANNELS ─────────────────────────────────
        $channels = [
            ['name' => 'Amazon', 'type' => 'marketplace'],
            ['name' => 'Wayfair', 'type' => 'marketplace'],
            ['name' => 'Faire', 'type' => 'marketplace'],
            ['name' => 'GIGA', 'type' => 'marketplace'],
            ['name' => 'Shopify', 'type' => 'marketplace'],
            ['name' => 'TICA', 'type' => 'offline'],
            ['name' => 'Coons', 'type' => 'offline'],
        ];
        foreach ($channels as $ch) {
            SalesChannel::firstOrCreate(['slug' => Str::slug($ch['name'])], [
                'name' => $ch['name'], 'type' => $ch['type'], 'is_active' => true,
                'company_codes' => ['2000', '2100', '2200'],
            ]);
        }

        // ─── WAREHOUSES ──────────────────────────────────────
        $warehouses = [
            ['name' => 'India Main Warehouse', 'code' => 'WH-IN-001', 'company_code' => '2000', 'country' => 'India', 'city' => 'Delhi', 'inward_rate_per_cbm' => 50, 'storage_rate_per_cbm_month' => 25, 'pick_pack_rate' => 5, 'consumable_rate' => 2, 'last_mile_rate' => 10],
            ['name' => 'USA Main Warehouse', 'code' => 'WH-US-001', 'company_code' => '2100', 'country' => 'United States', 'city' => 'New Jersey', 'inward_rate_per_cbm' => 80, 'storage_rate_per_cbm_month' => 45, 'pick_pack_rate' => 8, 'consumable_rate' => 3, 'last_mile_rate' => 15],
            ['name' => 'NL Main Warehouse', 'code' => 'WH-NL-001', 'company_code' => '2200', 'country' => 'Netherlands', 'city' => 'Amsterdam', 'inward_rate_per_cbm' => 70, 'storage_rate_per_cbm_month' => 40, 'pick_pack_rate' => 7, 'consumable_rate' => 3, 'last_mile_rate' => 12],
        ];
        foreach ($warehouses as $wh) {
            Warehouse::firstOrCreate(['code' => $wh['code']], array_merge($wh, ['is_active' => true]));
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin login: admin@expobazaar.com (use OTP)');
    }
}
