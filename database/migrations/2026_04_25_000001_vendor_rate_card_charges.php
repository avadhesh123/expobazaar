<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Vendor Rate Cards (one active card per vendor) ──────────
        $exists = DB::select("SHOW TABLES LIKE 'vendor_rate_cards'");
        if (empty($exists)) {
            DB::statement("CREATE TABLE `vendor_rate_cards` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `vendor_id` BIGINT UNSIGNED NOT NULL,
                `company_code` VARCHAR(10) NOT NULL,
                `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
                `inward_rate_per_carton` DECIMAL(10,4) NOT NULL DEFAULT 0,
                `storage_rate_per_cft` DECIMAL(10,4) NOT NULL DEFAULT 0,
                `fulfillment_rate_small` DECIMAL(10,4) NOT NULL DEFAULT 1.50,
                `fulfillment_rate_large` DECIMAL(10,4) NOT NULL DEFAULT 2.50,
                `fulfillment_qty_threshold` INT NOT NULL DEFAULT 3,
                `pick_pack_rate_per_unit` DECIMAL(10,4) NOT NULL DEFAULT 0.50,
                `effective_from` DATE NOT NULL,
                `effective_to` DATE NULL,
                `version` INT NOT NULL DEFAULT 1,
                `status` ENUM('draft','pending_approval','approved','expired') NOT NULL DEFAULT 'draft',
                `created_by` BIGINT UNSIGNED NULL,
                `approved_by` BIGINT UNSIGNED NULL,
                `approved_at` TIMESTAMP NULL,
                `vendor_acknowledged` TINYINT(1) NOT NULL DEFAULT 0,
                `vendor_acknowledged_at` TIMESTAMP NULL,
                `notes` TEXT NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `vrc_vendor_idx` (`vendor_id`),
                INDEX `vrc_status_idx` (`status`),
                INDEX `vrc_effective_idx` (`effective_from`, `effective_to`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // ── 2. Monthly Vendor Charges (one per vendor per GRN per month) ──
        $exists = DB::select("SHOW TABLES LIKE 'vendor_monthly_charges'");
        if (empty($exists)) {
            DB::statement("CREATE TABLE `vendor_monthly_charges` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `vendor_id` BIGINT UNSIGNED NOT NULL,
                `grn_id` BIGINT UNSIGNED NULL,
                `warehouse_id` BIGINT UNSIGNED NULL,
                `company_code` VARCHAR(10) NOT NULL,
                `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
                `charge_month` TINYINT NOT NULL,
                `charge_year` SMALLINT NOT NULL,
                `rate_card_id` BIGINT UNSIGNED NULL,
                `inward_cartons` INT NOT NULL DEFAULT 0,
                `inward_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `storage_remaining_qty` INT NOT NULL DEFAULT 0,
                `storage_cft` DECIMAL(12,4) NOT NULL DEFAULT 0,
                `storage_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `fulfillment_orders_small` INT NOT NULL DEFAULT 0,
                `fulfillment_orders_large` INT NOT NULL DEFAULT 0,
                `fulfillment_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `pick_pack_units` INT NOT NULL DEFAULT 0,
                `pick_pack_charge` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `material_cost` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `total_charges` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `status` ENUM('calculated','approved','deducted','disputed') NOT NULL DEFAULT 'calculated',
                `approved_by` BIGINT UNSIGNED NULL,
                `approved_at` TIMESTAMP NULL,
                `deducted_from_payout_id` BIGINT UNSIGNED NULL,
                `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
                `calculation_snapshot` JSON NULL COMMENT 'Immutable snapshot of input data at calculation time',
                `notes` TEXT NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `vmc_vendor_idx` (`vendor_id`),
                INDEX `vmc_grn_idx` (`grn_id`),
                INDEX `vmc_period_idx` (`charge_month`, `charge_year`),
                INDEX `vmc_status_idx` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // ── 3. Rate Card Change Log ──────────────────────────────────
        $exists = DB::select("SHOW TABLES LIKE 'rate_card_audit_logs'");
        if (empty($exists)) {
            DB::statement("CREATE TABLE `rate_card_audit_logs` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `vendor_rate_card_id` BIGINT UNSIGNED NOT NULL,
                `vendor_id` BIGINT UNSIGNED NOT NULL,
                `field_name` VARCHAR(100) NOT NULL,
                `old_value` VARCHAR(255) NULL,
                `new_value` VARCHAR(255) NULL,
                `reason` VARCHAR(500) NULL,
                `changed_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL,
                INDEX `rcal_card_idx` (`vendor_rate_card_id`),
                INDEX `rcal_vendor_idx` (`vendor_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // ── 4. Add cft_per_unit to products if missing ───────────────
        $exists = DB::select("SHOW COLUMNS FROM `products` WHERE Field = 'cft_per_unit'");
        if (empty($exists)) {
            DB::statement("ALTER TABLE `products` ADD COLUMN `cft_per_unit` DECIMAL(10,4) NULL AFTER `sap_code`");
        }

        // ── 5. Add inward_charged flag to grns ───────────────────────
        $exists = DB::select("SHOW COLUMNS FROM `grn` WHERE Field = 'inward_charged'");
        if (empty($exists)) {
            DB::statement("ALTER TABLE `grn` ADD COLUMN `inward_charged` TINYINT(1) NOT NULL DEFAULT 0");
        }
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `rate_card_audit_logs`");
        DB::statement("DROP TABLE IF EXISTS `vendor_monthly_charges`");
        DB::statement("DROP TABLE IF EXISTS `vendor_rate_cards`");
    }
};
