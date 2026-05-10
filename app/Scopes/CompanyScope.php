<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();
        if (!$user) return;

        // Admins see everything
        if ($user->isAdmin()) return;

        $companyCodes = $user->company_codes ?? [];
        if (empty($companyCodes)) return;

        // Get the correct column name (some models use 'company_code', some 'company_codes')
        $column = $model->getTable() . '.company_code';

        $builder->whereIn($column, $companyCodes);
    }
}
