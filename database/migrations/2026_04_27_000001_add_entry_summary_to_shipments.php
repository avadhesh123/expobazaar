<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $addCol = function($col, $def) {
            $exists = DB::select("SHOW COLUMNS FROM `shipments` WHERE Field = '{$col}'");
            if (empty($exists)) {
                DB::statement("ALTER TABLE `shipments` ADD COLUMN `{$col}` {$def}");
            }
        };
        $addCol('entry_summary_file', "VARCHAR(500) NULL");
        $addCol('entry_summary_number', "VARCHAR(100) NULL");
        $addCol('entry_summary_date', "DATE NULL");
        $addCol('entry_summary_upload_by', "BIGINT UNSIGNED NULL");
        $addCol('entry_summary_upload_date', "DATE NULL");
    }

    public function down(): void
    {
        $cols = ['entry_summary_file','entry_summary_number','entry_summary_date','entry_summary_upload_by','entry_summary_upload_date'];
        foreach ($cols as $col) {
            $exists = DB::select("SHOW COLUMNS FROM `shipments` WHERE Field = '{$col}'");
            if (!empty($exists)) DB::statement("ALTER TABLE `shipments` DROP COLUMN `{$col}`");
        }
    }
};
