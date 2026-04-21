<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'consignment_number',
        'vendor_id',
        'offer_sheet_id',
        'live_sheet_id',
        'company_code',
        'destination_country',
        'status',
        'total_cbm',
        'total_items',
        'total_value',
        'currency',
        'remarks',
        'created_by',
        'commercial_invoice_file',
        'commercial_invoice_number',
        'commercial_invoice_upload_date',
        'commercial_invoice_upload_by',
        'packing_list_file',
        'packing_list_number',
        'packing_list_upload_date',
        'packing_list_upload_by',
    ];

    protected $casts = [
        'total_cbm' => 'decimal:4',
        'total_value' => 'decimal:2',
        'commercial_invoice_upload_date' => 'date',
        'packing_list_upload_date' => 'date',
    ];
    public function commercialInvoiceUploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'commercial_invoice_upload_by');
    }
    public function packingListUploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'packing_list_upload_by');
    }
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
    public function offerSheet()
    {
        return $this->belongsTo(OfferSheet::class);
    }
    public function inspectionReports()
    {
        return $this->hasMany(InspectionReport::class);
    }
    public function shipments()
    {
        return $this->belongsToMany(Shipment::class, 'shipment_consignments');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Live sheet — try belongsTo first (consignments.live_sheet_id),
     * fall back to hasOne (live_sheets.consignment_id)
     */
    public function liveSheet()
    {
        return $this->belongsTo(LiveSheet::class, 'live_sheet_id');
    }

    /**
     * Reverse lookup — live sheets that reference this consignment
     */
    public function liveSheets()
    {
        return $this->hasMany(LiveSheet::class);
    }

    /**
     * Get live sheet via any available link (accessor fallback)
     */
    public function getLiveSheetModelAttribute()
    {
        // Try the direct foreign key first
        if ($this->live_sheet_id) {
            return LiveSheet::find($this->live_sheet_id);
        }
        // Fallback: find by reverse FK
        return LiveSheet::where('consignment_id', $this->id)->first();
    }

    /**
     * Get the primary shipment (accessor)
     */
    public function getShipmentAttribute()
    {
        return $this->shipments()->first();
    }

    /**
     * Get GRN through shipment (accessor)
     */
    public function getGrnAttribute()
    {
        $shipmentIds = $this->shipments()->pluck('shipments.id');
        if ($shipmentIds->isEmpty()) return null;
        return \App\Models\Grn::whereIn('shipment_id', $shipmentIds)->first();
    }

    public function scopeByCompanyCode($query, $code)
    {
        return $query->where('company_code', $code);
    }
    public function scopeReadyForShipment($query)
    {
        return $query->where('status', 'live_sheet_locked');
    }

    public static function generateNumber(string $companyCode, string $country): string
    {
        $countryCode = strtoupper(substr($country, 0, 2));
        $prefix = 'CON-' . $companyCode . '-' . $countryCode . '-';
        $last = self::where('consignment_number', 'like', $prefix . '%')->orderBy('id', 'desc')->first();
        $next = $last ? intval(substr($last->consignment_number, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
