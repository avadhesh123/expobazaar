<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseCharge extends Model
{
    protected $fillable = [
        'warehouse_id', 'vendor_id', 'company_code', 'charge_month', 'charge_year',
        'charge_type', 'charge_category', 'calculated_amount', 'actual_amount', 'variance',
        'variance_comment', 'reason_code', 'receipt_file', 'invoice_number', 'invoice_date',
        'invoice_file', 'status', 'uploaded_by', 'approved_by', 'approved_at',
        'deducted_from_payout', 'payout_id', 'notes',
    ];

    protected $casts = [
        'calculated_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'invoice_date' => 'date',
        'approved_at' => 'datetime',
        'deducted_from_payout' => 'boolean',
    ];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function payout() { return $this->belongsTo(VendorPayout::class, 'payout_id'); }
    public function items() { return $this->hasMany(WarehouseChargeItem::class); }

    public function scopePayable($query) { return $query->where('charge_category', 'payable'); }
    public function scopeReceivable($query) { return $query->where('charge_category', 'receivable'); }

    public function scopeByMonth($query, int $month, int $year)
    {
        return $query->where('charge_month', $month)->where('charge_year', $year);
    }

    public function calculateVariance(): float
    {
        $this->variance = floatval($this->actual_amount ?? 0) - floatval($this->calculated_amount ?? 0);
        return $this->variance;
    }

    public function getPeriodAttribute(): string
    {
        return date('M', mktime(0, 0, 0, $this->charge_month, 1)) . ' ' . $this->charge_year;
    }
}
