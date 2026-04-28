<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorRateCard extends Model
{
    protected $fillable = [
        'vendor_id', 'warehouse_id', 'company_code', 'charge_key',
        'charge_label', 'charge_type', 'uom', 'rate',
        'effective_from', 'effective_to', 'is_active', 'created_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive($query) { return $query->where('is_active', true); }

    public function scopeEffectiveOn($query, $date = null)
    {
        $date = $date ?? now()->toDateString();
        return $query->where(function ($q) use ($date) {
            $q->where(function ($q2) use ($date) {
                $q2->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
            })->where(function ($q2) use ($date) {
                $q2->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            });
        });
    }
}
