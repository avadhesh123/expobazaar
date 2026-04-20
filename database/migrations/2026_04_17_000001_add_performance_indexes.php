<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Helper to add index only if it doesn't exist
        $addIndex = function (string $table, string $column, string $type = 'INDEX') {
            $indexName = "{$table}_{$column}_index";
            $exists = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'");
            if (empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` ADD {$type} `{$indexName}` (`{$column}`)");
            }
        };

        $addComposite = function (string $table, array $columns, string $name) {
            $exists = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'");
            if (empty($exists)) {
                $cols = '`' . implode('`,`', $columns) . '`';
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$cols})");
            }
        };

        // ── vendors ────────────────────────────────────────────────────
        $addIndex('vendors', 'status');
        $addIndex('vendors', 'company_code');
        $addIndex('vendors', 'vendor_code');
        $addIndex('vendors', 'kyc_status');

        // ── products ───────────────────────────────────────────────────
        $addIndex('products', 'vendor_id');
        $addIndex('products', 'company_code');
        $addIndex('products', 'status');
        $addIndex('products', 'category_id');

        // ── offer_sheets ───────────────────────────────────────────────
        $addIndex('offer_sheets', 'vendor_id');
        $addIndex('offer_sheets', 'status');
        $addIndex('offer_sheets', 'company_code');

        // ── live_sheets ────────────────────────────────────────────────
        $addIndex('live_sheets', 'vendor_id');
        $addIndex('live_sheets', 'status');
        $addIndex('live_sheets', 'company_code');
        $addIndex('live_sheets', 'is_locked');
        $addIndex('live_sheets', 'consignment_id');

        // ── live_sheet_items ───────────────────────────────────────────
        $addIndex('live_sheet_items', 'live_sheet_id');
        $addIndex('live_sheet_items', 'product_id');
        $addIndex('live_sheet_items', 'consignment_id');
        $addIndex('live_sheet_items', 'is_selected');

        // ── consignments ───────────────────────────────────────────────
        $addIndex('consignments', 'vendor_id');
        $addIndex('consignments', 'status');
        $addIndex('consignments', 'company_code');
        $addIndex('consignments', 'live_sheet_id');

        // ── shipments ──────────────────────────────────────────────────
       // $addIndex('shipments', 'consignment_id');
        $addIndex('shipments', 'status');

        // ── orders ─────────────────────────────────────────────────────
        $addIndex('orders', 'company_code');
        $addIndex('orders', 'status');
        $addIndex('orders', 'payment_status');
        $addIndex('orders', 'sales_channel_id');
        $addIndex('orders', 'order_date');
        $addComposite('orders', ['platform_order_id', 'sales_channel_id'], 'orders_platform_channel');

        // ── order_items ────────────────────────────────────────────────
        $addIndex('order_items', 'order_id');
        $addIndex('order_items', 'product_id');

        // ── product_catalogues ─────────────────────────────────────────
        $addComposite('product_catalogues', ['product_id', 'sales_channel_id'], 'prodcat_product_channel');
        $addIndex('product_catalogues', 'listing_status');
        $addIndex('product_catalogues', 'company_code');

        // ── platform_pricings ──────────────────────────────────────────
        // $addIndex('platform_pricings', 'status');
        // $addIndex('platform_pricings', 'company_code');
        // $addIndex('platform_pricings', 'sales_channel_id');

        // ── vendor_payouts ─────────────────────────────────────────────
        $addIndex('vendor_payouts', 'vendor_id');
        $addIndex('vendor_payouts', 'status');
        $addComposite('vendor_payouts', ['payout_month', 'payout_year'], 'payouts_period');

        // ── chargebacks ────────────────────────────────────────────────
        $addIndex('chargebacks', 'order_id');
        $addIndex('chargebacks', 'status');

        // ── finance_receivables ────────────────────────────────────────
        $addIndex('finance_receivables', 'order_id');
        // $addIndex('finance_receivables', 'status');

        // ── activity_logs ──────────────────────────────────────────────
        $addIndex('activity_logs', 'user_id');
        $addIndex('activity_logs', 'module');
        $addIndex('activity_logs', 'action');
        $addIndex('activity_logs', 'created_at');

        // ── notifications ──────────────────────────────────────────────
        $addIndex('notifications', 'notifiable_id');
        $addComposite('notifications', ['notifiable_id', 'read_at'], 'notif_user_read');

        // ── users ──────────────────────────────────────────────────────
        $addIndex('users', 'user_type');
        $addIndex('users', 'department');
        // $addIndex('users', 'is_active');
    }

    public function down(): void
    {
        // Indexes are additive, no need to drop in down()
    }
};
