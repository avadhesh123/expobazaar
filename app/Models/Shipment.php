<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shipment_code', 'company_code', 'destination_country', 'shipment_type',
        'status', 'container_number', 'container_size', 'total_cbm', 'capacity_cbm',
        'total_weight', 'total_items', 'total_value', 'shipping_line', 'vessel_name',
        'voyage_number', 'bill_of_lading', 'sailing_date', 'eta_date', 'arrival_date',
        'port_of_loading', 'port_of_discharge', 'destination_warehouse_id',
        'locked_by', 'locked_at', 'created_by', 'remarks',
    ];

    protected $casts = [
        'sailing_date' => 'date',
        'eta_date' => 'date',
        'arrival_date' => 'date',
        'locked_at' => 'datetime',
        'total_cbm' => 'decimal:4',
        'capacity_cbm' => 'decimal:2',
    ];

    public function consignments() { return $this->belongsToMany(Consignment::class, 'shipment_consignments')->withPivot('cbm', 'items'); }
    public function asn() { return $this->hasOne(Asn::class); }
    public function grn() { return $this->hasOne(Grn::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class, 'destination_warehouse_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeByCompanyCode($query, $code) { return $query->where('company_code', $code); }
    public function scopeInTransit($query) { return $query->where('status', 'in_transit'); }

    public function isOverCapacity(): bool
    {
        return $this->total_cbm > $this->capacity_cbm;
    }

    public function getUtilizationPercent(): float
    {
        return $this->capacity_cbm > 0 ? round(($this->total_cbm / $this->capacity_cbm) * 100, 2) : 0;
    }

    public static function generateCode(string $companyCode, string $type): string
    {
        $prefix = 'SHP-' . $companyCode . '-' . $type . '-';
        $last = self::where('shipment_code', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        $next = $last ? intval(substr($last->shipment_code, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
