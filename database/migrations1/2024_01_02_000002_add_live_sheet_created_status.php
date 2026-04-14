<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE offer_sheets MODIFY COLUMN status ENUM('draft','submitted','under_review','selection_done','live_sheet_created','converted') DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE offer_sheets MODIFY COLUMN status ENUM('draft','submitted','under_review','selection_done','converted') DEFAULT 'draft'");
    }
};
