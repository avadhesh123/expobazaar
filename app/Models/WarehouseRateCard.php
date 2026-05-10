<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseRateCard extends Model
{
    protected $fillable = [
        'warehouse_id', 'company_code', 'currency',
        'wh_inward_rate_per_carton', 'wh_storage_rate_per_cft',
        'wh_fulfillment_rate_small', 'wh_fulfillment_rate_large', 'wh_fulfillment_qty_threshold',
        'wh_pick_pack_rate_per_unit',
        'effective_from', 'effective_to', 'version', 'status',
        'contract_file', 'created_by', 'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime',
        'wh_inward_rate_per_carton' => 'decimal:4', 'wh_storage_rate_per_cft' => 'decimal:4',
        'wh_fulfillment_rate_small' => 'decimal:4', 'wh_fulfillment_rate_large' => 'decimal:4',
        'wh_pick_pack_rate_per_unit' => 'decimal:4',
    ];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator()   { return $this->belongsTo(User::class, 'created_by'); }
    public function approver()  { return $this->belongsTo(User::class, 'approved_by'); }

    public function scopeApproved($q) { return $q->where('status', 'approved'); }

    public function scopeEffectiveOn($q, $date = null)
    {
        $date = $date ?? now()->toDateString();
        return $q->where('effective_from', '<=', $date)
                  ->where(fn($q2) => $q2->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    public static function getActive(int $warehouseId, ?string $date = null): ?self
    {
        return static::where('warehouse_id', $warehouseId)->approved()->effectiveOn($date)->orderByDesc('version')->first();
    }

    public function getCurrencySymbol(): string { return match($this->currency) { 'INR' => '₹', 'EUR' => '€', default => '$' }; }

    public function isComplete(): bool
    {
        return $this->wh_inward_rate_per_carton > 0 && $this->wh_storage_rate_per_cft > 0
            && $this->wh_fulfillment_rate_small > 0 && $this->wh_fulfillment_rate_large > 0
            && $this->wh_fulfillment_qty_threshold > 0 && $this->wh_pick_pack_rate_per_unit > 0;
    }
}
