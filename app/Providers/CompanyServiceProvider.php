<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class CompanyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $activeCompany = session('active_company');
            $currencyMap = ['2000' => ['INR', '₹'], '2100' => ['USD', '$'], '2200' => ['EUR', '€'], '2400' => ['GBP', '£']];
            $currencyInfo = $currencyMap[$activeCompany] ?? ['USD', '$'];

            $view->with([
                'activeCompany'        => $activeCompany,
                'activeCurrency'       => $currencyInfo[0],
                'activeCurrencySymbol' => $currencyInfo[1],
            ]);
        });
    }

    public function register(): void
    {
    }
}
