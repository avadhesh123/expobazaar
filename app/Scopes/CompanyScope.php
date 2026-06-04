<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Disabled — using explicit filters in controllers instead
        return;
    }
    public function apply03(Builder $builder, Model $model): void
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        $column = $model->getTable() . '.company_code';

        // If a specific company is selected in session, filter to that
        $activeCompany = session('active_company');
        if ($activeCompany) {
            $builder->where($column, $activeCompany);
            return;
        }

        // Admins with no specific selection see everything
        if ($user->isAdmin()) {
            return;
        }

        // Non-admin users without selection: filter to their assigned companies
        $companyCodes = $user->company_codes ?? [];
        if (is_string($companyCodes)) {
            $companyCodes = json_decode($companyCodes, true) ?? [];
        }
        if (!empty($companyCodes)) {
            $builder->whereIn($column, $companyCodes);
        }
    }
}
