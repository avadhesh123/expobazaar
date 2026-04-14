@extends('layouts.app')
@section('title', 'Create User')
@section('page-title', 'Create New User')

@section('content')
<div style="max-width:720px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-plus" style="margin-right:.5rem;color:#e8a838;"></i> New User</h3>
            <a href="{{ route('admin.users') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                <div class="grid-2">
                    {{-- Name --}}
                    <div class="form-group">
                        <label>Full Name <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="John Doe">
                        @error('name')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                    </div>

                    {{-- Email --}}
                    <div class="form-group">
                        <label>Email Address <span style="color:#dc2626;">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="john@expobazaar.com">
                        @error('email')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="grid-2">
                    {{-- Phone --}}
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+91 98765 43210">
                    </div>

                    {{-- User Type --}}
                    <div class="form-group">
                        <label>User Type <span style="color:#dc2626;">*</span></label>
                        <select name="user_type" id="userType" required onchange="toggleDepartment()">
                            <option value="">Select type...</option>
                            <option value="admin" {{ old('user_type')==='admin'?'selected':'' }}>Admin</option>
                            <option value="internal" {{ old('user_type')==='internal'?'selected':'' }}>Internal (Team Member)</option>
                            <option value="external" {{ old('user_type')==='external'?'selected':'' }}>External (Vendor)</option>
                        </select>
                        @error('user_type')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                    </div>
                </div>

                {{-- Department (only for internal users) --}}
                <div class="form-group" id="departmentGroup" style="display:none;">
                    <label>Department <span style="color:#dc2626;">*</span></label>
                    <select name="department" id="departmentSelect">
                        <option value="">Select department...</option>
                        <option value="sourcing" {{ old('department')==='sourcing'?'selected':'' }}>Sourcing Team</option>
                        <option value="logistics" {{ old('department')==='logistics'?'selected':'' }}>Logistics Team</option>
                        <option value="cataloguing" {{ old('department')==='cataloguing'?'selected':'' }}>Cataloguing Team</option>
                        <option value="sales" {{ old('department')==='sales'?'selected':'' }}>Sales Team</option>
                        <option value="finance" {{ old('department')==='finance'?'selected':'' }}>Finance Team</option>
                        <option value="hod" {{ old('department')==='hod'?'selected':'' }}>Head of Department (HOD)</option>
                    </select>
                    @error('department')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>

                {{-- Company Codes --}}
                <div class="form-group">
                    <label>Company Code Access <span style="color:#dc2626;">*</span></label>
                    <div style="display:flex;gap:1.5rem;padding:.5rem 0;">
                        @foreach(['2000' => 'India (2000)', '2100' => 'USA (2100)', '2200' => 'Netherlands (2200)'] as $code => $label)
                        <label style="display:flex;align-items:center;gap:.4rem;font-size:.85rem;cursor:pointer;">
                            <input type="checkbox" name="company_codes[]" value="{{ $code }}"
                                {{ in_array($code, old('company_codes', [])) ? 'checked' : '' }}
                                style="width:16px;height:16px;accent-color:#1e3a5f;">
                            <span>{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                    @error('company_codes')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>

                {{-- Roles --}}
                <div class="form-group">
                    <label>Assign Roles</label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.5rem;padding:.5rem 0;">
                        @foreach($roles as $role)
                        <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;cursor:pointer;padding:.35rem .5rem;background:#f8fafc;border-radius:6px;border:1px solid #e8ecf1;">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                                {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }}
                                style="width:15px;height:15px;accent-color:#7c3aed;">
                            <span>{{ $role->display_name ?? $role->name }}</span>
                        </label>
                        @endforeach
                    </div>
                    @error('roles')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>

                {{-- Status --}}
                <div class="form-group">
                    <label>Initial Status</label>
                    <select name="status">
                        <option value="active" {{ old('status')==='active'?'selected':'' }}>Active (can login immediately)</option>
                        <option value="inactive" {{ old('status')==='inactive'?'selected':'' }}>Inactive (cannot login until activated)</option>
                    </select>
                </div>

                <hr style="border:none;border-top:1px solid #e8ecf1;margin:1.5rem 0;">

                <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                    <a href="{{ route('admin.users') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus" style="margin-right:.3rem;"></i> Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleDepartment() {
    const type = document.getElementById('userType').value;
    const deptGroup = document.getElementById('departmentGroup');
    const deptSelect = document.getElementById('departmentSelect');
    if (type === 'internal') {
        deptGroup.style.display = 'block';
        deptSelect.required = true;
    } else {
        deptGroup.style.display = 'none';
        deptSelect.required = false;
        deptSelect.value = '';
    }
}
// Init on page load
document.addEventListener('DOMContentLoaded', toggleDepartment);
</script>
@endpush
@endsection
