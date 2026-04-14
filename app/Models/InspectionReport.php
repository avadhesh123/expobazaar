<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionReport extends Model
{
    protected $fillable = [
        'consignment_id',
        'product_id',
        'inspection_type',
        'report_file',
        'report_name',
        'result',
        'remarks',
        'findings',
        'uploaded_by',
    ];

    protected $casts = [
        'findings' => 'array',
        'commercial_invoice_uploaded_at' => 'datetime',
        'packing_list_uploaded_at'       => 'datetime',
    ];

    public function consignment()
    {
        return $this->belongsTo(Consignment::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
    public function commercialInvoiceUploadedBy()
    {
        return $this->belongsTo(User::class, 'commercial_invoice_uploaded_by');
    }

    public function packingListUploadedBy()
    {
        return $this->belongsTo(User::class, 'packing_list_uploaded_by');
    }
}
