<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveSheetItemChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'live_sheet_item_id', 'live_sheet_id', 'product_id',
        'field_name', 'old_value', 'new_value',
        'changed_by', 'changed_by_role', 'change_reason',
        'revision_number', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function item() { return $this->belongsTo(LiveSheetItem::class, 'live_sheet_item_id'); }
    public function liveSheet() { return $this->belongsTo(LiveSheet::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function user() { return $this->belongsTo(User::class, 'changed_by'); }

    /**
     * Track changes for a live sheet item — compares old vs new and logs diffs
     */
    public static function trackChanges(LiveSheetItem $item, array $newDetails, User $user, string $role, ?string $reason = null): int
    {
        $trackedFields = [
            'target_fob', 'final_qty', 'final_fob', 'freight_factor', 'wsp_factor', 'comments',
            'vendor_fob', 'qty_offered', 'barcode', 'sap_code',
        ];

        $oldDetails = $item->product_details ?? [];
        $changes = 0;

        // Get next revision number for this item
        $lastRevision = self::where('live_sheet_item_id', $item->id)->max('revision_number') ?? 0;
        $revision = $lastRevision + 1;

        foreach ($trackedFields as $field) {
            $oldVal = $oldDetails[$field] ?? null;
            $newVal = $newDetails[$field] ?? null;

            // Also check direct item fields
            if ($field === 'final_qty') {
                $oldVal = $oldVal ?? (string) $item->quantity;
                $newVal = $newVal ?? (string) ($newDetails['quantity'] ?? $item->quantity);
            }

            // Convert to string for comparison
            $oldStr = $oldVal !== null ? (string) $oldVal : null;
            $newStr = $newVal !== null ? (string) $newVal : null;

            if ($oldStr !== $newStr && ($oldStr !== null || $newStr !== null)) {
                self::create([
                    'live_sheet_item_id' => $item->id,
                    'live_sheet_id'      => $item->live_sheet_id,
                    'product_id'         => $item->product_id,
                    'field_name'         => $field,
                    'old_value'          => $oldStr,
                    'new_value'          => $newStr,
                    'changed_by'         => $user->id,
                    'changed_by_role'    => $role,
                    'change_reason'      => $reason,
                    'revision_number'    => $revision,
                    'created_at'         => now(),
                ]);
                $changes++;
            }
        }

        return $changes;
    }

    /**
     * Get formatted change history for a live sheet
     */
    public static function getHistory(int $liveSheetId, ?string $field = null, ?int $itemId = null)
    {
        return self::where('live_sheet_id', $liveSheetId)
            ->when($field, fn($q, $v) => $q->where('field_name', $v))
            ->when($itemId, fn($q, $v) => $q->where('live_sheet_item_id', $v))
            ->with('user', 'product')
            ->orderByDesc('created_at')
            ->get();
    }
}
