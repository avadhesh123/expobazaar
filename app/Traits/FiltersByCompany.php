<?php

namespace App\Traits;

use App\Scopes\CompanyScope;

trait FiltersByCompany
{
    public static function bootFiltersByCompany(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    /**
     * Query without company filter (for admin/system operations)
     */
    public static function withoutCompanyFilter(): \Illuminate\Database\Eloquent\Builder
    {
        return static::withoutGlobalScope(CompanyScope::class);
    }
}
