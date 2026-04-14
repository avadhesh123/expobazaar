<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventory';

    protected $fillable = [
        'product_id', 'warehouse_id', 'warehouse_sub_location_id', 'company_code',
        'quantity', 'reserved_quantity', 'available_quantity',
        'received_date', 'grn_id', 'consignment_id',
    ];

    protected $casts = ['received_date' => 'date'];

    public function product() { return $this->belongsTo(Product::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function subLocation() { return $this->belongsTo(WarehouseSubLocation::class, 'warehouse_sub_location_id'); }
    public function grn() { return $this->belongsTo(Grn::class); }
    public function consignment() { return $this->belongsTo(Consignment::class); }

    public function getAgeingDays(): int
    {
        return $this->received_date ? now()->diffInDays($this->received_date) : 0;
    }

    public function scopeByCompanyCode($query, $code) { return $query->where('company_code', $code); }
    public function scopeByWarehouse($query, $warehouseId) { return $query->where('warehouse_id', $warehouseId); }
    public function scopeAgeing($query, int $days) { return $query->where('received_date', '<=', now()->subDays($days)); }
}
