<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseChargeGrnDetail extends Model
{
    protected $fillable = [
        'warehouse_monthly_charge_id', 'grn_id', 'vendor_id',
        'inward_cartons', 'inward_charge',
        'storage_qty', 'storage_cft', 'storage_charge',
        'fulfillment_orders_small', 'fulfillment_orders_large', 'fulfillment_charge',
        'pick_pack_units', 'pick_pack_charge', 'total_charge',
    ];

    public function monthlyCharge() { return $this->belongsTo(WarehouseMonthlyCharge::class, 'warehouse_monthly_charge_id'); }
    public function grn()           { return $this->belongsTo(Grn::class); }
    public function vendor()        { return $this->belongsTo(Vendor::class); }
}
