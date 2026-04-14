<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesChannel extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'type', 'platform_url', 'is_active', 'company_codes', 'commission_rules'];

    protected $casts = [
        'is_active' => 'boolean',
        'company_codes' => 'array',
        'commission_rules' => 'array',
    ];

    public function orders() { return $this->hasMany(Order::class); }
    public function catalogues() { return $this->hasMany(ProductCatalogue::class); }
    public function scopeActive($query) { return $query->where('is_active', true); }
}
