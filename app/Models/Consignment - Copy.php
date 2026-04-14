<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'consignment_number', 'vendor_id', 'offer_sheet_id', 'company_code',
        'destination_country', 'status', 'total_cbm', 'total_items',
        'total_value', 'currency', 'remarks', 'created_by',
    ];

    protected $casts = [
        'total_cbm' => 'decimal:4',
        'total_value' => 'decimal:2',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function offerSheet() { return $this->belongsTo(OfferSheet::class); }
    public function liveSheet() { return $this->hasOne(LiveSheet::class); }
    public function inspectionReports() { return $this->hasMany(InspectionReport::class); }
    public function shipments() { return $this->belongsToMany(Shipment::class, 'shipment_consignments'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeByCompanyCode($query, $code) { return $query->where('company_code', $code); }
    public function scopeReadyForShipment($query) { return $query->where('status', 'live_sheet_locked'); }

    public static function generateNumber(string $companyCode, string $country): string
    {
        $countryCode = strtoupper(substr($country, 0, 2));
        $prefix = 'CON-' . $companyCode . '-' . $countryCode . '-';
        $last = self::where('consignment_number', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        $next = $last ? intval(substr($last->consignment_number, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
