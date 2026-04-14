<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSheet extends Model
{
    protected $fillable = [
        'consignment_id', 'live_sheet_number', 'status', 'total_cbm',
        'is_locked', 'locked_by', 'locked_at', 'unlocked_by', 'unlocked_at',
        'approved_by', 'approved_at', 'remarks',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'unlocked_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_cbm' => 'decimal:4',
    ];

    public function consignment() { return $this->belongsTo(Consignment::class); }
    public function items() { return $this->hasMany(LiveSheetItem::class); }
    public function lockedByUser() { return $this->belongsTo(User::class, 'locked_by'); }
    public function approvedByUser() { return $this->belongsTo(User::class, 'approved_by'); }

    public function scopeLocked($query) { return $query->where('is_locked', true); }
    public function scopeApproved($query) { return $query->where('status', 'approved'); }

    public function recalculateCbm(): void
    {
        $this->total_cbm = $this->items()->sum('total_cbm');
        $this->save();
    }

    public static function generateNumber(string $consignmentNumber): string
    {
        return 'LS-' . str_replace('CON-', '', $consignmentNumber);
    }
}
