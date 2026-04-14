<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'company_code', 'type', 'parent_warehouse_id',
        'address', 'city', 'state', 'country', 'pincode',
        'contact_person', 'contact_phone', 'contact_email',
        'inward_rate_per_cbm', 'storage_rate_per_cbm_month', 'pick_pack_rate',
        'consumable_rate', 'last_mile_rate', 'rate_card', 'is_active',
    ];

    protected $casts = [
        'rate_card' => 'array',
        'is_active' => 'boolean',
    ];

    public function parentWarehouse() { return $this->belongsTo(Warehouse::class, 'parent_warehouse_id'); }
    public function subWarehouses() { return $this->hasMany(Warehouse::class, 'parent_warehouse_id'); }
    public function subLocations() { return $this->hasMany(WarehouseSubLocation::class); }
    public function inventory() { return $this->hasMany(Inventory::class); }
    public function grns() { return $this->hasMany(Grn::class); }
    public function charges() { return $this->hasMany(WarehouseCharge::class); }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeMain($query) { return $query->where('type', 'main'); }
    public function scopeByCompanyCode($query, $code) { return $query->where('company_code', $code); }
}
