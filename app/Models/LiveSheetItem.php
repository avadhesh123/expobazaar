<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSheetItem extends Model
{
    protected $fillable = [
        'live_sheet_id', 'product_id', 'consignment_id', 'quantity',
        'unit_price', 'total_price', 'cbm_per_unit', 'total_cbm',
        'weight_per_unit', 'total_weight', 'product_details',
    ];

    protected $casts = ['product_details' => 'array'];

    public function liveSheet() { return $this->belongsTo(LiveSheet::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function consignment() { return $this->belongsTo(Consignment::class); }
}
