<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sheets', function (Blueprint $table) {
            $table->date('ex_factory_date')->nullable()->after('total_cbm');
            $table->date('final_inspection_date')->nullable()->after('ex_factory_date');
        });
    }

    public function down(): void
    {
        Schema::table('live_sheets', function (Blueprint $table) {
            $table->dropColumn(['ex_factory_date', 'final_inspection_date']);
        });
    }
};
