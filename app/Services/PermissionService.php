<?php
// app/Services/PermissionService.php

namespace App\Services;

use App\Models\{User, Permission};
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * Check if user can perform action
     * This is THE method called everywhere
     */
    public static function can(User $user, string $permission): bool
    {
        // Admin bypass
        if ($user->isAdmin()) return true;

        // Internal user — always has full access to their own department

        if ($user->isInternal()) {
            $module = explode('.', $permission)[0] ?? '';
            $dept = strtolower($user->department ?? '');

            // Direct match
            if ($dept === $module) {
                return true;
            }

            // Department aliases (department name → modules they can access)
            $deptModules = [
                'admin'       => ['admin'],
                'sourcing'    => ['sourcing'],
                'logistics'   => ['logistics'],
                'cataloguing' => ['cataloguing', 'catalog'],
                'sales'       => ['sales'],
                'finance'     => ['finance'],
                'hod'         => ['hod', 'admin', 'sourcing', 'logistics', 'cataloguing', 'sales', 'finance'],
            ];

            $allowedModules = $deptModules[$dept] ?? [$dept];
            if (in_array($module, $allowedModules)) {
                return true;
            }
        }


        // Additional access via roles (both internal cross-dept and external)
        $perms = self::getUserPermissions($user);
        return $perms->contains($permission);
    }

    public static function canAccessModule(User $user, string $module): bool
    {
        if ($user->isAdmin()) return true;

        // Internal user — own department is always accessible
        if ($user->isInternal() && strtolower($user->department) === $module) return true;

        // Cross-dept or external — check role-based permissions
        $perms = self::getUserPermissions($user);
        return $perms->contains(fn($p) => str_starts_with($p, $module . '.'));
    }

    /**
     * Get all permissions for a user (cached 5 min)
     */
    public static function getUserPermissions(User $user): \Illuminate\Support\Collection
    {
        $perms = Cache::remember("user_perms_{$user->id}", 300, function () use ($user) {
            $direct = $user->permissions()->pluck('name');
            $roleIds = $user->roles()->pluck('roles.id');
            $viaRoles = Permission::whereHas(
                'roles',
                fn($q) =>
                $q->whereIn('roles.id', $roleIds)
            )->pluck('name');
            return $direct->merge($viaRoles)->unique()->values()->toArray(); // cache as array, not Collection
        });

        return collect($perms); // convert back to Collection when reading
    }

    /**
     * Check if user has any roles assigned
     */
    public static function hasRoles(User $user): bool
    {
        return Cache::remember("user_has_roles_{$user->id}", 300, function () use ($user) {
            return $user->roles()->exists();
        });
    }

    /**
     * Clear permission cache for a user (call when roles/perms change)
     */
    public static function clearCache(int $userId): void
    {
        Cache::forget("user_perms_{$userId}");
        Cache::forget("user_has_roles_{$userId}");
    }

    /**
     * Clear cache for all users with a specific role
     */
    public static function clearRoleCache(int $roleId): void
    {
        $userIds = \DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->pluck('model_id');

        foreach ($userIds as $uid) {
            self::clearCache($uid);
        }
    }

    /**
     * Get permissions grouped by module for admin UI
     */
    public static function getGroupedPermissions(): array
    {
        $permissions = Permission::orderBy('module')->orderBy('entity')->orderBy('action')->get();
        $grouped = [];

        foreach ($permissions as $p) {
            $grouped[$p->module][$p->entity][] = $p;
        }

        return $grouped;
    }
}
