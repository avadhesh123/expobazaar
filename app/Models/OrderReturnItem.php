<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderReturnItem extends Model
{
    protected $fillable = [
        'order_return_id',
        'order_item_id',
        'product_id',
        'sku',
        'return_qty',
        'unit_price',
        'return_amount',
        'condition_status',
        'restock',
        'restocked_qty',
        'notes',
    ];

    public function orderReturn()
    {
        return $this->belongsTo(OrderReturn::class);
    }
    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id')
            ->withoutGlobalScopes();
    }
    // public function product()
    // {
    //     return $this->belongsTo(Product::class, 'product_id');
    // }
    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
