<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::select("SHOW TABLES LIKE 'wsp_revisions'");
        if (empty($exists)) {
            DB::statement("
                CREATE TABLE wsp_revisions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    live_sheet_id BIGINT UNSIGNED NOT NULL,
                    live_sheet_item_id BIGINT UNSIGNED NULL COMMENT 'NULL = applies to all items in this live sheet',
                    product_id BIGINT UNSIGNED NULL,
                    vendor_id BIGINT UNSIGNED NOT NULL,
                    company_code VARCHAR(10) NOT NULL,
                    vendor_wsp DECIMAL(12,2) NOT NULL DEFAULT 0,
                    effective_from DATE NOT NULL,
                    effective_to DATE NULL COMMENT 'NULL = active until next revision',
                    remarks VARCHAR(500) NULL,
                    created_by BIGINT UNSIGNED NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_live_sheet (live_sheet_id),
                    INDEX idx_product (product_id),
                    INDEX idx_vendor (vendor_id),
                    INDEX idx_effective (effective_from, effective_to),
                    FOREIGN KEY (live_sheet_id) REFERENCES live_sheets(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    public function down(): void {}
};
