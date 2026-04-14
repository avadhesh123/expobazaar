<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Amazon, Wayfair, Faire, GIGA, Shopify, TICA, Coons
            $table->string('slug')->unique();
            $table->enum('type', ['marketplace', 'offline', 'direct'])->default('marketplace');
            $table->string('platform_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('company_codes')->nullable();
            $table->text('commission_rules')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('pincode')->nullable();
            $table->foreignId('sales_channel_id')->nullable()->constrained()->onDelete('set null');
            $table->string('company_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('sales_channels');
    }
};
