<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetActiveCompany
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();
        $companyCodes = $user->company_codes ?? [];
        if (is_string($companyCodes)) {
            $companyCodes = json_decode($companyCodes, true) ?? [];
        }

        // Handle company switch request
        if ($request->has('switch_company')) {
            $requestedCode = $request->input('switch_company');
            if ($user->isAdmin() || in_array($requestedCode, $companyCodes)) {
                session(['active_company' => $requestedCode]);
            }
            return redirect()->back();
        }

        // Set default if not in session
        if (!session()->has('active_company')) {
            $default = !empty($companyCodes) ? $companyCodes[0] : null;
            session(['active_company' => $default]);
        }

        // Validate session value still belongs to user
        $active = session('active_company');
        if ($active && !$user->isAdmin() && !in_array($active, $companyCodes)) {
            $active = !empty($companyCodes) ? $companyCodes[0] : null;
            session(['active_company' => $active]);
        }

        // Share globally
        $currencyMap = ['2000' => ['INR', '₹'], '2100' => ['USD', '$'], '2200' => ['EUR', '€'], '2400' => ['GBP', '£']];
        $currencyInfo = $currencyMap[$active] ?? ['USD', '$'];

        view()->share('activeCompany', $active);
        view()->share('userCompanyCodes', $companyCodes);
        view()->share('activeCurrency', $currencyInfo[0]);
        view()->share('activeCurrencySymbol', $currencyInfo[1]);

        // Also store in config for access in services/controllers
        config(['app.active_company' => $active]);
        config(['app.active_currency' => $currencyInfo[0]]);
        config(['app.active_currency_symbol' => $currencyInfo[1]]);

        return $next($request);
    }
}
