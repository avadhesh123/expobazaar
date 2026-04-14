@extends('layouts.app')
@section('title', 'Edit Role: ' . ($role->display_name ?? $role->name))
@section('page-title', 'Edit Role')

@section('content')
<div style="max-width:720px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-shield-alt" style="margin-right:.5rem;color:#7c3aed;"></i> Edit: {{ $role->display_name ?? ucfirst(str_replace('_',' ',$role->name)) }}</h3>
            <a href="{{ route('admin.roles') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            {{-- Role info --}}
            <div style="padding:.75rem;background:#faf5ff;border-radius:8px;border:1px solid #e9d5ff;margin-bottom:1.25rem;">
                <div style="font-size:.7rem;color:#7c3aed;font-weight:600;">Role Key: <span style="font-family:monospace;">{{ $role->name }}</span></div>
                <div style="font-size:.7rem;color:#7c3aed;">Users with this role: <strong>{{ $role->users()->count() }}</strong></div>
            </div>

            <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                @csrf
                @method('PUT')

                <div class="grid-2">
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" value="{{ old('display_name', $role->display_name) }}" placeholder="e.g. Sourcing Manager">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" value="{{ old('description', $role->description) }}" placeholder="Brief description...">
                    </div>
                </div>

                <div class="form-group">
                    <label>Company Codes</label>
                    <div style="display:flex;gap:1rem;">
                        @foreach(['2000'=>'India','2100'=>'USA','2200'=>'NL'] as $code=>$name)
                        <label style="display:flex;align-items:center;gap:.3rem;font-size:.82rem;cursor:pointer;">
                            <input type="checkbox" name="company_codes[]" value="{{ $code }}"
                                {{ in_array($code, old('company_codes', $role->company_codes ?? [])) ? 'checked' : '' }}
                                style="accent-color:#7c3aed;">
                            {{ $code }} – {{ $name }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="form-group">
                    <label>Permissions</label>
                    <div style="display:flex;gap:.5rem;margin-bottom:.5rem;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.querySelectorAll('.role-perm-cb').forEach(c=>c.checked=true)"><i class="fas fa-check-double"></i> Select All</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.querySelectorAll('.role-perm-cb').forEach(c=>c.checked=false)"><i class="fas fa-times"></i> Deselect All</button>
                    </div>
                    <div style="max-height:500px;overflow-y:auto;border:1px solid #e9d5ff;border-radius:8px;padding:.75rem;">
                        @foreach($allPermissions as $module => $perms)
                        <div style="margin-bottom:.75rem;">
                            <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.3rem;padding-bottom:.25rem;border-bottom:1px solid #f1f5f9;">
                                <input type="checkbox" onclick="toggleModulePerms(this,'{{ Str::slug($module) }}')" style="accent-color:#7c3aed;width:14px;height:14px;"
                                    {{ $perms->every(fn($p) => in_array($p->id, $rolePermissionIds)) ? 'checked' : '' }}>
                                <span style="font-size:.72rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.06em;">{{ str_replace('_',' ',$module) }}</span>
                                <span style="font-size:.62rem;color:#94a3b8;margin-left:auto;">{{ $perms->count() }}</span>
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:.3rem;padding-left:1.2rem;">
                                @foreach($perms as $perm)
                                <label style="display:flex;align-items:center;gap:.25rem;font-size:.75rem;cursor:pointer;padding:.2rem .4rem;background:{{ in_array($perm->id, $rolePermissionIds)?'#ede9fe':'#f8fafc' }};border:1px solid {{ in_array($perm->id, $rolePermissionIds)?'#c4b5fd':'#e8ecf1' }};border-radius:4px;">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                        class="role-perm-cb rperm-{{ Str::slug($module) }}"
                                        {{ in_array($perm->id, $rolePermissionIds) ? 'checked' : '' }}
                                        style="accent-color:#7c3aed;width:13px;height:13px;">
                                    {{ str_replace($module.'.', '', $perm->name) }}
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e8ecf1;margin:1.5rem 0;">

                <div style="display:flex;justify-content:space-between;">
                    @if($role->users()->count() === 0)
                    <form method="POST" action="{{ route('admin.roles.delete', $role) }}" style="display:inline;" onsubmit="return confirm('Delete this role permanently?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete Role</button>
                    </form>
                    @else
                    <span style="font-size:.72rem;color:#94a3b8;display:flex;align-items:center;"><i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Cannot delete — {{ $role->users()->count() }} user(s) assigned</span>
                    @endif

                    <div style="display:flex;gap:.5rem;">
                        <a href="{{ route('admin.roles') }}" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right:.3rem;"></i> Update Role</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleModulePerms(el, moduleSlug) {
    document.querySelectorAll('.rperm-' + moduleSlug).forEach(function(cb) { cb.checked = el.checked; });
}
</script>
@endpush
@endsection
