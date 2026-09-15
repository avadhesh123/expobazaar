<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payout_warehouse_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_payout_id');
            $table->decimal('amount', 12, 2);                 // Positive = extra charge, Negative = credit/refund
            $table->string('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->date('adjustment_date');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('vendor_payout_id')
                  ->references('id')
                  ->on('vendor_payouts')
                  ->onDelete('cascade');

            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_warehouse_adjustments');
    }
};
