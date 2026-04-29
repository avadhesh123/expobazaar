<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $addCol = function($col, $def) {
            $exists = DB::select("SHOW COLUMNS FROM `consignments` WHERE Field = '{$col}'");
            if (empty($exists)) {
                DB::statement("ALTER TABLE `consignments` ADD COLUMN `{$col}` {$def}");
            }
        };

        // Shipping Bill
        $addCol('shipping_bill_file', "VARCHAR(500) NULL");
        $addCol('shipping_bill_number', "VARCHAR(100) NULL");
        $addCol('shipping_bill_upload_date', "DATE NULL");
        $addCol('shipping_bill_upload_by', "BIGINT UNSIGNED NULL");

        // Measurement Copy
        $addCol('measurement_file', "VARCHAR(500) NULL");
        $addCol('measurement_number', "VARCHAR(100) NULL");
        $addCol('measurement_upload_date', "DATE NULL");
        $addCol('measurement_upload_by', "BIGINT UNSIGNED NULL");

        // HBL Copy
        $addCol('hbl_file', "VARCHAR(500) NULL");
        $addCol('hbl_number', "VARCHAR(100) NULL");
        $addCol('hbl_upload_date', "DATE NULL");
        $addCol('hbl_upload_by', "BIGINT UNSIGNED NULL");

        // Other Document
        $addCol('other_doc_file', "VARCHAR(500) NULL");
        $addCol('other_doc_name', "VARCHAR(200) NULL");
        $addCol('other_doc_upload_date', "DATE NULL");
        $addCol('other_doc_upload_by', "BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        $cols = [
            'shipping_bill_file','shipping_bill_number','shipping_bill_upload_date','shipping_bill_upload_by',
            'measurement_file','measurement_number','measurement_upload_date','measurement_upload_by',
            'hbl_file','hbl_number','hbl_upload_date','hbl_upload_by',
            'other_doc_file','other_doc_name','other_doc_upload_date','other_doc_upload_by',
        ];
        foreach ($cols as $col) {
            $exists = DB::select("SHOW COLUMNS FROM `consignments` WHERE Field = '{$col}'");
            if (!empty($exists)) {
                DB::statement("ALTER TABLE `consignments` DROP COLUMN `{$col}`");
            }
        }
    }
};
