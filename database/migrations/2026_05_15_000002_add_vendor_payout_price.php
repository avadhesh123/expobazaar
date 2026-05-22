<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up()
    {
        // Products Table
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('vendor_payout_price', 12, 4)
                  ->after('sap_code')
                  ->nullable()
                  ->comment('Vendor payout price per unit');
        });

        // Order Items Table
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('vendor_payout_price', 12, 4)
                  ->after('sap_code')
                  ->nullable();
        });

        // Live Sheet Items / Offer Sheets (if exists)
        Schema::table('live_sheet_items', function (Blueprint $table) {
            $table->decimal('vendor_payout_price', 12, 4)
                  ->after('sap_code')
                  ->nullable();
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('vendor_payout_price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('vendor_payout_price');
        });

        Schema::table('live_sheet_items', function (Blueprint $table) {
            $table->dropColumn('vendor_payout_price');
        });
    }
};
