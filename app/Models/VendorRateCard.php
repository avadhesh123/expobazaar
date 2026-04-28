<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorRateCard extends Model
{
    protected $fillable = [
        'vendor_id',
        'company_code',
        'currency',
        'inward_rate_per_carton',
        'storage_rate_per_cft',
        'fulfillment_rate_small',
        'fulfillment_rate_large',
        'fulfillment_qty_threshold',
        'pick_pack_rate_per_unit',
        'effective_from',
        'effective_to',
        'version',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'vendor_acknowledged',
        'vendor_acknowledged_at',
        'notes',
    ];

    protected $casts = [
        'inward_rate_per_carton'  => 'decimal:4',
        'storage_rate_per_cft'   => 'decimal:4',
        'fulfillment_rate_small' => 'decimal:4',
        'fulfillment_rate_large' => 'decimal:4',
        'pick_pack_rate_per_unit' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'approved_at' => 'datetime',
        'vendor_acknowledged' => 'boolean',
        'vendor_acknowledged_at' => 'datetime',
    ];
public function warehouse()
{
    return $this->belongsTo(\App\Models\Warehouse::class, 'warehouse_id');
}
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
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
    // public function scopeActive($query)
    // {
    //     return $query->where(1);
    // }
    public static function getActive(int $vendorId, ?string $date = null): ?self
    {
        return static::where('vendor_id', $vendorId)->approved()->effectiveOn($date)->orderByDesc('version')->first();
    }

    public function getCurrencySymbol(): string
    {
        return $this->currency === 'EUR' ? '€' : '$';
    }

    public function isComplete(): bool
    {
        return $this->inward_rate_per_carton > 0 && $this->storage_rate_per_cft > 0
            && $this->fulfillment_rate_small > 0 && $this->fulfillment_rate_large > 0
            && $this->fulfillment_qty_threshold > 0 && $this->pick_pack_rate_per_unit > 0;
    }
}
