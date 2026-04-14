<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'sku', 'sap_code', 'name', 'description', 'category_id', 'vendor_id', 'company_code',
        'length_cm', 'width_cm', 'height_cm', 'weight_kg', 'cbm', 'color', 'material',
        'variations', 'vendor_price', 'fob_price', 'currency', 'thumbnail', 'images',
        'status', 'hsn_code', 'barcode', 'stock_quantity', 'reserved_quantity',
        'shopify_url', 'platform_listing_status',
    ];

    protected $casts = [
        'variations' => 'array',
        'images' => 'array',
        'platform_listing_status' => 'array',
        'vendor_price' => 'decimal:2',
        'fob_price' => 'decimal:2',
        'cbm' => 'decimal:4',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
    public function inventory()
    {
        return $this->hasMany(Inventory::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function catalogues()
    {
        return $this->hasMany(ProductCatalogue::class);
    }
    public function platformPricing()
    {
        return $this->hasMany(PlatformPricing::class);
    }
    public function liveSheetItems()
    {
        return $this->hasMany(LiveSheetItem::class);
    }

    public function scopeByCompanyCode($query, $code)
    {
        return $query->where('company_code', $code);
    }
    public function scopeListed($query)
    {
        return $query->where('status', 'listed');
    }
    public function scopeAvailable($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function calculateCbm(): float
    {
        if ($this->length_cm && $this->width_cm && $this->height_cm) {
            return ($this->length_cm * $this->width_cm * $this->height_cm) / 1000000;
        }
        return 0;
    }

    public function getAvailableStock(): int
    {
        return $this->stock_quantity - $this->reserved_quantity;
    }

    public function getTotalInventory(): int
    {
        return $this->inventory()->sum('available_quantity');
    }

    public static function generateSku(string $companyCode, int $categoryId): string
    {
        $prefix = match($companyCode) {
            '2000' => 'IN',
            '2100' => 'US',
            '2200' => 'NL',
            default => 'XX',
        };
        $catPrefix = str_pad($categoryId, 3, '0', STR_PAD_LEFT);
        $count = self::where('company_code', $companyCode)->count() + 1;
        return $prefix . $catPrefix . str_pad($count, 6, '0', STR_PAD_LEFT);
    }
}
