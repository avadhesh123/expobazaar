<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceReceivable extends Model
{
    protected $fillable = [
        'order_id', 'sales_channel_id', 'company_code', 'order_amount',
        'platform_commission', 'platform_fee', 'insurance_charge',
        'chargeback_amount', 'other_deductions', 'deduction_notes',
        'net_receivable', 'payment_status', 'amount_received',
        'payment_date', 'payment_reference', 'bank_reference', 'updated_by',
    ];

    protected $casts = ['payment_date' => 'date'];

    public function order() { return $this->belongsTo(Order::class); }
    public function salesChannel() { return $this->belongsTo(SalesChannel::class); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeUnpaid($query) { return $query->where('payment_status', 'unpaid'); }
    public function scopeByCompanyCode($query, $code) { return $query->where('company_code', $code); }

    public function calculateNetReceivable(): float
    {
        return $this->order_amount - $this->platform_commission - $this->platform_fee
            - $this->insurance_charge - $this->chargeback_amount - $this->other_deductions;
    }
}
