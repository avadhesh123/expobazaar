<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'vendor_id', 'quantity',
        'unit_price', 'total_price', 'discount', 'tax', 'sku',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
}
