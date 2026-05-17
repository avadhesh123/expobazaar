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

        $addCol('orders', 'shipped_qty', "INT NULL");
        $addCol('orders', 'shipped_amount', "DECIMAL(12,2) NULL");
        $addCol('orders', 'shipping_cost', "DECIMAL(10,2) NULL");
        $addCol('orders', 'carrier', "VARCHAR(50) NULL COMMENT 'Fedex, UPS, USPS, LTL, Other'");
    }

    public function down(): void {}
};
