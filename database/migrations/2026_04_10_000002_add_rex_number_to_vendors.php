<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::select("SHOW COLUMNS FROM `vendors` WHERE Field = 'rex_number'");
        if (empty($exists)) {
            DB::statement("ALTER TABLE `vendors` ADD COLUMN `rex_number` VARCHAR(20) NULL AFTER `msme_number`");
        }
    }
    public function down(): void
    {
        DB::statement("ALTER TABLE `vendors` DROP COLUMN IF EXISTS `rex_number`");
    }
};
