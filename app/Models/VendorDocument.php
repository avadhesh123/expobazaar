<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorDocument extends Model
{
    protected $fillable = [
        'vendor_id', 'document_type', 'document_name', 'file_path',
        'file_type', 'file_size', 'status', 'uploaded_by',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
