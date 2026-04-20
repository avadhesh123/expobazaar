<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `live_sheet_item_changes` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `live_sheet_item_id` BIGINT UNSIGNED NOT NULL,
                `live_sheet_id` BIGINT UNSIGNED NOT NULL,
                `product_id` BIGINT UNSIGNED NULL,
                `field_name` VARCHAR(50) NOT NULL,
                `old_value` VARCHAR(255) NULL,
                `new_value` VARCHAR(255) NULL,
                `changed_by` BIGINT UNSIGNED NOT NULL,
                `changed_by_role` VARCHAR(20) NOT NULL COMMENT 'vendor or sourcing or finance or admin',
                `change_reason` VARCHAR(500) NULL,
                `revision_number` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_item` (`live_sheet_item_id`),
                INDEX `idx_sheet` (`live_sheet_id`),
                INDEX `idx_field` (`field_name`),
                INDEX `idx_user` (`changed_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `live_sheet_item_changes`');
    }
};
