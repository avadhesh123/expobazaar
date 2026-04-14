<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chargeback extends Model
{
    protected $fillable = [
        'order_id', 'vendor_id', 'company_code', 'amount', 'reason',
        'description', 'status', 'raised_by', 'confirmed_by',
        'confirmed_at', 'confirmation_remarks',
    ];

    protected $casts = ['confirmed_at' => 'datetime'];

    public function order() { return $this->belongsTo(Order::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function raiser() { return $this->belongsTo(User::class, 'raised_by'); }
    public function confirmer() { return $this->belongsTo(User::class, 'confirmed_by'); }

    public function scopePending($query) { return $query->where('status', 'pending_confirmation'); }
}
