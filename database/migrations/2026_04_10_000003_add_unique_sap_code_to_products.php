<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure sap_code column exists first
        $exists = DB::select("SHOW COLUMNS FROM `products` WHERE Field = 'sap_code'");
        if (empty($exists)) {
            DB::statement("ALTER TABLE `products` ADD COLUMN `sap_code` VARCHAR(50) NULL AFTER `sku`");
        }

        // Clean up duplicates: keep only the first occurrence, null out the rest
        DB::statement("
            UPDATE products p1
            JOIN products p2 ON p1.sap_code = p2.sap_code AND p1.id > p2.id
            SET p1.sap_code = NULL
            WHERE p1.sap_code IS NOT NULL AND p1.sap_code != ''
        ");

        // Add unique index (NULL values are allowed and don't conflict in MySQL)
        $hasIdx = DB::select("SHOW INDEX FROM `products` WHERE Key_name = 'products_sap_code_unique'");
        if (empty($hasIdx)) {
            DB::statement("ALTER TABLE `products` ADD UNIQUE INDEX `products_sap_code_unique` (`sap_code`)");
        }
    }

    public function down(): void
    {
        $hasIdx = DB::select("SHOW INDEX FROM `products` WHERE Key_name = 'products_sap_code_unique'");
        if (!empty($hasIdx)) {
            DB::statement("ALTER TABLE `products` DROP INDEX `products_sap_code_unique`");
        }
    }
};
