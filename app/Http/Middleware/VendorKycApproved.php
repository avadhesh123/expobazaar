<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VendorKycApproved
{
    /**
     * Block access to vendor panel routes (except dashboard & kyc) until KYC is approved.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !$user->isVendor()) {
            return $next($request);
        }

        $vendor = $user->vendor;

        // Allow access to dashboard & kyc routes always
        $allowedRoutes = ['vendor.dashboard', 'vendor.kyc', 'vendor.kyc.submit', 'vendor.contract.download'];
        $currentRoute = $request->route()?->getName();

        if (in_array($currentRoute, $allowedRoutes)) {
            return $next($request);
        }

        // Block all other vendor routes if KYC not approved
        if (!$vendor || $vendor->kyc_status !== 'approved') {
            return redirect()->route('vendor.kyc')
                ->with('error', 'Please complete your KYC registration and wait for Finance team approval before accessing other modules.');
        }

        return $next($request);
    }
}
