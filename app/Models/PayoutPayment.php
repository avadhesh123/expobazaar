<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutPayment extends Model
{
    protected $fillable = [
        'vendor_payout_id', 'vendor_id', 'company_code',
        'amount', 'payment_date', 'payment_mode',
        'reference_number', 'remarks', 'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function payout() { return $this->belongsTo(VendorPayout::class, 'vendor_payout_id'); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
