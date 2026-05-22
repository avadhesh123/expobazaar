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

        $addCol('chargebacks', 'evidence_file', "VARCHAR(500) NULL");
        $addCol('chargebacks', 'chargeback_items', "JSON NULL COMMENT 'Array of order_item IDs involved'");
    }

    public function down(): void {}
};
