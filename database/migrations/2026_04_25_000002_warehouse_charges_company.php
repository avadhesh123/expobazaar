<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Warehouse Rate Card (company-level, what warehouse charges us)
        $exists = DB::select("SHOW TABLES LIKE 'warehouse_rate_cards'");
        if (empty($exists)) {
            DB::statement("CREATE TABLE `warehouse_rate_cards` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `warehouse_id` BIGINT UNSIGNED NOT NULL,
                `company_code` VARCHAR(10) NOT NULL,
                `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
                `wh_inward_rate_per_carton` DECIMAL(10,4) NOT NULL DEFAULT 0,
                `wh_storage_rate_per_cft` DECIMAL(10,4) NOT NULL DEFAULT 0.60,
                `wh_fulfillment_rate_small` DECIMAL(10,4) NOT NULL DEFAULT 1.50,
                `wh_fulfillment_rate_large` DECIMAL(10,4) NOT NULL DEFAULT 2.50,
                `wh_fulfillment_qty_threshold` INT NOT NULL DEFAULT 3,
                `wh_pick_pack_rate_per_unit` DECIMAL(10,4) NOT NULL DEFAULT 0.50,
                `effective_from` DATE NOT NULL,
                `effective_to` DATE NULL,
                `version` INT NOT NULL DEFAULT 1,
                `status` ENUM('draft','pending_approval','approved','expired') NOT NULL DEFAULT 'draft',
                `contract_file` VARCHAR(500) NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `approved_by` BIGINT UNSIGNED NULL,
                `approved_at` TIMESTAMP NULL,
                `notes` TEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `wrc_warehouse_idx` (`warehouse_id`),
                INDEX `wrc_status_idx` (`status`),
                INDEX `wrc_effective_idx` (`effective_from`, `effective_to`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // 2. Warehouse Monthly Charges (expected + actual + variance per period)
        $exists = DB::select("SHOW TABLES LIKE 'warehouse_monthly_charges'");
        if (empty($exists)) {
            DB::statement("CREATE TABLE `warehouse_monthly_charges` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `warehouse_id` BIGINT UNSIGNED NOT NULL,
                `company_code` VARCHAR(10) NOT NULL,
                `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
                `charge_month` TINYINT NOT NULL,
                `charge_year` SMALLINT NOT NULL,
                `rate_card_id` BIGINT UNSIGNED NULL,
                `expected_inward` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `expected_storage` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `expected_fulfillment` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `expected_pick_pack` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `expected_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `actual_inward` DECIMAL(14,2) NULL,
                `actual_storage` DECIMAL(14,2) NULL,
                `actual_fulfillment` DECIMAL(14,2) NULL,
                `actual_pick_pack` DECIMAL(14,2) NULL,
                `actual_other` DECIMAL(14,2) NULL,
                `actual_total` DECIMAL(14,2) NULL,
                `variance_inward` DECIMAL(14,2) NULL,
                `variance_storage` DECIMAL(14,2) NULL,
                `variance_fulfillment` DECIMAL(14,2) NULL,
                `variance_pick_pack` DECIMAL(14,2) NULL,
                `variance_total` DECIMAL(14,2) NULL,
                `invoice_number` VARCHAR(100) NULL,
                `invoice_date` DATE NULL,
                `invoice_file` VARCHAR(500) NULL,
                `variance_explanations` JSON NULL,
                `status` ENUM('calculated','invoice_entered','under_review','approved','rejected','locked') NOT NULL DEFAULT 'calculated',
                `tolerance_pct` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
                `tolerance_abs` DECIMAL(12,2) NOT NULL DEFAULT 100.00,
                `calculation_snapshot` JSON NULL,
                `calculated_by` BIGINT UNSIGNED NULL,
                `calculated_at` TIMESTAMP NULL,
                `invoice_entered_by` BIGINT UNSIGNED NULL,
                `reviewed_by` BIGINT UNSIGNED NULL,
                `approved_by` BIGINT UNSIGNED NULL,
                `approved_at` TIMESTAMP NULL,
                `remarks` TEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `wmc_warehouse_idx` (`warehouse_id`),
                INDEX `wmc_period_idx` (`charge_month`, `charge_year`),
                INDEX `wmc_status_idx` (`status`),
                UNIQUE INDEX `wmc_unique_period` (`warehouse_id`, `charge_month`, `charge_year`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // 3. Warehouse Charge GRN Details (consignment-wise breakdown)
        $exists = DB::select("SHOW TABLES LIKE 'warehouse_charge_grn_details'");
        if (empty($exists)) {
            DB::statement("CREATE TABLE `warehouse_charge_grn_details` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `warehouse_monthly_charge_id` BIGINT UNSIGNED NOT NULL,
                `grn_id` BIGINT UNSIGNED NOT NULL,
                `vendor_id` BIGINT UNSIGNED NULL,
                `inward_cartons` INT NOT NULL DEFAULT 0,
                `inward_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `storage_qty` INT NOT NULL DEFAULT 0,
                `storage_cft` DECIMAL(12,4) NOT NULL DEFAULT 0,
                `storage_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `fulfillment_orders_small` INT NOT NULL DEFAULT 0,
                `fulfillment_orders_large` INT NOT NULL DEFAULT 0,
                `fulfillment_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `pick_pack_units` INT NOT NULL DEFAULT 0,
                `pick_pack_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `total_charge` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `wcgd_parent_idx` (`warehouse_monthly_charge_id`),
                INDEX `wcgd_grn_idx` (`grn_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // 4. Add inward_charged to grn table (not grns!)
        $exists = DB::select("SHOW COLUMNS FROM `grn` WHERE Field = 'inward_charged'");
        if (empty($exists)) {
            DB::statement("ALTER TABLE `grn` ADD COLUMN `inward_charged` TINYINT(1) NOT NULL DEFAULT 0");
        }

        // 5. Add cft_per_unit to products if missing
        $exists = DB::select("SHOW COLUMNS FROM `products` WHERE Field = 'cft_per_unit'");
        if (empty($exists)) {
            DB::statement("ALTER TABLE `products` ADD COLUMN `cft_per_unit` DECIMAL(10,4) NULL");
        }
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `warehouse_charge_grn_details`");
        DB::statement("DROP TABLE IF EXISTS `warehouse_monthly_charges`");
        DB::statement("DROP TABLE IF EXISTS `warehouse_rate_cards`");
    }
};
