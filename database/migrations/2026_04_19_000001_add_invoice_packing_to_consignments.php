<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $cols = [
            'commercial_invoice_file'   => "VARCHAR(500) NULL",
            'commercial_invoice_number' => "VARCHAR(100) NULL",
            'commercial_invoice_upload_date' => "DATE NULL",
            'commercial_invoice_upload_by'   => "BIGINT UNSIGNED NULL",
            'packing_list_file'         => "VARCHAR(500) NULL",
            'packing_list_number'       => "VARCHAR(100) NULL",
            'packing_list_upload_date'  => "DATE NULL",
            'packing_list_upload_by'    => "BIGINT UNSIGNED NULL",
        ];

        foreach ($cols as $col => $def) {
            $exists = DB::select("SHOW COLUMNS FROM `consignments` WHERE Field = '{$col}'");
            if (empty($exists)) {
                DB::statement("ALTER TABLE `consignments` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    public function down(): void
    {
        $cols = ['commercial_invoice_file', 'commercial_invoice_number', 'commercial_invoice_upload_date', 'commercial_invoice_upload_by',
                 'packing_list_file', 'packing_list_number', 'packing_list_upload_date', 'packing_list_upload_by'];
        foreach ($cols as $col) {
            $exists = DB::select("SHOW COLUMNS FROM `consignments` WHERE Field = '{$col}'");
            if (!empty($exists)) {
                DB::statement("ALTER TABLE `consignments` DROP COLUMN `{$col}`");
            }
        }
    }
};
