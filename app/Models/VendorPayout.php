<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorPayout extends Model
{
    protected $fillable = [
        'vendor_id', 'company_code', 'payout_month', 'payout_year',
        'total_sales', 'total_commission', 'gross_payout',
        'total_warehouse_charges', 'total_chargebacks',
        'total_storage_charges', 'total_inward_charges',
        'total_logistics_charges', 'total_platform_deductions',
        'total_other_deductions', 'total_shipped_qty',
        'net_payout', 'status', 'payment_date',
        'payment_reference', 'payment_method', 'payment_advice_file',
        'vendor_invoice_file', 'vendor_invoice_number',
        'approved_by', 'paid_by', 'remarks',
        'calculation_snapshot',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'calculation_snapshot' => 'array',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function payer() { return $this->belongsTo(User::class, 'paid_by'); }

    public function scopeByMonth($query, int $month, int $year)
    {
        return $query->where('payout_month', $month)->where('payout_year', $year);
    }

    public function scopePending($query) { return $query->where('status', 'calculated' ); }

    public function getPeriodAttribute(): string
    {
        return date('M Y', mktime(0, 0, 0, $this->payout_month, 1, $this->payout_year));
    }
}
