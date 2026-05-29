<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, \App\Traits\FiltersByCompany, SoftDeletes;

    protected $fillable = [
        'shipment_code',
        'company_code',
        'destination_country',
        'shipment_type',
        'status',
        'container_number',
        'container_size',
        'total_cbm',
        'capacity_cbm',
        'total_weight',
        'total_items',
        'total_value',
        'shipping_line',
        'vessel_name',
        'voyage_number',
        'bill_of_lading',
        'sailing_date',
        'eta_date',
        'arrival_date',
        'pickup_date',
        'delivery_date',
        'carrier_name',
        'tracking_number',
        'shipping_bill_number',
        'shipping_bill_date',
        'forwarder_name',
        'booking_no',
        'no_of_pallets',
        'port_of_loading',
        'port_of_discharge',
        'destination_warehouse_id',
        'locked_by',
        'locked_at',
        'created_by',
        'remarks',
        'entry_summary_file',
        'entry_summary_number',
        'entry_summary_date',
        'entry_summary_upload_by',
        'entry_summary_upload_date',
        'status_changed_at',
        'status_changed_by',
        'origin_charges',
        'ocean_freight',
        'destination_charges',
        'drayage_cost',
        'duty_amount',
    ];

    protected $casts = [
        'sailing_date' => 'date',
        'pickup_date'       => 'date',
        'eta_date' => 'date',
        'arrival_date' => 'date',
        'delivery_date' => 'date',
        'shipping_bill_date' => 'date',
        'locked_at' => 'datetime',
        'total_cbm' => 'decimal:4',
        'capacity_cbm' => 'decimal:2',
        'entry_summary_date' => 'date',
        'entry_summary_upload_date' => 'date',
        'status_changed_at' => 'datetime',
    ];

    public function consignments()
    {
        return $this->belongsToMany(Consignment::class, 'shipment_consignments')->withPivot('cbm', 'items');
    }
    public function asn()
    {
        return $this->hasOne(Asn::class);
    }
    public function grn()
    {
        return $this->hasOne(Grn::class);
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function logs()
    {
        return $this->hasMany(ShipmentLog::class)->latest('changed_at');
    }
    public function scopeByCompanyCode($query, $code)
    {
        return $query->where('company_code', $code);
    }
    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    public function isOverCapacity(): bool
    {
        return $this->total_cbm > $this->capacity_cbm;
    }

    public function getUtilizationPercent(): float
    {
        return $this->capacity_cbm > 0 ? round(($this->total_cbm / $this->capacity_cbm) * 100, 2) : 0;
    }
    public function statusChangedBy()
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }
    public static function generateCode(string $companyCode, string $type): string
    {
        $prefix = 'SHP-' . $companyCode . '-' . $type . '-';
        $last = self::where('shipment_code', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        $next = $last ? intval(substr($last->shipment_code, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
