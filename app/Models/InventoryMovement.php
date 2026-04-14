<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id', 'movement_type', 'from_warehouse_id', 'to_warehouse_id',
        'from_sub_location_id', 'to_sub_location_id', 'quantity',
        'reference_type', 'reference_id', 'remarks', 'performed_by',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function fromWarehouse() { return $this->belongsTo(Warehouse::class, 'from_warehouse_id'); }
    public function toWarehouse() { return $this->belongsTo(Warehouse::class, 'to_warehouse_id'); }
    public function performer() { return $this->belongsTo(User::class, 'performed_by'); }
}
