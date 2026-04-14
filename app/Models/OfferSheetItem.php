<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferSheetItem extends Model
{
    protected $fillable = [
        'offer_sheet_id', 'product_id', 'product_name', 'product_sku',
        'category_id', 'vendor_price', 'currency', 'thumbnail',
        'product_details', 'is_selected', 'selected_by', 'selected_at',
    ];

    protected $casts = [
        'product_details' => 'array',
        'is_selected' => 'boolean',
        'selected_at' => 'datetime',
    ];

    public function offerSheet() { return $this->belongsTo(OfferSheet::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function selector() { return $this->belongsTo(User::class, 'selected_by'); }
}
