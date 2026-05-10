<?php
// app/Http/Middleware/CheckPermission.php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;

class CheckPermission
{
    /**
     * Usage in routes:
     *   ->middleware('permission:logistics.grn.view')
     *   ->middleware('permission:logistics.grn.create,logistics.grn.edit')  // any of these
     */
    public function handle($request, Closure $next, string ...$permissions)
    {
        if (!auth()->check()) return redirect()->route('auth.login');

        $user = auth()->user();

        // Admin bypass
        if ($user->isAdmin()) return $next($request);

        // Check if user has any of the required permissions
        foreach ($permissions as $perm) {
            if (PermissionService::can($user, $perm)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}

// app/Http/Middleware/CheckModule.php
// (replaces your current CheckDepartment.php)

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;

class CheckModule
{
    /**
     * Usage: ->middleware('module:logistics')
     * Allows access if user can access any feature in the module
     */
    public function handle($request, Closure $next, string ...$modules)
    {
        if (!auth()->check()) return redirect()->route('auth.login');

        $user = auth()->user();

        if ($user->isAdmin()) return $next($request);

        foreach ($modules as $module) {
            if (PermissionService::canAccessModule($user, $module)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this section.');
    }
}
