<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{User, Vendor, Role, Permission, SalesChannel, Category, Warehouse, LiveSheet};
use App\Services\{DashboardService, VendorService, SourcingService};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
        protected VendorService $vendorService
    ) {
    }

    public function dashboard(Request $request)
    {
        $companyCode = $request->get('company_code');
        $data = $this->dashboardService->getAdminDashboard($companyCode);
        return view('admin.dashboard', compact('data', 'companyCode'));
    }

    // =====================================================================
    //  USER MANAGEMENT — Full CRUD + Bulk + Export + Status + Soft Delete
    // =====================================================================

    public function users(Request $request)
    {
        $query = User::with('roles');

        if ($request->show_deleted === 'yes') {
            $query->onlyTrashed();
        }

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->when($request->type, fn ($q, $v) => $q->where('user_type', $v));
        $query->when($request->department, fn ($q, $v) => $q->where('department', $v));
        $query->when($request->status, fn ($q, $v) => $q->where('status', $v));
        $query->when($request->company_code, fn ($q, $v) => $q->whereJsonContains('company_codes', $v));

        $sortField = in_array($request->sort, ['name', 'email', 'user_type', 'department', 'status', 'created_at', 'last_login_at'])
            ? $request->sort : 'created_at';
        $sortDir = $request->direction === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDir);

        $users = $query->paginate(25)->appends($request->query());

        $stats = [
            'total'     => User::count(),
            'active'    => User::where('status', 'active')->count(),
            'inactive'  => User::where('status', 'inactive')->count(),
            'suspended' => User::where('status', 'suspended')->count(),
            'admins'    => User::where('user_type', 'admin')->count(),
            'internal'  => User::where('user_type', 'internal')->count(),
            'external'  => User::where('user_type', 'external')->count(),
            'deleted'   => User::onlyTrashed()->count(),
        ];

        $roles = Role::all();

        return view('admin.users.index', compact('users', 'stats', 'roles'));
    }

    public function showUser(User $user)
    {
        $user->load('roles.permissions', 'vendor', 'permissions');
        $activities = \App\Models\ActivityLog::where('user_id', $user->id)->latest()->take(20)->get();
        return view('admin.users.show', compact('user', 'activities'));
    }

    public function createUser()
    {
        $roles = Role::all();
        return view('admin.users.create', compact('roles'));
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'phone'           => 'nullable|string|max:20',
            'user_type'       => 'required|in:internal,admin,external',
            'department'      => 'nullable|required_if:user_type,internal|in:sourcing,logistics,cataloguing,sales,finance,hod',
            'company_codes'   => 'required|array|min:1',
            'company_codes.*' => 'in:2000,2100,2200',
            'roles'           => 'nullable|array',
            'roles.*'         => 'exists:roles,id',
            'status'          => 'nullable|in:active,inactive',
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'phone'             => $validated['phone'] ?? null,
            'user_type'         => $validated['user_type'],
            'department'        => $validated['user_type'] === 'internal' ? $validated['department'] : null,
            'company_codes'     => $validated['company_codes'],
            'status'            => $validated['status'] ?? 'active',
            'email_verified_at' => now(),
        ]);

        if (!empty($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        \App\Models\ActivityLog::log('created', 'users', $user, null, $user->toArray(), "User '{$user->name}' created");

        return redirect()->route('admin.users')->with('success', "User '{$user->name}' created successfully.");
    }

    public function editUser(User $user)
    {
        $user->load('roles');
        $roles = Role::all();
        $userRoleIds = $user->roles->pluck('id')->toArray();
        return view('admin.users.edit', compact('user', 'roles', 'userRoleIds'));
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,' . $user->id,
            'phone'           => 'nullable|string|max:20',
            'user_type'       => 'required|in:internal,admin,external',
            'department'      => 'nullable|required_if:user_type,internal|in:sourcing,logistics,cataloguing,sales,finance,hod',
            'company_codes'   => 'required|array|min:1',
            'company_codes.*' => 'in:2000,2100,2200',
            'roles'           => 'nullable|array',
            'roles.*'         => 'exists:roles,id',
            'status'          => 'required|in:active,inactive,suspended',
        ]);

        $oldValues = $user->toArray();

        $user->update([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'phone'         => $validated['phone'] ?? $user->phone,
            'user_type'     => $validated['user_type'],
            'department'    => $validated['user_type'] === 'internal' ? $validated['department'] : null,
            'company_codes' => $validated['company_codes'],
            'status'        => $validated['status'],
        ]);

        $user->roles()->sync($validated['roles'] ?? []);

        \App\Models\ActivityLog::log('updated', 'users', $user, $oldValues, $user->fresh()->toArray(), "User '{$user->name}' updated");

        return redirect()->route('admin.users')->with('success', "User '{$user->name}' updated successfully.");
    }

    // =====================================================================
    //  USER PERMISSIONS — Manage roles + direct permissions per user
    // =====================================================================

    /**
     * Show the permissions management page for a specific user
     */
    public function manageUserPermissions(User $user)
    {
        $user->load('roles', 'permissions');

        $roles = Role::with('permissions')->get();
        $allPermissions = Permission::all()->groupBy('module');

        // IDs of roles assigned to this user
        $userRoleIds = $user->roles->pluck('id')->toArray();

        // IDs of permissions assigned DIRECTLY to this user (not via roles)
        $userDirectPermissionIds = $user->permissions->pluck('id')->toArray();

        // IDs of permissions the user gets via their roles (read-only, shown grayed out)
        $rolePermissionIds = $user->roles->flatMap(fn ($role) => $role->permissions->pluck('id'))->unique()->toArray();

        return view('admin.users.permissions', compact(
            'user',
            'roles',
            'allPermissions',
            'userRoleIds',
            'userDirectPermissionIds',
            'rolePermissionIds'
        ));
    }

    /**
     * Update roles and direct permissions for a user
     */
    public function updateUserPermissions(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles'       => 'nullable|array',
            'roles.*'     => 'exists:roles,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $oldRoles = $user->roles->pluck('name')->toArray();
        $oldPerms = $user->permissions->pluck('name')->toArray();

        // Sync roles
        $user->roles()->sync($validated['roles'] ?? []);

        // Sync direct permissions (these are IN ADDITION to role permissions)
        $user->permissions()->sync($validated['permissions'] ?? []);

        $newRoles = $user->fresh()->roles->pluck('name')->toArray();
        $newPerms = $user->fresh()->permissions->pluck('name')->toArray();

        \App\Models\ActivityLog::log(
            'permissions_updated',
            'users',
            $user,
            ['roles' => $oldRoles, 'direct_permissions' => $oldPerms],
            ['roles' => $newRoles, 'direct_permissions' => $newPerms],
            "Permissions updated for '{$user->name}'"
        );

        return redirect()->route('admin.users.permissions', $user)
            ->with('success', "Permissions updated for '{$user->name}'.");
    }

    // =====================================================================
    //  USER STATUS + DELETE + RESTORE + BULK + EXPORT
    // =====================================================================

    public function toggleUserStatus(User $user, string $status)
    {
        if (!in_array($status, ['active', 'inactive', 'suspended'])) {
            return back()->with('error', 'Invalid status.');
        }
        if ($user->id === auth()->id() && $status !== 'active') {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $oldStatus = $user->status;
        $user->update(['status' => $status]);

        \App\Models\ActivityLog::log(
            'status_changed',
            'users',
            $user,
            ['status' => $oldStatus],
            ['status' => $status],
            "User '{$user->name}' changed from {$oldStatus} to {$status}"
        );

        return back()->with('success', "User '{$user->name}' is now {$status}.");
    }

    public function deleteUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $name = $user->name;
        $user->update(['status' => 'inactive']);
        $user->delete();

        \App\Models\ActivityLog::log('deleted', 'users', $user, null, null, "User '{$name}' deleted");

        return redirect()->route('admin.users')->with('success', "User '{$name}' deleted.");
    }

    public function restoreUser(int $userId)
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $user->restore();
        $user->update(['status' => 'inactive']);

        \App\Models\ActivityLog::log('restored', 'users', $user, null, null, "User '{$user->name}' restored");

        return back()->with('success', "User '{$user->name}' restored (set to inactive).");
    }

    public function forceDeleteUser(int $userId)
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $name = $user->name;
        $user->roles()->detach();
        $user->permissions()->detach();
        $user->forceDelete();

        return back()->with('success', "User '{$name}' permanently deleted.");
    }

    public function bulkUserAction(Request $request)
    {
        $request->validate([
            'action'      => 'required|in:activate,deactivate,suspend,delete',
            'user_ids'    => 'required|array|min:1',
            'user_ids.*'  => 'exists:users,id',
        ]);

        $userIds = collect($request->user_ids)->reject(fn ($id) => (int)$id === auth()->id());
        if ($userIds->isEmpty()) {
            return back()->with('error', 'No valid users selected.');
        }

        $count = $userIds->count();
        switch ($request->action) {
            case 'activate':   User::whereIn('id', $userIds)->update(['status' => 'active']);
                $msg = "{$count} user(s) activated.";
                break;
            case 'deactivate': User::whereIn('id', $userIds)->update(['status' => 'inactive']);
                $msg = "{$count} user(s) deactivated.";
                break;
            case 'suspend':    User::whereIn('id', $userIds)->update(['status' => 'suspended']);
                $msg = "{$count} user(s) suspended.";
                break;
            case 'delete':     User::whereIn('id', $userIds)->update(['status' => 'inactive']);
                User::whereIn('id', $userIds)->delete();
                $msg = "{$count} user(s) deleted.";
                break;
            default: return back()->with('error', 'Invalid action.');
        }

        return back()->with('success', $msg);
    }

    public function exportUsers(Request $request)
    {
        $users = User::with('roles')
            ->when($request->type, fn ($q, $v) => $q->where('user_type', $v))
            ->when($request->department, fn ($q, $v) => $q->where('department', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name')->get();

        $csv = "ID,Name,Email,Phone,Type,Department,Company Codes,Status,Roles,Created,Last Login\n";
        foreach ($users as $u) {
            $csv .= implode(',', [
                $u->id,
                '"' . str_replace('"', '""', $u->name) . '"',
                $u->email,
                $u->phone ?? '',
                $u->user_type,
                $u->department ?? '',
                '"' . implode('; ', $u->company_codes ?? []) . '"',
                $u->status,
                '"' . $u->roles->pluck('display_name')->implode('; ') . '"',
                $u->created_at?->format('Y-m-d H:i'),
                $u->last_login_at?->format('Y-m-d H:i') ?? 'Never',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users-' . date('Y-m-d') . '.csv"',
        ]);
    }

    // =====================================================================
    //  VENDOR APPROVAL
    // =====================================================================

    public function pendingVendors()
    {
        $vendors = Vendor::pendingApproval()->with('creator')->latest()->paginate(25);
        return view('admin.vendors.pending', compact('vendors'));
    }
    public function showVendor(Vendor $vendor)
    {
        $vendor->load('user', 'documents', 'creator');
        return view('admin.vendors.show', compact('vendor'));
    }
    public function approveVendor(Vendor $vendor)
    {
        $this->vendorService->approveVendorCreation($vendor, auth()->user());
        return back()->with('success', 'Vendor approved successfully.');
    }

    public function waiveMembership(Vendor $vendor)
    {
        $this->vendorService->waiveMembershipFee($vendor, auth()->user());
        return back()->with('success', 'Membership fee waived.');
    }

    // =====================================================================
    //  ROLES & PERMISSIONS MANAGEMENT
    // =====================================================================

    public function roles()
    {
        $roles = Role::with('permissions')->get();
        $allPermissions = Permission::all()->groupBy('module');
        return view('admin.roles.index', compact('roles', 'allPermissions'));
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|unique:roles',
            'display_name'  => 'nullable|string',
            'description'   => 'nullable|string',
            'company_codes' => 'nullable|array',
            'permissions'   => 'nullable|array',
        ]);

        $role = Role::create($validated);
        if (!empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        \App\Models\ActivityLog::log('created', 'roles', $role, null, $role->toArray(), "Role '{$role->name}' created");

        return redirect()->route('admin.roles')->with('success', 'Role created.');
    }

    /**
     * Edit role — show form
     */
    public function editRole(Role $role)
    {
        $role->load('permissions');
        $allPermissions = Permission::all()->groupBy('module');
        $rolePermissionIds = $role->permissions->pluck('id')->toArray();
        return view('admin.roles.edit', compact('role', 'allPermissions', 'rolePermissionIds'));
    }

    /**
     * Update role permissions
     */
    public function updateRole(Request $request, Role $role)
    {
        $validated = $request->validate([
            'display_name'  => 'nullable|string',
            'description'   => 'nullable|string',
            'company_codes' => 'nullable|array',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $oldPerms = $role->permissions->pluck('name')->toArray();

        $role->update([
            'display_name'  => $validated['display_name'] ?? $role->display_name,
            'description'   => $validated['description'] ?? $role->description,
            'company_codes' => $validated['company_codes'] ?? $role->company_codes,
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        $newPerms = $role->fresh()->permissions->pluck('name')->toArray();

        \App\Models\ActivityLog::log(
            'updated',
            'roles',
            $role,
            ['permissions' => $oldPerms],
            ['permissions' => $newPerms],
            "Role '{$role->name}' permissions updated"
        );

        $roleName = $role->display_name ?? $role->name;
        return redirect()->route('admin.roles')->with('success', "Role '{$roleName}' updated.");
    }

    /**
     * Delete role
     */
    public function deleteRole(Role $role)
    {
        $userCount = $role->users()->count();
        if ($userCount > 0) {
            return back()->with('error', "Cannot delete role '{$role->name}' — it has {$userCount} user(s) assigned.");
        }

        $name = $role->display_name ?? $role->name;
        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('admin.roles')->with('success', "Role '{$name}' deleted.");
    }

    // =====================================================================
    //  MASTERS
    // =====================================================================

    public function categories()
    {
        $categories = Category::with('parent')->orderBy('sort_order')->get();
        return view('admin.masters.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        Category::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'parent_id'   => $request->parent_id,
            'description' => $request->description,
        ]);
        return back()->with('success', 'Category created.');
    }

    public function salesChannels()
    {
        $channels = SalesChannel::all();
        return view('admin.masters.sales-channels', compact('channels'));
    }

    public function storeSalesChannel(Request $request)
    {
        $request->validate(['name' => 'required|string', 'type' => 'required|in:marketplace,offline,direct']);
        SalesChannel::create([
            'name'          => $request->name,
            'slug'          => Str::slug($request->name),
            'type'          => $request->type,
            'platform_url'  => $request->platform_url,
            'company_codes' => $request->company_codes,
            'is_active'     => true,
        ]);
        return back()->with('success', 'Sales channel created.');
    }

    public function warehouses()
    {
        $warehouses = Warehouse::with('subWarehouses', 'subLocations')->get();
        return view('admin.masters.warehouses', compact('warehouses'));
    }

    public function storeWarehouse(Request $request)
    {
        $request->validate([
            'name'         => 'required|string',
            'code'         => 'required|unique:warehouses',
            'company_code' => 'required|in:2000,2100,2200',
            'country'      => 'required',
        ]);
        Warehouse::create($request->all());
        return back()->with('success', 'Warehouse created.');
    }

    // =====================================================================
    //  LIVE SHEET UNLOCK
    // =====================================================================

    public function unlockLiveSheet(LiveSheet $liveSheet)
    {
        app(SourcingService::class)->unlockLiveSheet($liveSheet, auth()->user());
        return back()->with('success', 'Live sheet unlocked.');
    }

    // =====================================================================
    //  ACTIVITY LOG
    // =====================================================================

    public function activityLog(Request $request)
{
    $logs = \App\Models\ActivityLog::with('user')
        ->when($request->module, fn($q, $v) => $q->where('module', $v))
        ->when($request->action, fn($q, $v) => $q->where('action', $v))
        ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
        ->when($request->date_from, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
        ->when($request->date_to, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
        ->when($request->search, function ($q, $v) {
            $q->where(function ($sub) use ($v) {
                $sub->where('description', 'LIKE', "%{$v}%")
                    ->orWhere('subject_type', 'LIKE', "%{$v}%")
                    ->orWhere('action', 'LIKE', "%{$v}%");
            });
        })
        ->latest()
        ->paginate(50)
        ->withQueryString();

    // For filter dropdowns
    $modules = \App\Models\ActivityLog::distinct()->pluck('module')->filter()->sort()->values();
    $actions = \App\Models\ActivityLog::distinct()->pluck('action')->filter()->sort()->values();
    $users   = \App\Models\User::orderBy('name')->get(['id', 'name', 'email']);

    // KPI summary
    $stats = [
        'total'        => \App\Models\ActivityLog::count(),
        'today'        => \App\Models\ActivityLog::whereDate('created_at', today())->count(),
        'this_week'    => \App\Models\ActivityLog::where('created_at', '>=', now()->startOfWeek())->count(),
        'unique_users' => \App\Models\ActivityLog::distinct('user_id')->count('user_id'),
    ];

    return view('admin.activity-log', compact('logs', 'modules', 'actions', 'users', 'stats'));
}

}
