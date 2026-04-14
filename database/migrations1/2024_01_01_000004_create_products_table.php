<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');
            $table->string('company_code');

            // Dimensions & Physical
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();
            $table->decimal('weight_kg', 10, 3)->nullable();
            $table->decimal('cbm', 10, 4)->nullable(); // Cubic meter
            $table->string('color')->nullable();
            $table->string('material')->nullable();
            $table->text('variations')->nullable(); // size, color variations

            // Pricing
            $table->decimal('vendor_price', 12, 2)->default(0);
            $table->decimal('fob_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');

            // Images
            $table->string('thumbnail')->nullable();
            $table->text('images')->nullable();

            // Status
            $table->enum('status', ['draft', 'submitted', 'selected', 'approved', 'rejected', 'listed', 'discontinued'])->default('draft');
            $table->string('hsn_code')->nullable();
            $table->string('barcode')->nullable();

            // Inventory
            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);

            // Listing
            $table->string('shopify_url')->nullable();
            $table->text('platform_listing_status')->nullable(); // {amazon: true, wayfair: false, ...}

            $table->timestamps();
            $table->softDeletes();

            $table->index('company_code');
            $table->index('status');
            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
