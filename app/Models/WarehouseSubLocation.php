<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseSubLocation extends Model
{
    protected $fillable = ['warehouse_id', 'name', 'code', 'type', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function inventory() { return $this->hasMany(Inventory::class); }
}
