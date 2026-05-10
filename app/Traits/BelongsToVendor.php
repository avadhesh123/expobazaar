<?php
// app/Traits/BelongsToVendor.php

namespace App\Traits;

trait BelongsToVendor
{
    /**
     * Auto-scope queries for vendor users
     */
    public static function bootBelongsToVendor(): void
    {
        static::addGlobalScope('vendor_filter', function ($query) {
            $user = auth()->user();
            if ($user && $user->isVendor() && $user->vendor_id) {
                $query->where('vendor_id', $user->vendor_id);
            }
        });
    }
}

// Usage in models:
class Consignment extends Model {
    use BelongsToVendor;
}

class VendorRateCard extends Model {
    use BelongsToVendor;
}

