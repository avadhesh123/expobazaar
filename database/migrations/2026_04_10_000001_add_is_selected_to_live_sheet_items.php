<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::select("SHOW COLUMNS FROM `live_sheet_items` WHERE Field = 'is_selected'");
        if (empty($exists)) {
            DB::statement("ALTER TABLE `live_sheet_items` ADD COLUMN `is_selected` TINYINT(1) NOT NULL DEFAULT 1");
        }
    }

    public function down(): void
    {
        $exists = DB::select("SHOW COLUMNS FROM `live_sheet_items` WHERE Field = 'is_selected'");
        if (!empty($exists)) {
            DB::statement("ALTER TABLE `live_sheet_items` DROP COLUMN `is_selected`");
        }
    }
};
