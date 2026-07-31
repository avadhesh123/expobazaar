<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::select("SHOW COLUMNS FROM `users` WHERE Field = 'password'");
        if (empty($exists)) {
            DB::statement("ALTER TABLE `users` ADD COLUMN `password` VARCHAR(255) NULL AFTER `email`");
        }
    }

    public function down(): void {}
};
