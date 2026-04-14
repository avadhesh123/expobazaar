<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('company_name');
            $table->string('company_code'); // 2000, 2100, 2200
            $table->string('vendor_code')->unique();
            $table->string('contact_person');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('pincode')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->string('bank_swift_code')->nullable();

            // KYC
            $table->enum('kyc_status', ['pending', 'submitted', 'approved', 'rejected'])->default('pending');
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_approved_at')->nullable();
            $table->foreignId('kyc_approved_by')->nullable()->constrained('users');
            $table->text('kyc_rejection_reason')->nullable();

            // Contract
            $table->enum('contract_status', ['pending', 'sent', 'signed', 'expired'])->default('pending');
            $table->string('docusign_envelope_id')->nullable();
            $table->timestamp('contract_signed_at')->nullable();
            $table->timestamp('contract_expiry_at')->nullable();

            // Membership
            $table->decimal('membership_fee', 10, 2)->default(0);
            $table->boolean('membership_fee_waived')->default(false);
            $table->foreignId('membership_waived_by')->nullable()->constrained('users');
            $table->enum('membership_status', ['pending', 'invoiced', 'paid', 'waived'])->default('pending');

            // Payout Rules
            $table->text('payout_rules')->nullable();
            $table->decimal('storage_rate', 10, 2)->default(0);

            $table->enum('status', ['pending_approval', 'pending_kyc', 'pending_contract', 'active', 'inactive', 'suspended'])->default('pending_approval');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_code');
            $table->index('status');
        });

        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade');
            $table->string('document_type'); // kyc, contract, invoice, other
            $table->string('document_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->enum('status', ['uploaded', 'verified', 'rejected'])->default('uploaded');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
        Schema::dropIfExists('vendors');
    }
};
