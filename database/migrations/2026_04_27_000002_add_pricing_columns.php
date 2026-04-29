<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $addCol = function($col, $def) {
            $exists = DB::select("SHOW COLUMNS FROM `platform_pricings` WHERE Field = '{$col}'");
            if (empty($exists)) {
                DB::statement("ALTER TABLE `platform_pricings` ADD COLUMN `{$col}` {$def}");
            }
        };
        $addCol('last_mile', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        $addCol('retail_price', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        $addCol('pricing_factor', "DECIMAL(6,4) NOT NULL DEFAULT 1.0000");
        $addCol('channel_price', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        $addCol('fob_price', "DECIMAL(10,2) NOT NULL DEFAULT 0");
        $addCol('wsp_price', "DECIMAL(10,2) NOT NULL DEFAULT 0");

        // Add pricing_factors JSON to sales_channels if missing
        $exists = DB::select("SHOW COLUMNS FROM `sales_channels` WHERE Field = 'pricing_factors'");
        if (empty($exists)) {
            DB::statement("ALTER TABLE `sales_channels` ADD COLUMN `pricing_factors` JSON NULL");
        }
    }

    public function down(): void {}
};
