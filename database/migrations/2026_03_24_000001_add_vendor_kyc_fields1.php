<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('street_address')->nullable()->after('address');
            $table->string('province_state')->nullable()->after('state');
            $table->string('finance_contact_person')->nullable()->after('contact_person');
            $table->string('landline')->nullable()->after('phone');
            $table->string('official_website')->nullable()->after('landline');
            $table->string('iec_code')->nullable()->after('gst_number');
            $table->string('msme_number')->nullable()->after('iec_code');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['street_address', 'province_state', 'finance_contact_person', 'landline', 'official_website', 'iec_code', 'msme_number']);
        });
    }
};
