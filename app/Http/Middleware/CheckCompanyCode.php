<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckCompanyCode
{
    public function handle(Request $request, Closure $next)
    {
        $companyCode = $request->get('company_code') ?? $request->route('company_code');
        if ($companyCode && !auth()->user()->hasCompanyAccess($companyCode)) {
            abort(403, 'No access to this company code.');
        }
        return $next($request);
    }
}
