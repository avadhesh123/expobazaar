<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'company_name', 'company_code', 'vendor_code', 'contact_person',
        'email', 'phone', 'address', 'street_address', 'city', 'state', 'province_state',
        'country', 'pincode', 'gst_number', 'pan_number',
        'finance_contact_person', 'iec_code', 'msme_number', 'landline', 'official_website',
        'bank_name', 'bank_account_number', 'bank_ifsc', 'bank_swift_code',
        'kyc_status', 'kyc_submitted_at', 'kyc_approved_at',
        'kyc_approved_by', 'kyc_rejection_reason', 'contract_status', 'docusign_envelope_id',
        'contract_signed_at', 'contract_expiry_at', 'membership_fee', 'membership_fee_waived',
        'membership_waived_by', 'membership_status', 'payout_rules', 'storage_rate',
        'status', 'created_by','rex_number',
    ];

    protected $casts = [
        'kyc_submitted_at' => 'datetime',
        'kyc_approved_at' => 'datetime',
        'contract_signed_at' => 'datetime',
        'contract_expiry_at' => 'datetime',
        'membership_fee_waived' => 'boolean',
        'payout_rules' => 'array',
        'membership_fee' => 'decimal:2',
        'storage_rate' => 'decimal:2',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function documents()
    {
        return $this->hasMany(VendorDocument::class);
    }
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function offerSheets()
    {
        return $this->hasMany(OfferSheet::class);
    }
    public function consignments()
    {
        return $this->hasMany(Consignment::class);
    }
    public function orders()
    {
        return $this->hasManyThrough(Order::class, OrderItem::class, 'vendor_id', 'id', 'id', 'order_id');
    }
    public function chargebacks()
    {
        return $this->hasMany(Chargeback::class);
    }
    public function warehouseCharges()
    {
        return $this->hasMany(WarehouseCharge::class);
    }
    public function payouts()
    {
        return $this->hasMany(VendorPayout::class);
    }
    public function kycApprover()
    {
        return $this->belongsTo(User::class, 'kyc_approved_by');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    public function scopeByCompanyCode($query, $code)
    {
        return $query->where('company_code', $code);
    }
    public function scopePendingKyc($query)
    {
        return $query->where('kyc_status', 'submitted');
    }
    public function scopePendingContract($query)
    {
        return $query->where('contract_status', 'sent');
    }
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
    public function isKycApproved(): bool
    {
        return $this->kyc_status === 'approved';
    }
    public function isContractSigned(): bool
    {
        return $this->contract_status === 'signed';
    }

    public static function generateVendorCode(string $companyCode): string
    {
        $prefix = match($companyCode) {
            '2000' => 'VIN',
            '2100' => 'VUS',
            '2200' => 'VNL',
            default => 'VXX',
        };
        $lastVendor = self::where('company_code', $companyCode)->orderBy('id', 'desc')->first();
        $nextNumber = $lastVendor ? intval(substr($lastVendor->vendor_code, 3)) + 1 : 1;
        return $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
