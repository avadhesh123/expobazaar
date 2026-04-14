<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'customer_code', 'name', 'email', 'phone', 'address',
        'city', 'state', 'country', 'pincode', 'sales_channel_id', 'company_code',
    ];

    public function salesChannel() { return $this->belongsTo(SalesChannel::class); }
    public function orders() { return $this->hasMany(Order::class); }

    public static function generateCode(): string
    {
        $last = self::orderBy('id', 'desc')->first();
        $next = $last ? intval(substr($last->customer_code, 4)) + 1 : 1;
        return 'CUS-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
