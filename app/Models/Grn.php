<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grn extends Model
{
    protected $table = 'grn';

    protected $fillable = [
        'grn_number', 'shipment_id', 'warehouse_id', 'company_code',
        'receipt_date', 'status', 'total_items_expected', 'total_items_received',
        'damaged_items', 'missing_items', 'grn_file', 'remarks',
        'uploaded_by', 'verified_by',
    ];

    protected $casts = ['receipt_date' => 'date'];

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function items() { return $this->hasMany(GrnItem::class); }
    public function inventoryRecords() { return $this->hasMany(Inventory::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getAgeingDays(): int
    {
        return $this->receipt_date ? now()->diffInDays($this->receipt_date) : 0;
    }

    public static function generateNumber(string $shipmentCode): string
    {
        return 'GRN-' . str_replace('SHP-', '', $shipmentCode);
    }
}
