<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseChargeItem extends Model
{
    protected $fillable = [
        'warehouse_charge_id', 'charge_key', 'charge_label',
        'uom', 'quantity', 'rate', 'amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
    ];

    public function warehouseCharge() { return $this->belongsTo(WarehouseCharge::class); }
}
