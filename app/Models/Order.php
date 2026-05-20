<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'platform_order_id',
        'sales_channel_id',
        'customer_id',
        'company_code',
        'order_date',
        'subtotal',
        'shipping_amount',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'tracking_id',
        'tracking_url',
        'shipping_provider',
        'shipment_status',
        'shipped_date',
        'delivered_date',
        'customer_name',
        'customer_email',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_country',
        'shipping_pincode',
        'payment_status',
        'status',
        'uploaded_by',
        'remarks',
        'invoice_number',
        'customer_phone',
        'customer_type',
        'company_name',
        'shipping_method',
        'warehouse_id',
        'shipped_qty',
        'shipped_amount',
        'shipping_cost',
        'carrier',
        'ship_date',
        'current_status',
        'delivery_date',
        'material_cost',
        'order_processing_charges',
    ];

    protected $casts = [
        'order_date' => 'date',
        'shipped_date' => 'date',
        'delivered_date' => 'date',
        'ship_date' => 'date',
        'delivery_date' => 'date',
    ];

    public function salesChannel()
    {
        return $this->belongsTo(SalesChannel::class);
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function receivable()
    {
        return $this->hasOne(FinanceReceivable::class);
    }
    public function chargebacks()
    {
        return $this->hasMany(Chargeback::class);
    }
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class);
    }

    public function scopeByCompanyCode($query, $code)
    {
        return $query->where('company_code', $code);
    }
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }
    public function scopePendingShipment($query)
    {
        return $query->where('shipment_status', 'pending');
    }
    // In Order Model
    public function scopeByOrderNumber($query, string $orderNumber)
    {
        return $query->where('order_number', $orderNumber);
    }
    public static function findByOrderNumber(string $orderNumber)
    {
        return self::where('order_number', $orderNumber)->first();
    }
    public static function generateOrderNumber(string $companyCode): string
    {
        $prefix = 'ORD-' . $companyCode . '-';
        $last = self::where('order_number', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        $next = $last ? intval(substr($last->order_number, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
    }
}
