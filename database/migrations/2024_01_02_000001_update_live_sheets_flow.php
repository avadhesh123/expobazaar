<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        // 1. live_sheets: make consignment_id nullable, add new columns
        DB::statement('ALTER TABLE live_sheets DROP FOREIGN KEY live_sheets_consignment_id_foreign');
        DB::statement('ALTER TABLE live_sheets DROP COLUMN consignment_id');
        DB::statement('ALTER TABLE live_sheets ADD COLUMN consignment_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE live_sheets ADD CONSTRAINT live_sheets_consignment_id_foreign FOREIGN KEY (consignment_id) REFERENCES consignments(id) ON DELETE SET NULL');

        if (!$this->columnExists('live_sheets', 'offer_sheet_id')) {
            DB::statement('ALTER TABLE live_sheets ADD COLUMN offer_sheet_id BIGINT UNSIGNED NULL AFTER consignment_id');
            DB::statement('ALTER TABLE live_sheets ADD CONSTRAINT live_sheets_offer_sheet_id_foreign FOREIGN KEY (offer_sheet_id) REFERENCES offer_sheets(id) ON DELETE SET NULL');
        }
        if (!$this->columnExists('live_sheets', 'vendor_id')) {
            DB::statement('ALTER TABLE live_sheets ADD COLUMN vendor_id BIGINT UNSIGNED NULL AFTER offer_sheet_id');
            DB::statement('ALTER TABLE live_sheets ADD CONSTRAINT live_sheets_vendor_id_foreign FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE');
        }
        if (!$this->columnExists('live_sheets', 'company_code')) {
            DB::statement('ALTER TABLE live_sheets ADD COLUMN company_code VARCHAR(4) NULL AFTER vendor_id');
        }

        // 2. live_sheet_items: make consignment_id nullable
        DB::statement('ALTER TABLE live_sheet_items DROP FOREIGN KEY live_sheet_items_consignment_id_foreign');
        DB::statement('ALTER TABLE live_sheet_items DROP COLUMN consignment_id');
        DB::statement('ALTER TABLE live_sheet_items ADD COLUMN consignment_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE live_sheet_items ADD CONSTRAINT live_sheet_items_consignment_id_foreign FOREIGN KEY (consignment_id) REFERENCES consignments(id) ON DELETE SET NULL');

        // 3. consignments: add live_sheet_id
        if (!$this->columnExists('consignments', 'live_sheet_id')) {
            DB::statement('ALTER TABLE consignments ADD COLUMN live_sheet_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE consignments ADD CONSTRAINT consignments_live_sheet_id_foreign FOREIGN KEY (live_sheet_id) REFERENCES live_sheets(id) ON DELETE SET NULL');
        }
    }

    public function down(): void
    {
        if ($this->columnExists('consignments', 'live_sheet_id')) {
            DB::statement('ALTER TABLE consignments DROP FOREIGN KEY consignments_live_sheet_id_foreign');
            DB::statement('ALTER TABLE consignments DROP COLUMN live_sheet_id');
        }

        DB::statement('ALTER TABLE live_sheet_items DROP FOREIGN KEY live_sheet_items_consignment_id_foreign');
        DB::statement('ALTER TABLE live_sheet_items DROP COLUMN consignment_id');
        DB::statement('ALTER TABLE live_sheet_items ADD COLUMN consignment_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE live_sheet_items ADD CONSTRAINT live_sheet_items_consignment_id_foreign FOREIGN KEY (consignment_id) REFERENCES consignments(id) ON DELETE CASCADE');

        if ($this->columnExists('live_sheets', 'company_code')) {
            DB::statement('ALTER TABLE live_sheets DROP COLUMN company_code');
        }
        if ($this->columnExists('live_sheets', 'vendor_id')) {
            DB::statement('ALTER TABLE live_sheets DROP FOREIGN KEY live_sheets_vendor_id_foreign');
            DB::statement('ALTER TABLE live_sheets DROP COLUMN vendor_id');
        }
        if ($this->columnExists('live_sheets', 'offer_sheet_id')) {
            DB::statement('ALTER TABLE live_sheets DROP FOREIGN KEY live_sheets_offer_sheet_id_foreign');
            DB::statement('ALTER TABLE live_sheets DROP COLUMN offer_sheet_id');
        }

        DB::statement('ALTER TABLE live_sheets DROP FOREIGN KEY live_sheets_consignment_id_foreign');
        DB::statement('ALTER TABLE live_sheets DROP COLUMN consignment_id');
        DB::statement('ALTER TABLE live_sheets ADD COLUMN consignment_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE live_sheets ADD CONSTRAINT live_sheets_consignment_id_foreign FOREIGN KEY (consignment_id) REFERENCES consignments(id) ON DELETE CASCADE');
    }

    /**
     * Check if column exists using SHOW COLUMNS — no information_schema query
     */
    private function columnExists(string $table, string $column): bool
    {

$result = DB::select("SHOW COLUMNS FROM `{$table}` WHERE Field = '{$column}'");
        return count($result) > 0;
    }
};
