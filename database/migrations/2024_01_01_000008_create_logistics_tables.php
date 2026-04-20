<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shipments
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_code')->unique();
            $table->string('company_code');
            $table->string('destination_country');
            $table->enum('shipment_type', ['FCL', 'LCL', 'AIR'])->default('FCL');
            $table->enum('status', [
                'planning', 'consolidated', 'locked', 'asn_generated',
                'in_transit', 'arrived', 'grn_pending', 'grn_completed',
                'cancelled'
            ])->default('planning');

            // Container Details
            $table->string('container_number')->nullable();
            $table->string('container_size')->nullable(); // 20ft, 40ft
            $table->decimal('total_cbm', 12, 4)->default(0);
            $table->decimal('capacity_cbm', 10, 2)->default(65); // FCL default
            $table->decimal('total_weight', 12, 3)->default(0);
            $table->integer('total_items')->default(0);
            $table->decimal('total_value', 15, 2)->default(0);

            // Shipping Details
            $table->string('shipping_line')->nullable();
            $table->string('vessel_name')->nullable();
            $table->string('voyage_number')->nullable();
            $table->string('bill_of_lading')->nullable();
            $table->date('sailing_date')->nullable();
            $table->date('eta_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();

            // Warehouse
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses');

            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_code', 'status']);
        });

        // Shipment-Consignment Mapping
        Schema::create('shipment_consignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->onDelete('cascade');
            $table->foreignId('consignment_id')->constrained()->onDelete('cascade');
            $table->decimal('cbm', 10, 4)->default(0);
            $table->integer('items')->default(0);
            $table->timestamps();

            $table->unique(['shipment_id', 'consignment_id']);
        });

        // ASN (Advance Shipping Notice)
        Schema::create('asn', function (Blueprint $table) {
            $table->id();
            $table->string('asn_number')->unique();
            $table->foreignId('shipment_id')->constrained()->onDelete('cascade');
            $table->string('company_code');
            $table->enum('status', ['generated', 'pricing_pending', 'pricing_done', 'sent_to_warehouse'])->default('generated');
            $table->json('items')->nullable();
            $table->decimal('total_cbm', 12, 4)->default(0);
            $table->integer('total_items')->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        // GRN (Goods Receipt Note)
        Schema::create('grn', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number')->unique();
            $table->foreignId('shipment_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->string('company_code');
            $table->date('receipt_date');
            $table->enum('status', ['pending', 'uploaded', 'verified', 'completed'])->default('pending');
            $table->integer('total_items_expected')->default(0);
            $table->integer('total_items_received')->default(0);
            $table->integer('damaged_items')->default(0);
            $table->integer('missing_items')->default(0);
            $table->string('grn_file')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('grn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained('grn')->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('consignment_id')->nullable()->constrained();
            $table->integer('expected_quantity')->default(0);
            $table->integer('received_quantity')->default(0);
            $table->integer('damaged_quantity')->default(0);
            $table->integer('missing_quantity')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_items');
        Schema::dropIfExists('grn');
        Schema::dropIfExists('asn');
        Schema::dropIfExists('shipment_consignments');
        Schema::dropIfExists('shipments');
    }
};
