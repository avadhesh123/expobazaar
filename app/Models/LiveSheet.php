<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSheet extends Model
{
    protected $fillable = [
        'consignment_id',
        'offer_sheet_id',
        'vendor_id',
        'company_code',
        'live_sheet_number',
        'status',
        'total_cbm',
        'is_locked',
        'locked_by',
        'locked_at',
        'unlocked_by',
        'unlocked_at',
        'approved_by',
        'approved_at',
        'remarks',
        'ex_factory_date',
        'final_inspection_date'
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'unlocked_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_cbm' => 'decimal:4',
        'ex_factory_date' => 'date',
        'final_inspection_date' => 'date',
    ];

    public function consignment()
    {
        return $this->belongsTo(Consignment::class);
    }
    public function offerSheet()
    {
        return $this->belongsTo(OfferSheet::class);
    }
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
    public function items()
    {
        return $this->hasMany(LiveSheetItem::class);
    }
    public function lockedByUser()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function recalculateCbm(): void
    {
        $this->total_cbm = $this->items()->sum('total_cbm');
        $this->save();
    }

    public static function generateNumber(string $reference): string
    {
        $ref = str_replace(['CON-', 'OS-'], '', $reference);
        return 'LS-' . $ref;
    }
    // app/Models/LiveSheet.php

    public function canBeLocked(): bool
    {
        // All items must have a SAP code before locking
        return $this->items()
            ->whereNull('product_details->sap_code')  // or wherever you store SAP code
            ->doesntExist();
    }

    public function isLocked(): bool
    {
        return $this->is_locked === true || $this->status === 'locked';
    }

    // Optional: Scope
    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }
}
