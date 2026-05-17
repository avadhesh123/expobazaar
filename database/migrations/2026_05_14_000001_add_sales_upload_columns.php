<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $addCol = function($table, $col, $def) {
            $exists = DB::select("SHOW COLUMNS FROM `{$table}` WHERE Field = '{$col}'");
            if (empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
            }
        };

        // Orders table — add missing columns from sales Excel
        $addCol('orders', 'invoice_number', "VARCHAR(100) NULL");
        $addCol('orders', 'customer_phone', "VARCHAR(50) NULL");
        $addCol('orders', 'customer_type', "VARCHAR(50) NULL");
        $addCol('orders', 'company_name', "VARCHAR(200) NULL");
        $addCol('orders', 'shipping_method', "VARCHAR(100) NULL COMMENT '1=Store Pickup, 2=Marketplace Label, 3=Seller Label'");
        $addCol('orders', 'warehouse_id', "BIGINT UNSIGNED NULL");

        // Order items — add material_cost for warehouse charges calculation
        $addCol('order_items', 'material_cost', "DECIMAL(10,2) NULL");
        $addCol('order_items', 'consignment_id', "BIGINT UNSIGNED NULL");
    }

    public function down(): void {}
};
