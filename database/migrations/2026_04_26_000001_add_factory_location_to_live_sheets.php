<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $addCol = function($col, $def) {
            $exists = DB::select("SHOW COLUMNS FROM `live_sheets` WHERE Field = '{$col}'");
            if (empty($exists)) {
                DB::statement("ALTER TABLE `live_sheets` ADD COLUMN `{$col}` {$def}");
            }
        };
        $addCol('factory_location', "VARCHAR(500) NULL AFTER `final_inspection_date`");
        $addCol('goods_ready_date', "DATE NULL AFTER `factory_location`");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `live_sheets` DROP COLUMN IF EXISTS `factory_location`");
        DB::statement("ALTER TABLE `live_sheets` DROP COLUMN IF EXISTS `goods_ready_date`");
    }
};
