<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make consignment_id nullable (live sheet is now created BEFORE consignment)
        Schema::table('live_sheets', function (Blueprint $table) {
            // Drop the foreign key first, then modify column
            $table->dropForeign(['consignment_id']);
        });

        Schema::table('live_sheets', function (Blueprint $table) {
            $table->unsignedBigInteger('consignment_id')->nullable()->change();
            $table->foreign('consignment_id')->references('id')->on('consignments')->onDelete('set null');

            // Add new columns for the updated flow
            $table->foreignId('offer_sheet_id')->nullable()->after('consignment_id')->constrained('offer_sheets')->onDelete('set null');
            $table->foreignId('vendor_id')->nullable()->after('offer_sheet_id')->constrained('vendors')->onDelete('cascade');
            $table->string('company_code', 4)->nullable()->after('vendor_id');
        });

        // Make consignment_id nullable on live_sheet_items too
        Schema::table('live_sheet_items', function (Blueprint $table) {
            $table->dropForeign(['consignment_id']);
        });

        Schema::table('live_sheet_items', function (Blueprint $table) {
            $table->unsignedBigInteger('consignment_id')->nullable()->change();
            $table->foreign('consignment_id')->references('id')->on('consignments')->onDelete('set null');
        });

        // Add offer_sheet_id and live_sheet_id to consignments (reverse link)
        Schema::table('consignments', function (Blueprint $table) {
            if (!Schema::hasColumn('consignments', 'live_sheet_id')) {
                $table->foreignId('live_sheet_id')->nullable()->after('offer_sheet_id')->constrained('live_sheets')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consignments', function (Blueprint $table) {
            if (Schema::hasColumn('consignments', 'live_sheet_id')) {
                $table->dropForeign(['live_sheet_id']);
                $table->dropColumn('live_sheet_id');
            }
        });

        Schema::table('live_sheet_items', function (Blueprint $table) {
            $table->dropForeign(['consignment_id']);
            $table->unsignedBigInteger('consignment_id')->nullable(false)->change();
            $table->foreign('consignment_id')->references('id')->on('consignments')->onDelete('cascade');
        });

        Schema::table('live_sheets', function (Blueprint $table) {
            $table->dropForeign(['consignment_id']);
            $table->dropForeign(['offer_sheet_id']);
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['offer_sheet_id', 'vendor_id', 'company_code']);
            $table->unsignedBigInteger('consignment_id')->nullable(false)->change();
            $table->foreign('consignment_id')->references('id')->on('consignments')->onDelete('cascade');
        });
    }
};
