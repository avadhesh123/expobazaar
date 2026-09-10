<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::select("SHOW TABLES LIKE 'payout_payments'");
        if (empty($exists)) {
            DB::statement("
                CREATE TABLE payout_payments (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    vendor_payout_id BIGINT UNSIGNED NOT NULL,
                    vendor_id BIGINT UNSIGNED NOT NULL,
                    company_code VARCHAR(10) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL,
                    payment_date DATE NOT NULL,
                    payment_mode VARCHAR(50) NULL COMMENT 'bank_transfer, cheque, upi, cash',
                    reference_number VARCHAR(100) NULL COMMENT 'Transaction ID, cheque number, etc.',
                    remarks VARCHAR(500) NULL,
                    created_by BIGINT UNSIGNED NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_payout (vendor_payout_id),
                    INDEX idx_vendor (vendor_id),
                    FOREIGN KEY (vendor_payout_id) REFERENCES vendor_payouts(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        //-- Add columns to vendor_payouts if missing
        $cols = collect(DB::select('SHOW COLUMNS FROM vendor_payouts'))->pluck('Field')->toArray();
        if (!in_array('total_paid', $cols)) {
            DB::statement('ALTER TABLE vendor_payouts ADD COLUMN total_paid DECIMAL(12,2) DEFAULT 0 AFTER net_payout');
        }
        if (!in_array('balance_due', $cols)) {
            DB::statement('ALTER TABLE vendor_payouts ADD COLUMN balance_due DECIMAL(12,2) DEFAULT 0 AFTER total_paid');
        }
    }

    public function down(): void {}
};
