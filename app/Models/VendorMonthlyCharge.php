<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorMonthlyCharge extends Model
{
    protected $table = 'vendor_monthly_charges';

    protected $fillable = [
        'vendor_id', 'grn_id', 'warehouse_id', 'company_code', 'currency',
        'charge_month', 'charge_year', 'rate_card_id',
        'inward_cartons', 'inward_charge',
        'storage_remaining_qty', 'storage_cft', 'storage_charge',
        'fulfillment_orders_small', 'fulfillment_orders_large', 'fulfillment_charge',
        'pick_pack_units', 'pick_pack_charge',
        'material_cost', 'total_charges',
        'status', 'approved_by', 'approved_at', 'deducted_from_payout_id',
        'is_locked', 'calculation_snapshot', 'notes', 'created_by',
    ];

    protected $casts = [
        'inward_charge'    => 'decimal:2', 'storage_charge'     => 'decimal:2',
        'fulfillment_charge' => 'decimal:2', 'pick_pack_charge' => 'decimal:2',
        'material_cost'    => 'decimal:2', 'total_charges'      => 'decimal:2',
        'storage_cft'      => 'decimal:4',
        'approved_at'      => 'datetime', 'is_locked' => 'boolean',
        'calculation_snapshot' => 'array',
    ];

    public function vendor()    { return $this->belongsTo(Vendor::class); }
    public function grn()       { return $this->belongsTo(Grn::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function rateCard()  { return $this->belongsTo(VendorRateCard::class, 'rate_card_id'); }
    public function approver()  { return $this->belongsTo(User::class, 'approved_by'); }
    public function payout()    { return $this->belongsTo(VendorPayout::class, 'deducted_from_payout_id'); }

    public function scopeByMonth($q, $m, $y) { return $q->where('charge_month', $m)->where('charge_year', $y); }

    public function getPeriodAttribute(): string
    {
        return date('M', mktime(0, 0, 0, $this->charge_month, 1)) . ' ' . $this->charge_year;
    }

    public function getCurrencySymbol(): string { return $this->currency === 'EUR' ? '€' : '$'; }
}
