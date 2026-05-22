<?php

namespace App\Helpers;

class ActiveCompany
{
    /**
     * Get the currently active company code from session
     */
    public static function code(): ?string
    {
        return session('active_company');
    }

    /**
     * Get company name from code
     */
    public static function name(): string
    {
        return match(self::code()) {            
            '2100' => 'ExpoBazaar USA',
            '2200' => 'ExpoBazaar EU',
            '2400' => 'ExpoBazaar UK',
             default => 'All Companies',
        };
    }

    /**
     * Get short label
     */
    public static function label(): string
    {
        return match(self::code()) {
            '2100' => '🇺🇸 ExpoBazaar USA',
            '2200' => '🇪🇺 ExpoBazaar EU',
            '2400' => '🇬🇧 ExpoBazaar UK',
            default => '🌐 All',
        };
    }

    /**
     * Get currency symbol
     */
    public static function currency(): string
    {
        return match(self::code()) {
            '2200' => '€',
            '2400' => '£',
            default => '$',
        };
    }

    /**
     * Get currency code
     */
    public static function currencyCode(): string
    {
        return match(self::code()) {
            '2000' => 'INR',
            '2200' => 'EUR',
            '2400' => 'GBP',
            default => 'USD',
        };
    }
}
