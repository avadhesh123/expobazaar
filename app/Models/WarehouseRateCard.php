<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseRateCard extends Model
{
    protected $fillable = [
        'warehouse_id',
        'company_code',
        'currency',
        'warehouse_id',
        'unloading_fcl',
        'unloading_lcl_palletize',
        'unloading_carton',
        'put_away_per_carton',
        'checkin_per_qty',
        'checkin_per_hours',
        'storage_per_pallet',
        'storage_per_cft',
        'order_processing_palletize',
        'order_processing_non_palletize',
        'pick_pack',
        'fulfillment_rate_small',
        'fulfillment_rate_large',
        'fulfillment_qty_threshold',
        'manpower_cost',
        'return_inward_per_qty',
        'return_inward_per_carton',
        'effective_from',
        'effective_to',
        'version',
        'status',
        'contract_file',
        'created_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'approved_at' => 'datetime',
       
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

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

    public function getCurrencySymbol(): string
    {
        return match ($this->currency) {
            'INR' => '₹',
            'EUR' => '€',
            default => '$'
        };
    }

    public function isComplete(): bool
    {
        return $this->wh_inward_rate_per_carton > 0 && $this->wh_storage_rate_per_cft > 0
            && $this->wh_fulfillment_rate_small > 0 && $this->wh_fulfillment_rate_large > 0
            && $this->wh_fulfillment_qty_threshold > 0 && $this->wh_pick_pack_rate_per_unit > 0;
    }
}
