<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders / Sales
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('platform_order_id')->nullable();
            $table->foreignId('sales_channel_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->string('company_code');
            $table->date('order_date');

            // Order Details
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');

            // Shipping / Tracking
            $table->string('tracking_id')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('shipping_provider')->nullable();
            $table->enum('shipment_status', [
                'pending', 'shipped', 'in_transit', 'delivered',
                'returned', 'cancelled'
            ])->default('pending');
            $table->date('shipped_date')->nullable();
            $table->date('delivered_date')->nullable();

            // Customer Info
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_country')->nullable();
            $table->string('shipping_pincode')->nullable();

            // Payment
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');
            $table->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'])->default('pending');

            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_code', 'status']);
            $table->index('sales_channel_id');
            $table->index('payment_status');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->foreignId('vendor_id')->constrained();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->string('sku')->nullable();
            $table->timestamps();
        });

        // Finance - Receivables & Deductions
        Schema::create('finance_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('sales_channel_id')->constrained();
            $table->string('company_code');
            $table->decimal('order_amount', 15, 2)->default(0);

            // Platform Deductions
            $table->decimal('platform_commission', 10, 2)->default(0);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('insurance_charge', 10, 2)->default(0);
            $table->decimal('chargeback_amount', 10, 2)->default(0);
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->text('deduction_notes')->nullable();

            // Net Receivable
            $table->decimal('net_receivable', 15, 2)->default(0);

            // Payment
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->decimal('amount_received', 15, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('bank_reference')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['company_code', 'payment_status']);
        });

        // Chargebacks
        Schema::create('chargebacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained();
            $table->string('company_code');
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->text('description')->nullable();
            $table->enum('status', ['raised', 'pending_confirmation', 'confirmed', 'rejected', 'deducted'])->default('raised');
            $table->foreignId('raised_by')->nullable()->constrained('users');
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('confirmation_remarks')->nullable();
            $table->timestamps();
        });

        // Warehouse Charges
        Schema::create('warehouse_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('vendor_id')->constrained();
            $table->string('company_code');
            $table->integer('charge_month'); // 1-12
            $table->integer('charge_year');
            $table->enum('charge_type', ['inward', 'storage', 'pick_pack', 'consumable', 'last_mile', 'other']);
            $table->decimal('calculated_amount', 12, 2)->default(0);
            $table->decimal('actual_amount', 12, 2)->default(0);
            $table->decimal('variance', 12, 2)->default(0);
            $table->text('variance_comment')->nullable();
            $table->string('receipt_file')->nullable();
            $table->enum('status', ['calculated', 'receipt_uploaded', 'verified', 'allocated'])->default('calculated');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['vendor_id', 'charge_month', 'charge_year']);
        });

        // Vendor Payouts
        Schema::create('vendor_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained();
            $table->string('company_code');
            $table->integer('payout_month');
            $table->integer('payout_year');

            // Payout Calculation
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('total_storage_charges', 12, 2)->default(0);
            $table->decimal('total_inward_charges', 12, 2)->default(0);
            $table->decimal('total_logistics_charges', 12, 2)->default(0);
            $table->decimal('total_platform_deductions', 12, 2)->default(0);
            $table->decimal('total_chargebacks', 12, 2)->default(0);
            $table->decimal('total_other_deductions', 12, 2)->default(0);
            $table->decimal('net_payout', 15, 2)->default(0);

            $table->enum('status', ['draft', 'calculated', 'approved', 'payment_pending', 'paid', 'invoice_received'])->default('draft');
            $table->date('payment_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_advice_file')->nullable();
            $table->string('vendor_invoice_file')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'payout_month', 'payout_year', 'company_code'], 'vendor_payout_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payouts');
        Schema::dropIfExists('warehouse_charges');
        Schema::dropIfExists('chargebacks');
        Schema::dropIfExists('finance_receivables');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
