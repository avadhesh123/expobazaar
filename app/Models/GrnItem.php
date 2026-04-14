<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrnItem extends Model
{
    protected $fillable = [
        'grn_id', 'product_id', 'consignment_id', 'expected_quantity',
        'received_quantity', 'damaged_quantity', 'missing_quantity', 'remarks',
    ];

    public function grn() { return $this->belongsTo(Grn::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function consignment() { return $this->belongsTo(Consignment::class); }
}
