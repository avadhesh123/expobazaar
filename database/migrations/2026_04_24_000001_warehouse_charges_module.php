<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Vendor Rate Cards table
        $exists = DB::select("SHOW TABLES LIKE 'vendor_rate_cards'");
        if (empty($exists)) {
            DB::statement("CREATE TABLE `vendor_rate_cards` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `vendor_id` BIGINT UNSIGNED NOT NULL,
                `warehouse_id` BIGINT UNSIGNED NULL,
                `company_code` VARCHAR(10) NULL,
                `charge_key` VARCHAR(100) NOT NULL COMMENT 'e.g. inward_unloading, storage_pallet, outward_pickpack',
                `charge_label` VARCHAR(200) NOT NULL,
                `charge_type` VARCHAR(100) NULL COMMENT 'One time, Per week, Per Month, etc.',
                `uom` VARCHAR(100) NULL COMMENT 'Per Shipment, Per Unit, Pallet, etc.',
                `rate` DECIMAL(12,4) NOT NULL DEFAULT 0,
                `effective_from` DATE NULL,
                `effective_to` DATE NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `vrc_vendor_idx` (`vendor_id`),
                INDEX `vrc_warehouse_idx` (`warehouse_id`),
                INDEX `vrc_charge_key_idx` (`charge_key`),
                INDEX `vrc_effective_idx` (`effective_from`, `effective_to`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // 2. Warehouse Charge Line Items
        $exists = DB::select("SHOW TABLES LIKE 'warehouse_charge_items'");
        if (empty($exists)) {
            DB::statement("CREATE TABLE `warehouse_charge_items` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `warehouse_charge_id` BIGINT UNSIGNED NOT NULL,
                `charge_key` VARCHAR(100) NOT NULL,
                `charge_label` VARCHAR(200) NOT NULL,
                `uom` VARCHAR(100) NULL,
                `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0,
                `rate` DECIMAL(12,4) NOT NULL DEFAULT 0,
                `amount` DECIMAL(14,2) NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                INDEX `wci_charge_idx` (`warehouse_charge_id`),
                INDEX `wci_key_idx` (`charge_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // 3. Expand warehouse_charges table
        $addCol = function($table, $col, $def) {
            $exists = DB::select("SHOW COLUMNS FROM `{$table}` WHERE Field = '{$col}'");
            if (empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
            }
        };

        $addCol('warehouse_charges', 'charge_category', "ENUM('payable','receivable') NOT NULL DEFAULT 'payable' AFTER `charge_type`");
        $addCol('warehouse_charges', 'invoice_number', "VARCHAR(100) NULL");
        $addCol('warehouse_charges', 'invoice_date', "DATE NULL");
        $addCol('warehouse_charges', 'invoice_file', "VARCHAR(500) NULL");
        $addCol('warehouse_charges', 'reason_code', "VARCHAR(100) NULL");
        $addCol('warehouse_charges', 'deducted_from_payout', "TINYINT(1) NOT NULL DEFAULT 0");
        $addCol('warehouse_charges', 'payout_id', "BIGINT UNSIGNED NULL");
        $addCol('warehouse_charges', 'approved_by', "BIGINT UNSIGNED NULL");
        $addCol('warehouse_charges', 'approved_at', "TIMESTAMP NULL");
        $addCol('warehouse_charges', 'notes', "TEXT NULL");

        // Add indexes
        $addIdx = function($table, $col) {
            $name = "{$table}_{$col}_idx";
            $exists = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$name}'");
            if (empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` (`{$col}`)");
            }
        };
        $addIdx('warehouse_charges', 'charge_category');
        $addIdx('warehouse_charges', 'charge_month');
        $addIdx('warehouse_charges', 'charge_year');
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `warehouse_charge_items`");
        DB::statement("DROP TABLE IF EXISTS `vendor_rate_cards`");
    }
};
