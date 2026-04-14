<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformPricing extends Model
{
    protected $table = 'platform_pricing';

    protected $fillable = [
        'asn_id', 'product_id', 'sales_channel_id', 'company_code',
        'cost_price', 'platform_price', 'selling_price', 'map_price',
        'margin_percent', 'status', 'prepared_by', 'finance_reviewed_by',
        'approved_by', 'approved_at', 'remarks',
    ];

    protected $casts = ['approved_at' => 'datetime'];

    public function asn() { return $this->belongsTo(Asn::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function salesChannel() { return $this->belongsTo(SalesChannel::class); }
    public function preparer() { return $this->belongsTo(User::class, 'prepared_by'); }
    public function financeReviewer() { return $this->belongsTo(User::class, 'finance_reviewed_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }

    public function calculateMargin(): float
    {
        if ($this->selling_price > 0) {
            return round((($this->selling_price - $this->cost_price) / $this->selling_price) * 100, 2);
        }
        return 0;
    }
}
