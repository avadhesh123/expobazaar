<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inspection_reports', function (Blueprint $table) {

            // Commercial Invoice fields
            $table->string('commercial_invoice')->nullable()
                  ->comment('File path or filename of the commercial invoice');

            $table->foreignId('commercial_invoice_uploaded_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('User who uploaded the commercial invoice');

            $table->timestamp('commercial_invoice_uploaded_at')
                  ->nullable()
                  ->comment('Date & time when commercial invoice was uploaded');

            // Packing List fields
            $table->string('packing_list')->nullable()
                  ->comment('File path or filename of the packing list');

            $table->foreignId('packing_list_uploaded_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('User who uploaded the packing list');

            $table->timestamp('packing_list_uploaded_at')
                  ->nullable()
                  ->comment('Date & time when packing list was uploaded');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspection_reports', function (Blueprint $table) {
            $table->dropColumn([
                'commercial_invoice',
                'commercial_invoice_uploaded_by',
                'commercial_invoice_uploaded_at',
                'packing_list',
                'packing_list_uploaded_by',
                'packing_list_uploaded_at',
            ]);
        });
    }
};
