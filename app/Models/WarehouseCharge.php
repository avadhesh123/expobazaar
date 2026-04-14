<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseCharge extends Model
{
    protected $fillable = [
        'warehouse_id', 'vendor_id', 'company_code', 'charge_month', 'charge_year',
        'charge_type', 'calculated_amount', 'actual_amount', 'variance',
        'variance_comment', 'receipt_file', 'status', 'uploaded_by',
    ];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function scopeByMonth($query, int $month, int $year)
    {
        return $query->where('charge_month', $month)->where('charge_year', $year);
    }

    public function calculateVariance(): float
    {
        return $this->actual_amount - $this->calculated_amount;
    }
}
