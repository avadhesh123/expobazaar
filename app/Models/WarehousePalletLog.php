<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehousePalletLog extends Model
{
    protected $fillable = [
        'warehouse_id', 'company_code', 'no_of_pallets', 'entry_date', 'remarks', 'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
