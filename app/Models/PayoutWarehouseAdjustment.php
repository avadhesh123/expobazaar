<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutWarehouseAdjustment extends Model
{
    protected $fillable = [
        'vendor_payout_id',
        'amount',
        'reason',
        'remarks',
        'adjustment_date',
        'created_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'adjustment_date'  => 'date',
    ];

    public function payout()
    {
        return $this->belongsTo(VendorPayout::class, 'vendor_payout_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
}
