<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'platform_order_id', 'sales_channel_id', 'customer_id',
        'company_code', 'order_date', 'subtotal', 'shipping_amount', 'tax_amount',
        'discount_amount', 'total_amount', 'currency', 'tracking_id', 'tracking_url',
        'shipping_provider', 'shipment_status', 'shipped_date', 'delivered_date',
        'customer_name', 'customer_email', 'shipping_address', 'shipping_city',
        'shipping_state', 'shipping_country', 'shipping_pincode',
        'payment_status', 'status', 'uploaded_by', 'remarks',
    ];

    protected $casts = [
        'order_date' => 'date',
        'shipped_date' => 'date',
        'delivered_date' => 'date',
    ];

    public function salesChannel() { return $this->belongsTo(SalesChannel::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function receivable() { return $this->hasOne(FinanceReceivable::class); }
    public function chargebacks() { return $this->hasMany(Chargeback::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function scopeByCompanyCode($query, $code) { return $query->where('company_code', $code); }
    public function scopeUnpaid($query) { return $query->where('payment_status', 'unpaid'); }
    public function scopePendingShipment($query) { return $query->where('shipment_status', 'pending'); }

    public static function generateOrderNumber(string $companyCode): string
    {
        $prefix = 'ORD-' . $companyCode . '-';
        $last = self::where('order_number', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        $next = $last ? intval(substr($last->order_number, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
