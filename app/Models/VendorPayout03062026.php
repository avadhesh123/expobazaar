<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPayout extends Model
{
    protected $fillable = [
        'vendor_id', 'company_code', 'payout_month', 'payout_year',
        'total_sales', 'total_storage_charges', 'total_inward_charges',
        'total_logistics_charges', 'total_platform_deductions', 'total_chargebacks',
        'total_other_deductions', 'net_payout', 'status', 'payment_date',
        'payment_reference', 'payment_method', 'payment_advice_file',
        'vendor_invoice_file', 'approved_by', 'paid_by', 'remarks',
    ];

    protected $casts = ['payment_date' => 'date'];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function payer() { return $this->belongsTo(User::class, 'paid_by'); }

    public function scopeByMonth($query, int $month, int $year)
    {
        return $query->where('payout_month', $month)->where('payout_year', $year);
    }

    public function scopePending($query) { return $query->where('status', 'payment_pending'); }

    public function calculateNetPayout(): float
    {
        return $this->total_sales - $this->total_storage_charges - $this->total_inward_charges
            - $this->total_logistics_charges - $this->total_platform_deductions
            - $this->total_chargebacks - $this->total_other_deductions;
    }
}
