<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckDepartment
{
    public function handle(Request $request, Closure $next, string ...$departments)
    {
        if (!auth()->check()) {
            return redirect()->route('auth.login');
        }

        $user = auth()->user();

        // Admins bypass all checks
        if ($user->isAdmin()) {
            return $next($request);
        }

        // INTERNAL users: department match = full access to that department
        if ($user->isInternal()) {
            if (in_array($user->department, $departments)) {
                return $next($request);
            }
            // Internal user from a different department — still blocked unless they have role-based access
            // Fall through to role/permission check below
        }

        // EXTERNAL users + internal users from other departments: role-based permission check only
        $allPermissions = $this->getUserPermissions($user);

        foreach ($allPermissions as $perm) {
            $permLower = strtolower($perm);
            foreach ($departments as $dept) {
                if (str_starts_with($permLower, strtolower($dept) . '.') ||
                    str_contains($permLower, strtolower($dept))) {
                    return $next($request);
                }
            }
        }

        // Also check if user has a role matching the department
        foreach ($departments as $dept) {
            if ($user->roles()->where('name', 'LIKE', "%{$dept}%")->exists()) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this section.');
    }

    private function getUserPermissions($user): \Illuminate\Support\Collection
    {
        // Direct permissions
        $direct = $user->permissions()->pluck('name');

        // Permissions via roles
        $roleIds = $user->roles()->pluck('roles.id');
        $viaRoles = \App\Models\Permission::whereHas('roles', function ($q) use ($roleIds) {
            $q->whereIn('roles.id', $roleIds);
        })->pluck('name');

        return $direct->merge($viaRoles)->unique();
    }
}
