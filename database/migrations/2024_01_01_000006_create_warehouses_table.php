<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('company_code');
            $table->string('type')->default('main'); // main, sub
            $table->foreignId('parent_warehouse_id')->nullable()->constrained('warehouses')->onDelete('set null');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country');
            $table->string('pincode')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();

            // Rate Card
            $table->decimal('inward_rate_per_cbm', 10, 2)->default(0);
            $table->decimal('storage_rate_per_cbm_month', 10, 2)->default(0);
            $table->decimal('pick_pack_rate', 10, 2)->default(0);
            $table->decimal('consumable_rate', 10, 2)->default(0);
            $table->decimal('last_mile_rate', 10, 2)->default(0);
            $table->text('rate_card')->nullable(); // full rate card JSON

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_code');
        });

        Schema::create('warehouse_sub_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code');
            $table->string('type')->default('zone'); // zone, aisle, rack, bin, store
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_sub_locations');
        Schema::dropIfExists('warehouses');
    }
};
