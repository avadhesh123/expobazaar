<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform Pricing
        Schema::create('platform_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asn_id')->constrained('asn')->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('sales_channel_id')->constrained()->onDelete('cascade');
            $table->string('company_code');
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('platform_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('map_price', 12, 2)->nullable(); // Minimum advertised price
            $table->decimal('margin_percent', 8, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'finance_review', 'approved', 'rejected'])->default('draft');
            $table->foreignId('prepared_by')->nullable()->constrained('users');
            $table->foreignId('finance_reviewed_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sales_channel_id']);
        });

        // Product Catalogue (Listing details)
        Schema::create('product_catalogues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('sales_channel_id')->constrained()->onDelete('cascade');
            $table->foreignId('platform_pricing_id')->nullable()->constrained('platform_pricing')->onDelete('set null');
            $table->string('company_code');
            $table->string('listing_sku')->nullable();
            $table->string('listing_url')->nullable();
            $table->string('shopify_url')->nullable();
            $table->enum('listing_status', ['pending', 'listed', 'inactive', 'removed'])->default('pending');
            $table->json('catalogue_details')->nullable();
            $table->foreignId('listed_by')->nullable()->constrained('users');
            $table->timestamp('listed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'sales_channel_id']);
        });

        // Inventory Management
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_sub_location_id')->nullable()->constrained()->onDelete('set null');
            $table->string('company_code');
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('available_quantity')->default(0);
            $table->date('received_date')->nullable();
            $table->foreignId('grn_id')->nullable()->constrained('grn')->onDelete('set null');
            $table->foreignId('consignment_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id']);
            $table->index('company_code');
        });

        // Inventory Movements (transfer between warehouses/sub-locations)
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->enum('movement_type', ['inward', 'outward', 'transfer', 'adjustment']);
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses');
            $table->foreignId('from_sub_location_id')->nullable()->constrained('warehouse_sub_locations');
            $table->foreignId('to_sub_location_id')->nullable()->constrained('warehouse_sub_locations');
            $table->integer('quantity');
            $table->string('reference_type')->nullable(); // grn, sale, transfer
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('product_catalogues');
        Schema::dropIfExists('platform_pricing');
    }
};
