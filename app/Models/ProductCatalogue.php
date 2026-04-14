<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCatalogue extends Model
{
    protected $fillable = [
        'product_id', 'sales_channel_id', 'platform_pricing_id', 'company_code',
        'listing_sku', 'listing_url', 'shopify_url', 'listing_status',
        'catalogue_details', 'listed_by', 'listed_at',
    ];

    protected $casts = [
        'catalogue_details' => 'array',
        'listed_at' => 'datetime',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function salesChannel() { return $this->belongsTo(SalesChannel::class); }
    public function platformPricing() { return $this->belongsTo(PlatformPricing::class); }
    public function lister() { return $this->belongsTo(User::class, 'listed_by'); }

    public function scopeListed($query) { return $query->where('listing_status', 'listed'); }
    public function scopePending($query) { return $query->where('listing_status', 'pending'); }
}
