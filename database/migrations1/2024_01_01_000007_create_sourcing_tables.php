<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Offer Sheets
        Schema::create('offer_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('offer_sheet_number')->unique();
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');
            $table->string('company_code');
            $table->enum('status', ['draft', 'submitted', 'under_review', 'selection_done', 'converted'])->default('draft');
            $table->integer('total_products')->default(0);
            $table->integer('selected_products')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('offer_sheet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_sheet_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('vendor_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('thumbnail')->nullable();
            $table->text('product_details')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->foreignId('selected_by')->nullable()->constrained('users');
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();
        });

        // Consignments
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->string('consignment_number')->unique();
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');
            $table->foreignId('offer_sheet_id')->nullable()->constrained()->onDelete('set null');
            $table->string('company_code');
            $table->string('destination_country');
            $table->enum('status', [
                'created', 'live_sheet_pending', 'live_sheet_submitted',
                'live_sheet_approved', 'live_sheet_locked',
                'in_production', 'ready_for_shipment', 'shipped',
                'delivered', 'cancelled'
            ])->default('created');
            $table->decimal('total_cbm', 12, 4)->default(0);
            $table->integer('total_items')->default(0);
            $table->decimal('total_value', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_code', 'status']);
            $table->index('vendor_id');
        });

        // Live Sheet
        Schema::create('live_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->onDelete('cascade');
            $table->string('live_sheet_number')->unique();
            $table->enum('status', ['draft', 'submitted', 'approved', 'locked', 'unlocked'])->default('draft');
            $table->decimal('total_cbm', 12, 4)->default(0);
            $table->boolean('is_locked')->default(false);
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('users');
            $table->timestamp('unlocked_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('live_sheet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_sheet_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('consignment_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->decimal('cbm_per_unit', 10, 4)->default(0);
            $table->decimal('total_cbm', 10, 4)->default(0);
            $table->decimal('weight_per_unit', 10, 3)->default(0);
            $table->decimal('total_weight', 10, 3)->default(0);
            $table->text('product_details')->nullable(); // detailed specs
            $table->timestamps();
        });

        // Quality Inspection Reports
        Schema::create('inspection_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('inspection_type', ['inline', 'midline', 'final']);
            $table->string('report_file');
            $table->string('report_name');
            $table->enum('result', ['pass', 'fail', 'conditional'])->nullable();
            $table->text('remarks')->nullable();
            $table->text('findings')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_reports');
        Schema::dropIfExists('live_sheet_items');
        Schema::dropIfExists('live_sheets');
        Schema::dropIfExists('consignments');
        Schema::dropIfExists('offer_sheet_items');
        Schema::dropIfExists('offer_sheets');
    }
};
