<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asn extends Model
{
    protected $table = 'asn';

    protected $fillable = [
        'asn_number',
        'shipment_id',
        'company_code',
        'status',
        'items',
        'total_cbm',
        'total_items',
        'generated_by',
        'generated_at',
    ];

    protected $casts = [
        'items' => 'array',
        'generated_at' => 'datetime',
        'total_cbm' => 'decimal:4',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
    public function platformPricing()
    {
        return $this->hasMany(PlatformPricing::class);
    }
    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public static function generateNumber(string $shipmentCode): string
    {
        $base = 'ASN-' . str_replace('SHP-', '', $shipmentCode);
        $exists = static::where('asn_number', $base)->exists();
        if (!$exists) return $base;

        $count = static::where('asn_number', 'LIKE', $base . '%')->count();
        return $base . '-' . ($count + 1);
    }
}
