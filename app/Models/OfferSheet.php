<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfferSheet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'offer_sheet_number', 'vendor_id', 'company_code', 'status',
        'total_products', 'selected_products', 'reviewed_by', 'reviewed_at', 'remarks',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function items() { return $this->hasMany(OfferSheetItem::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function consignment() { return $this->hasOne(Consignment::class); }

    public function selectedItems() { return $this->items()->where('is_selected', true); }

    public static function generateNumber(string $companyCode): string
    {
        $prefix = 'OS-' . $companyCode . '-';
        $last = self::where('offer_sheet_number', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        $next = $last ? intval(substr($last->offer_sheet_number, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
