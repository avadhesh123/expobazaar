<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderReturn extends Model
{
    protected $fillable = [
        'return_number', 'order_id', 'company_code', 'vendor_id', 'return_date',
        'reason', 'reason_detail', 'status', 'total_return_amount', 'refund_amount',
        'refund_status', 'warehouse_id', 'tracking_id', 'carrier', 'received_date',
        'inspected_by', 'inspected_at', 'inspection_notes',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'return_date'   => 'date',
        'received_date' => 'date',
        'inspected_at'  => 'datetime',
        'approved_at'   => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function items() { return $this->hasMany(OrderReturnItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function inspector() { return $this->belongsTo(User::class, 'inspected_by'); }

    public static function generateNumber(string $companyCode): string
    {
        $prefix = "RET-{$companyCode}-";
        $last = static::where('return_number', 'LIKE', "{$prefix}%")
            ->orderByRaw("CAST(SUBSTRING(return_number, " . (strlen($prefix) + 1) . ") AS UNSIGNED) DESC")
            ->value('return_number');
        $next = $last ? intval(substr($last, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
