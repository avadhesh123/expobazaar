<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'tracking_id',
        'shipping_provider',
        'tracking_url',
        'shipped_date',
        'estimated_delivery_date',
        'notes',
        'added_by'
    ];

    protected $casts = [
        'shipped_date' => 'date',
        'estimated_delivery_date' => 'date',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
