<?php

namespace App\Services;

use App\Models\{User, Vendor, VendorDocument, EmailOtp, ActivityLog};
use App\Notifications\{VendorApproved, VendorKycSubmitted, VendorContractSent, VendorActivated};
use Illuminate\Support\Facades\{DB, Notification};

class VendorService
{
    /**
     * Step 1: Sourcing team creates vendor request
     */
    public function createVendorRequest(array $data, User $createdBy): Vendor
    {
        return DB::transaction(function () use ($data, $createdBy) {
            // Create user account (pending — activated after admin approval)
            $user = User::create([
                'name' => $data['contact_person'],
                'email' => $data['email'],
                'user_type' => 'external',
                'company_codes' => [$data['company_code']],
                'status' => 'pending',
            ]);

            // Create vendor record
            $vendor = Vendor::create([
                'user_id' => $user->id,
                'company_name' => $data['company_name'],
                'company_code' => $data['company_code'],
                'vendor_code' => Vendor::generateVendorCode($data['company_code']),
                'contact_person' => $data['contact_person'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => $data['country'] ?? null,
                'pincode' => $data['pincode'] ?? null,
                'status' => 'pending_approval',
                'created_by' => $createdBy->id,
            ]);

            ActivityLog::log('created', 'vendor', $vendor, null, $vendor->toArray(), 'Vendor creation request raised');

            // Notify admins
            $admins = User::admins()->active()->get();
            Notification::send($admins, new VendorApproved($vendor, 'creation_request'));

            // Send welcome email to vendor with login instructions
            try {
                $user->notify(new VendorApproved($vendor, 'welcome'));
            } catch (\Exception $e) {
                \Log::warning('Failed to send vendor welcome email: ' . $e->getMessage());
            }

            return $vendor;
        });
    }

    /**
     * Step 2: Admin approves vendor - triggers email to vendor for account creation
     */
    public function approveVendorCreation(Vendor $vendor, User $admin): Vendor
    {
        return DB::transaction(function () use ($vendor, $admin) {
            $vendor->update(['status' => 'pending_kyc']);
            $vendor->user->update(['status' => 'active']);

            ActivityLog::log('approved', 'vendor', $vendor, null, null, 'Vendor creation approved by admin');

            // Send welcome email to vendor with login instructions
            try {
                $vendor->user->notify(new VendorApproved($vendor, 'welcome'));
            } catch (\Exception $e) {
                \Log::warning('Failed to send vendor welcome email: ' . $e->getMessage());
            }

            return $vendor;
        });
    }

    /**
     * Step 3: Vendor submits KYC documents
     */
    public function submitKyc(Vendor $vendor, array $documents): Vendor
    {
        return DB::transaction(function () use ($vendor, $documents) {
            // Documents are already saved by VendorController - skip duplicate creation

            $vendor->update([
                'kyc_status' => 'submitted',
                'kyc_submitted_at' => now(),
            ]);

            ActivityLog::log('submitted', 'vendor_kyc', $vendor, null, null, 'KYC documents submitted');

            // Notify finance team
            try {
                $financeTeam = User::internal()->byDepartment('finance')->active()->get();
                Notification::send($financeTeam, new VendorKycSubmitted($vendor));
            } catch (\Exception $e) {
                \Log::warning('Failed to notify finance team: ' . $e->getMessage());
            }

            return $vendor;
        });
    }

    /**
     * Step 4: Finance approves KYC
     */
    public function approveKyc(Vendor $vendor, User $approver): Vendor
    {
        return DB::transaction(function () use ($vendor, $approver) {
            $vendor->update([
                'kyc_status' => 'approved',
                'kyc_approved_at' => now(),
                'kyc_approved_by' => $approver->id,
                'status' => 'pending_contract',
            ]);

            ActivityLog::log('approved', 'vendor_kyc', $vendor, null, null, 'KYC approved by finance');

            // Trigger DocuSign contract
            $this->sendContract($vendor);

            return $vendor;
        });
    }

    /**
     * Step 4b: Finance rejects KYC
     */
    public function rejectKyc(Vendor $vendor, User $rejector, string $reason): Vendor
    {
        $vendor->update([
            'kyc_status' => 'rejected',
            'kyc_rejection_reason' => $reason,
        ]);

        ActivityLog::log('rejected', 'vendor_kyc', $vendor, null, ['reason' => $reason], 'KYC rejected');
        $vendor->user->notify(new VendorApproved($vendor, 'kyc_rejected'));

        return $vendor;
    }

    /**
     * Step 5: Send contract via DocuSign
     */
    public function sendContract(Vendor $vendor): void
    {
        // DocuSign integration placeholder
        $vendor->update([
            'contract_status' => 'sent',
        ]);

        $vendor->user->notify(new VendorContractSent($vendor));
        ActivityLog::log('sent', 'vendor_contract', $vendor, null, null, 'Contract sent for signature');
    }

    /**
     * Step 6: Contract signed - activate vendor panel
     */
    public function activateVendor(Vendor $vendor): Vendor
    {
        return DB::transaction(function () use ($vendor) {
            $vendor->update([
                'contract_status' => 'signed',
                'contract_signed_at' => now(),
                'status' => 'active',
            ]);

            // Generate membership invoice if not waived
            if (!$vendor->membership_fee_waived && $vendor->membership_fee > 0) {
                $vendor->update(['membership_status' => 'invoiced']);
            }

            ActivityLog::log('activated', 'vendor', $vendor, null, null, 'Vendor activated');
            $vendor->user->notify(new VendorActivated($vendor));

            // Notify sourcing team
            $sourcing = User::internal()->byDepartment('sourcing')->active()->get();
            Notification::send($sourcing, new VendorActivated($vendor));

            return $vendor;
        });
    }

    /**
     * Waive membership fee
     */
    public function waiveMembershipFee(Vendor $vendor, User $admin): Vendor
    {
        $vendor->update([
            'membership_fee_waived' => true,
            'membership_waived_by' => $admin->id,
            'membership_status' => 'waived',
        ]);

        ActivityLog::log('waived', 'vendor_membership', $vendor, null, null, 'Membership fee waived');
        return $vendor;
    }
}
