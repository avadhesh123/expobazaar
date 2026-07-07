<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRevision extends Model
{
    protected $fillable = [
        'live_sheet_id', 'vendor_id', 'company_code',
        'commission_percentage', 'effective_from', 'effective_to',
        'remarks', 'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'commission_percentage' => 'decimal:2',
    ];

    public function liveSheet() { return $this->belongsTo(LiveSheet::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    /**
     * Get active commission for a given date
     */
    public static function getActiveRate(int $liveSheetId, ?string $date = null): ?float
    {
        $date = $date ?? now()->toDateString();
        $revision = static::where('live_sheet_id', $liveSheetId)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();

        return $revision?->commission_percentage;
    }
}
