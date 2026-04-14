@extends('layouts.app')
@section('title', 'Roles & Permissions')
@section('page-title', 'Roles & Permissions')

@section('content')
<div class="grid-2">
    {{-- ROLES LIST --}}
    <div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shield-alt" style="margin-right:.5rem;color:#7c3aed;"></i> Roles ({{ $roles->count() }})</h3>
                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('createRolePanel').style.display=document.getElementById('createRolePanel').style.display==='none'?'block':'none'">
                    <i class="fas fa-plus"></i> New Role
                </button>
            </div>

            {{-- Create Role Form (hidden by default) --}}
            <div id="createRolePanel" style="display:none;border-bottom:1px solid #e8ecf1;">
                <div class="card-body" style="background:#faf5ff;">
                    <form method="POST" action="{{ route('admin.roles.store') }}">
                        @csrf
                        <div style="font-size:.88rem;font-weight:700;color:#6d28d9;margin-bottom:.75rem;">Create New Role</div>

                        <div class="grid-2" style="gap:.75rem;">
                            <div class="form-group" style="margin-bottom:.5rem;">
                                <label>Role Key (lowercase, no spaces)</label>
                                <input type="text" name="name" required placeholder="e.g. warehouse_manager" pattern="[a-z_]+" style="font-family:monospace;">
                                @error('name')<span style="font-size:.7rem;color:#dc2626;">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group" style="margin-bottom:.5rem;">
                                <label>Display Name</label>
                                <input type="text" name="display_name" placeholder="e.g. Warehouse Manager">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label>Description</label>
                            <input type="text" name="description" placeholder="Brief description of this role...">
                        </div>

                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label>Company Codes</label>
                            <div style="display:flex;gap:1rem;">
                                @foreach(['2000'=>'India','2100'=>'USA','2200'=>'NL'] as $code=>$name)
                                <label style="display:flex;align-items:center;gap:.3rem;font-size:.82rem;cursor:pointer;">
                                    <input type="checkbox" name="company_codes[]" value="{{ $code }}" checked style="accent-color:#7c3aed;">{{ $code }} – {{ $name }}
                                </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:.75rem;">
                            <label>Permissions</label>
                            <div style="max-height:250px;overflow-y:auto;border:1px solid #e9d5ff;border-radius:8px;padding:.75rem;">
                                @php $grouped = \App\Models\Permission::all()->groupBy('module'); @endphp
                                @foreach($grouped as $module => $perms)
                                <div style="margin-bottom:.75rem;">
                                    <div style="font-size:.72rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem;display:flex;align-items:center;gap:.4rem;">
                                        <input type="checkbox" onclick="toggleModule(this,'{{ $module }}')" style="accent-color:#7c3aed;">
                                        {{ str_replace('_',' ',$module) }}
                                    </div>
                                    <div style="display:flex;flex-wrap:wrap;gap:.3rem;padding-left:1.2rem;">
                                        @foreach($perms as $perm)
                                        <label style="display:flex;align-items:center;gap:.25rem;font-size:.75rem;cursor:pointer;padding:.15rem .35rem;background:#f5f3ff;border-radius:4px;">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" class="perm-{{ $module }}" style="accent-color:#7c3aed;width:13px;height:13px;">
                                            {{ str_replace($module.'.', '', $perm->name) }}
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div style="display:flex;gap:.4rem;">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Create Role</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('createRolePanel').style.display='none'">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Roles Table --}}
            <div class="card-body" style="padding:0;">
                <table class="data-table">
                    <thead>
                        <tr><th>Role</th><th>Users</th><th>Permissions</th><th>Company Codes</th></tr>
                    </thead>
                    <tbody>
                        @forelse($roles as $role)
                        <tr onclick="showRoleDetail({{ $role->id }})" style="cursor:pointer;">
                            <td>
                                <div style="font-weight:600;color:#0d1b2a;">{{ $role->display_name ?? ucfirst(str_replace('_',' ',$role->name)) }}</div>
                                <div style="font-size:.68rem;color:#94a3b8;font-family:monospace;">{{ $role->name }}</div>
                                @if($role->description)<div style="font-size:.7rem;color:#64748b;margin-top:.1rem;">{{ Str::limit($role->description, 50) }}</div>@endif
                            </td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .5rem;background:#f1f5f9;border-radius:6px;font-size:.8rem;font-weight:600;">
                                    <i class="fas fa-users" style="color:#94a3b8;font-size:.7rem;"></i>
                                    {{ $role->users()->count() }}
                                </span>
                            </td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .5rem;background:#ede9fe;border-radius:6px;font-size:.8rem;font-weight:600;color:#6d28d9;">
                                    <i class="fas fa-key" style="font-size:.7rem;"></i>
                                    {{ $role->permissions->count() }}
                                </span>
                            </td>
                            <td>
                                @foreach($role->company_codes ?? [] as $c)
                                    <span style="display:inline-block;padding:.1rem .3rem;background:#f1f5f9;border-radius:3px;font-size:.68rem;font-weight:600;color:#475569;">{{ $c }}</span>
                                @endforeach
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;padding:2rem;color:#94a3b8;">No roles defined yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ROLE DETAIL PANEL --}}
    <div>
        <div class="card" id="roleDetailCard">
            <div class="card-header"><h3><i class="fas fa-key" style="margin-right:.5rem;color:#e8a838;"></i> Role Permissions</h3></div>
            <div class="card-body" id="roleDetailBody">
                <div style="text-align:center;padding:2rem;color:#94a3b8;">
                    <i class="fas fa-hand-pointer" style="font-size:2rem;margin-bottom:.5rem;display:block;"></i>
                    <div style="font-size:.85rem;">Click a role on the left to see its permissions.</div>
                </div>
            </div>
        </div>

        {{-- Role data stored in JS --}}
        @foreach($roles as $role)
        <div id="roleData{{ $role->id }}" style="display:none;" data-name="{{ $role->display_name ?? $role->name }}" data-desc="{{ $role->description }}">
            @php $rPerms = $role->permissions->groupBy('module'); @endphp
            @foreach($rPerms as $mod => $perms)
            <div style="margin-bottom:.75rem;">
                <div style="font-size:.7rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem;">{{ str_replace('_',' ',$mod) }}</div>
                <div style="display:flex;flex-wrap:wrap;gap:.25rem;">
                    @foreach($perms as $p)
                    <span style="padding:.15rem .4rem;background:#ede9fe;border-radius:4px;font-size:.68rem;font-weight:600;color:#6d28d9;">{{ str_replace($mod.'.', '', $p->name) }}</span>
                    @endforeach
                </div>
            </div>
            @endforeach
            @if($role->permissions->isEmpty())
                <div style="color:#94a3b8;font-size:.82rem;">No permissions assigned to this role.</div>
            @endif
        </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
function toggleModule(el, module) {
    document.querySelectorAll('.perm-' + module).forEach(c => c.checked = el.checked);
}
function showRoleDetail(id) {
    const src = document.getElementById('roleData' + id);
    const body = document.getElementById('roleDetailBody');
    const name = src.getAttribute('data-name');
    const desc = src.getAttribute('data-desc');
    body.innerHTML = '<div style="margin-bottom:1rem;"><div style="font-size:1.05rem;font-weight:700;color:#0d1b2a;">' + name + '</div>' +
        (desc ? '<div style="font-size:.78rem;color:#64748b;margin-top:.15rem;">' + desc + '</div>' : '') + '</div>' +
        src.innerHTML;
    // Highlight selected row
    document.querySelectorAll('.data-table tbody tr').forEach(r => r.style.background = '');
    event.currentTarget.style.background = '#faf5ff';
}
</script>
@endpush
@endsection
