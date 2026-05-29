<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shipment_id', 'field', 'old_value', 'new_value', 'changed_by', 'changed_at',
    ];

    protected $casts = ['changed_at' => 'datetime'];

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function user() { return $this->belongsTo(User::class, 'changed_by'); }

    public static function record(int $shipmentId, string $field, $oldValue, $newValue): self
    {
        return static::create([
            'shipment_id' => $shipmentId,
            'field'       => $field,
            'old_value'   => $oldValue,
            'new_value'   => $newValue,
            'changed_by'  => auth()->id(),
            'changed_at'  => now(),
        ]);
    }
}