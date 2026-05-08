<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseMonthlyCharge extends Model
{
    protected $fillable = [
        'warehouse_id', 'company_code', 'currency', 'charge_month', 'charge_year', 'rate_card_id',
        'expected_inward', 'expected_storage', 'expected_fulfillment', 'expected_pick_pack', 'expected_total',
        'actual_inward', 'actual_storage', 'actual_fulfillment', 'actual_pick_pack', 'actual_other', 'actual_total',
        'variance_inward', 'variance_storage', 'variance_fulfillment', 'variance_pick_pack', 'variance_total',
        'invoice_number', 'invoice_date', 'invoice_file', 'variance_explanations',
        'status', 'tolerance_pct', 'tolerance_abs', 'calculation_snapshot',
        'calculated_by', 'calculated_at', 'invoice_entered_by', 'reviewed_by',
        'approved_by', 'approved_at', 'remarks',
    ];

    protected $casts = [
        'invoice_date' => 'date', 'approved_at' => 'datetime', 'calculated_at' => 'datetime',
        'variance_explanations' => 'array', 'calculation_snapshot' => 'array',
    ];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function rateCard()  { return $this->belongsTo(WarehouseRateCard::class, 'rate_card_id'); }
    public function grnDetails(){ return $this->hasMany(WarehouseChargeGrnDetail::class); }
    public function approver()  { return $this->belongsTo(User::class, 'approved_by'); }

    public function scopeByMonth($q, $m, $y) { return $q->where('charge_month', $m)->where('charge_year', $y); }

    public function getPeriodAttribute(): string { return date('M', mktime(0,0,0,$this->charge_month,1)).' '.$this->charge_year; }
    public function getCurrencySymbol(): string { return $this->currency === 'EUR' ? '€' : '$'; }

    public function calculateVariances(): void
    {
        $this->variance_inward      = floatval($this->actual_inward ?? 0) - floatval($this->expected_inward);
        $this->variance_storage     = floatval($this->actual_storage ?? 0) - floatval($this->expected_storage);
        $this->variance_fulfillment = floatval($this->actual_fulfillment ?? 0) - floatval($this->expected_fulfillment);
        $this->variance_pick_pack   = floatval($this->actual_pick_pack ?? 0) - floatval($this->expected_pick_pack);
        $this->variance_total       = floatval($this->actual_total ?? 0) - floatval($this->expected_total);
    }

    public function isOverLimit(string $field): bool
    {
        $variance = abs(floatval($this->{'variance_' . $field} ?? 0));
        $expected = abs(floatval($this->{'expected_' . $field} ?? 0));
        if ($variance > floatval($this->tolerance_abs)) return true;
        if ($expected > 0 && ($variance / $expected * 100) > floatval($this->tolerance_pct)) return true;
        return false;
    }
}
