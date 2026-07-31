<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WspRevision extends Model
{
    protected $fillable = [
        'live_sheet_id', 'live_sheet_item_id', 'product_id',
        'vendor_id', 'company_code', 'vendor_wsp',
        'effective_from', 'effective_to',
        'remarks', 'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'vendor_wsp'     => 'decimal:2',
    ];

    public function liveSheet()
    {
        return $this->belongsTo(LiveSheet::class);
    }
    public function liveSheetItem()
    {
        return $this->belongsTo(LiveSheetItem::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get active WSP for a live sheet + product on a given date
     */
    public static function getActiveWsp(int $liveSheetId, ?int $productId = null, ?string $date = null): ?array
    {
        $date = $date ?? now()->toDateString();

        $query = static::where('live_sheet_id', $liveSheetId)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderByDesc('effective_from');

        // Try product-specific first
        if ($productId) {
            $productSpecific = (clone $query)->where('product_id', $productId)->first();
            if ($productSpecific) {
                return [
                    'wsp' => floatval($productSpecific->vendor_wsp),
                    'effective_from' => $productSpecific->effective_from,
                    'effective_to' => $productSpecific->effective_to
                ];
            }
        }

        // Fallback to live-sheet-level WSP (product_id = null)
        $sheetLevel = $query->whereNull('product_id')->first();
        return $sheetLevel ? [
            'wsp' => floatval($sheetLevel->vendor_wsp),
            'effective_from' => '',
            'effective_to' => ''
        ] : null;
    }
}
