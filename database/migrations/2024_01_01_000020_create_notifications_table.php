<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `notifications` (
                `id` CHAR(36) NOT NULL,
                `type` VARCHAR(255) NOT NULL,
                `notifiable_type` VARCHAR(255) NOT NULL,
                `notifiable_id` BIGINT UNSIGNED NOT NULL,
                `data` TEXT NOT NULL,
                `read_at` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                INDEX `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`, `notifiable_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS `notifications`');
    }
};
