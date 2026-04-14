@extends('layouts.app')
@section('title', 'Permissions: ' . $user->name)
@section('page-title', 'Manage Permissions')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('admin.users') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Users</a>
    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View Profile</a>
    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> Edit User</a>
</div>

{{-- User Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div style="width:50px;height:50px;border-radius:50%;background:{{ $user->user_type==='admin'?'linear-gradient(135deg,#7c3aed,#a855f7)':($user->user_type==='external'?'linear-gradient(135deg,#e8a838,#f59e0b)':'linear-gradient(135deg,#1e3a5f,#2d6a4f)') }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;font-weight:700;">{{ strtoupper(substr($user->name,0,2)) }}</div>
            <div>
                <div style="font-size:1.1rem;font-weight:700;color:#0d1b2a;">{{ $user->name }}</div>
                <div style="font-size:.82rem;color:#64748b;">{{ $user->email }} &middot; {{ ucfirst($user->user_type) }}{{ $user->department ? ' / '.ucfirst($user->department) : '' }}</div>
            </div>
            <span class="badge {{ ['active'=>'badge-success','inactive'=>'badge-gray','suspended'=>'badge-danger'][$user->status]??'badge-gray' }}" style="margin-left:auto;">{{ ucfirst($user->status) }}</span>
        </div>
    </div>
</div>

{{-- Legend --}}
<div style="display:flex;gap:1.5rem;margin-bottom:1.25rem;padding:.65rem 1rem;background:#f8fafc;border-radius:8px;border:1px solid #e8ecf1;font-size:.75rem;">
    <span style="display:flex;align-items:center;gap:.3rem;"><span style="width:14px;height:14px;border-radius:3px;background:#dcfce7;border:1px solid #86efac;display:inline-block;"></span> Inherited from Role (auto-granted)</span>
    <span style="display:flex;align-items:center;gap:.3rem;"><span style="width:14px;height:14px;border-radius:3px;background:#ede9fe;border:1px solid #c4b5fd;display:inline-block;"></span> Direct Permission (manually assigned)</span>
    <span style="display:flex;align-items:center;gap:.3rem;"><span style="width:14px;height:14px;border-radius:3px;background:#f1f5f9;border:1px solid #d1d5db;display:inline-block;"></span> Not assigned</span>
</div>

<form method="POST" action="{{ route('admin.users.permissions.update', $user) }}">
    @csrf
    @method('PUT')

    <div class="grid-2">
        {{-- LEFT: ROLES --}}
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-user-shield" style="margin-right:.5rem;color:#7c3aed;"></i> Assign Roles</h3></div>
            <div class="card-body">
                <p style="font-size:.78rem;color:#64748b;margin-bottom:1rem;">Roles grant a bundle of permissions. Selecting a role automatically grants all its permissions.</p>

                @foreach($roles as $role)
                <label style="display:flex;align-items:flex-start;gap:.6rem;padding:.65rem .75rem;margin-bottom:.4rem;background:{{ in_array($role->id,$userRoleIds)?'#faf5ff':'#f8fafc' }};border:1px solid {{ in_array($role->id,$userRoleIds)?'#c4b5fd':'#e8ecf1' }};border-radius:8px;cursor:pointer;transition:all .15s;" onmouseover="this.style.borderColor='#a78bfa'" onmouseout="this.style.borderColor='{{ in_array($role->id,$userRoleIds)?'#c4b5fd':'#e8ecf1' }}'">
                    <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                        {{ in_array($role->id, $userRoleIds) ? 'checked' : '' }}
                        onchange="updateRolePermissions()"
                        style="width:17px;height:17px;accent-color:#7c3aed;margin-top:.1rem;flex-shrink:0;">
                    <div style="flex:1;">
                        <div style="font-size:.85rem;font-weight:700;color:#0d1b2a;">{{ $role->display_name ?? ucfirst(str_replace('_',' ',$role->name)) }}</div>
                        @if($role->description)<div style="font-size:.7rem;color:#64748b;margin-top:.1rem;">{{ $role->description }}</div>@endif
                        <div style="margin-top:.3rem;display:flex;flex-wrap:wrap;gap:.2rem;">
                            @foreach($role->permissions->take(6) as $p)
                                <span style="font-size:.6rem;padding:.1rem .3rem;background:#ede9fe;border-radius:3px;color:#6d28d9;">{{ str_replace($p->module.'.', '', $p->name) }}</span>
                            @endforeach
                            @if($role->permissions->count() > 6)
                                <span style="font-size:.6rem;color:#94a3b8;">+{{ $role->permissions->count() - 6 }} more</span>
                            @endif
                        </div>
                    </div>
                    <span style="font-size:.7rem;color:#94a3b8;white-space:nowrap;">{{ $role->permissions->count() }} perms</span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- RIGHT: DIRECT PERMISSIONS --}}
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-key" style="margin-right:.5rem;color:#e8a838;"></i> Direct Permissions</h3></div>
            <div class="card-body">
                <p style="font-size:.78rem;color:#64748b;margin-bottom:.5rem;">Grant additional permissions beyond what roles provide. Green = already from role, purple = direct grant.</p>

                <div style="max-height:600px;overflow-y:auto;">
                    @foreach($allPermissions as $module => $perms)
                    <div style="margin-bottom:1rem;">
                        {{-- Module header with select-all --}}
                        <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.4rem;padding-bottom:.3rem;border-bottom:1px solid #e8ecf1;">
                            <input type="checkbox" onclick="toggleModule(this,'{{ Str::slug($module) }}')"
                                id="mod_{{ Str::slug($module) }}"
                                style="accent-color:#e8a838;width:14px;height:14px;">
                            <label for="mod_{{ Str::slug($module) }}" style="font-size:.72rem;font-weight:700;color:#1e3a5f;text-transform:uppercase;letter-spacing:.06em;cursor:pointer;">
                                {{ str_replace('_',' ',$module) }}
                            </label>
                            <span style="font-size:.62rem;color:#94a3b8;margin-left:auto;">{{ $perms->count() }} permissions</span>
                        </div>

                        {{-- Individual permissions --}}
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.25rem;padding-left:.3rem;">
                            @foreach($perms as $perm)
                            @php
                                $isFromRole = in_array($perm->id, $rolePermissionIds);
                                $isDirect = in_array($perm->id, $userDirectPermissionIds);
                                $bgColor = $isFromRole ? '#dcfce7' : ($isDirect ? '#ede9fe' : '#f8fafc');
                                $borderColor = $isFromRole ? '#86efac' : ($isDirect ? '#c4b5fd' : '#e8ecf1');
                                $textColor = $isFromRole ? '#166534' : ($isDirect ? '#6d28d9' : '#64748b');
                            @endphp
                            <label style="display:flex;align-items:center;gap:.3rem;padding:.25rem .4rem;background:{{ $bgColor }};border:1px solid {{ $borderColor }};border-radius:5px;cursor:pointer;font-size:.72rem;transition:all .15s;" class="perm-item perm-mod-{{ Str::slug($module) }}" data-perm-id="{{ $perm->id }}" data-from-role="{{ $isFromRole ? '1' : '0' }}">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                    class="perm-cb perm-cb-{{ Str::slug($module) }}"
                                    {{ $isDirect ? 'checked' : '' }}
                                    {{ $isFromRole ? 'disabled' : '' }}
                                    style="width:13px;height:13px;accent-color:#6d28d9;">

                                {{-- If from role and not direct, add a hidden input so it's not lost --}}
                                @if($isFromRole && !$isDirect)
                                    {{-- Role permissions are auto-granted, no hidden input needed --}}
                                @endif

                                <span style="color:{{ $textColor }};font-weight:{{ ($isFromRole || $isDirect) ? '600' : '400' }};">
                                    {{ str_replace($module.'.', '', $perm->name) }}
                                </span>

                                @if($isFromRole)
                                    <i class="fas fa-shield-alt" style="font-size:.55rem;color:#16a34a;margin-left:auto;" title="Granted by role"></i>
                                @endif
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Save Bar --}}
    <div style="margin-top:1.25rem;display:flex;gap:.5rem;justify-content:flex-end;padding:1rem;background:#fff;border-radius:14px;border:1px solid #e8ecf1;">
        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right:.3rem;"></i> Save Permissions</button>
    </div>
</form>

{{-- Current Effective Permissions Summary --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-shield-alt" style="margin-right:.5rem;color:#2d6a4f;"></i> Current Effective Permissions ({{ count($rolePermissionIds) + count($userDirectPermissionIds) }})</h3></div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:.3rem;">
            @php
                $allEffective = collect($rolePermissionIds)->merge($userDirectPermissionIds)->unique();
                $allPermsMap = \App\Models\Permission::whereIn('id', $allEffective)->get();
            @endphp
            @forelse($allPermsMap->sortBy('module') as $p)
                <span style="display:inline-flex;align-items:center;gap:.2rem;padding:.15rem .4rem;background:{{ in_array($p->id, $rolePermissionIds)?'#dcfce7':'#ede9fe' }};border-radius:4px;font-size:.65rem;font-weight:600;color:{{ in_array($p->id, $rolePermissionIds)?'#166534':'#6d28d9' }};">
                    @if(in_array($p->id, $rolePermissionIds))<i class="fas fa-shield-alt" style="font-size:.5rem;"></i>@else<i class="fas fa-key" style="font-size:.5rem;"></i>@endif
                    {{ $p->name }}
                </span>
            @empty
                <span style="color:#94a3b8;font-size:.82rem;">No permissions assigned.</span>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
// Toggle all permissions in a module
function toggleModule(el, moduleSlug) {
    document.querySelectorAll('.perm-cb-' + moduleSlug).forEach(function(cb) {
        if (!cb.disabled) { // Don't toggle role-inherited (disabled) checkboxes
            cb.checked = el.checked;
        }
    });
}

// When a role checkbox changes, visually indicate which permissions come from roles
function updateRolePermissions() {
    // Build list of role IDs that are checked
    var checkedRoleIds = [];
    document.querySelectorAll('input[name="roles[]"]:checked').forEach(function(cb) {
        checkedRoleIds.push(parseInt(cb.value));
    });

    // Role permission map from server
    var rolePermMap = {!! json_encode($roles->mapWithKeys(fn($r) => [$r->id => $r->permissions->pluck('id')->toArray()])) !!};

    // Collect all permission IDs granted by checked roles
    var grantedByRoles = [];
    checkedRoleIds.forEach(function(rid) {
        if (rolePermMap[rid]) {
            grantedByRoles = grantedByRoles.concat(rolePermMap[rid]);
        }
    });
    grantedByRoles = [...new Set(grantedByRoles)];

    // Update UI for each permission checkbox
    document.querySelectorAll('.perm-item').forEach(function(label) {
        var permId = parseInt(label.getAttribute('data-perm-id'));
        var cb = label.querySelector('input[type="checkbox"]');
        var isGrantedByRole = grantedByRoles.includes(permId);

        if (isGrantedByRole) {
            label.style.background = '#dcfce7';
            label.style.borderColor = '#86efac';
            cb.disabled = true;
            // Don't change checked state — role perms are auto-granted regardless
        } else {
            // Restore to direct permission state
            var wasDirect = label.getAttribute('data-from-role') === '0' && cb.checked;
            label.style.background = cb.checked ? '#ede9fe' : '#f8fafc';
            label.style.borderColor = cb.checked ? '#c4b5fd' : '#e8ecf1';
            cb.disabled = false;
        }
    });
}
</script>
@endpush
@endsection
