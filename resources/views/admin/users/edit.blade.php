@extends('layouts.app')
@section('title', 'Edit User – ' . $user->name)
@section('page-title', 'Edit User')

@section('content')
<div style="max-width:720px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-edit" style="margin-right:.5rem;color:#e8a838;"></i> Edit: {{ $user->name }}</h3>
            <div style="display:flex;gap:.4rem;">
                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</a>
                <a href="{{ route('admin.users') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            {{-- User meta info --}}
            <div style="display:flex;align-items:center;gap:1rem;padding:1rem;background:#f8fafc;border-radius:10px;margin-bottom:1.5rem;">
                <div style="width:48px;height:48px;border-radius:50%;background:{{ $user->user_type==='admin'?'linear-gradient(135deg,#7c3aed,#a855f7)':($user->user_type==='external'?'linear-gradient(135deg,#e8a838,#f59e0b)':'linear-gradient(135deg,#1e3a5f,#2d6a4f)') }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;font-weight:700;">{{ strtoupper(substr($user->name,0,2)) }}</div>
                <div>
                    <div style="font-size:.75rem;color:#64748b;">User ID: #{{ $user->id }} &middot; Created {{ $user->created_at->format('d M Y') }}</div>
                    <div style="font-size:.75rem;color:#64748b;">Last login: {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}</div>
                </div>
                @if($user->id === auth()->id())
                    <span class="badge badge-info" style="margin-left:auto;">This is you</span>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf @method('PUT')

                <div class="grid-2">
                    <div class="form-group">
                        <label>Full Name <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required>
                        @error('name')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Email Address <span style="color:#dc2626;">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                        @error('email')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}">
                    </div>
                    <div class="form-group">
                        <label>User Type <span style="color:#dc2626;">*</span></label>
                        <select name="user_type" id="userType" required onchange="toggleDepartment()">
                            <option value="admin" {{ old('user_type',$user->user_type)==='admin'?'selected':'' }}>Admin</option>
                            <option value="internal" {{ old('user_type',$user->user_type)==='internal'?'selected':'' }}>Internal</option>
                            <option value="external" {{ old('user_type',$user->user_type)==='external'?'selected':'' }}>External</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="departmentGroup" style="{{ $user->user_type==='internal'?'':'display:none;' }}">
                    <label>Department <span style="color:#dc2626;">*</span></label>
                    <select name="department" id="departmentSelect">
                        <option value="">Select department...</option>
                        @foreach(['sourcing','logistics','cataloguing','sales','finance','hod'] as $dept)
                            <option value="{{ $dept }}" {{ old('department',$user->department)===$dept?'selected':'' }}>{{ ucfirst($dept) }}{{ $dept==='hod'?' (Head of Department)':' Team' }}</option>
                        @endforeach
                    </select>
                    @error('department')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Company Code Access <span style="color:#dc2626;">*</span></label>
                    <div style="display:flex;gap:1.5rem;padding:.5rem 0;">
                        @foreach(['2000'=>'India (2000)','2100'=>'USA (2100)','2200'=>'Netherlands (2200)'] as $code=>$label)
                        <label style="display:flex;align-items:center;gap:.4rem;font-size:.85rem;cursor:pointer;">
                            <input type="checkbox" name="company_codes[]" value="{{ $code }}"
                                {{ in_array($code, old('company_codes', $user->company_codes ?? [])) ? 'checked' : '' }}
                                style="width:16px;height:16px;accent-color:#1e3a5f;">
                            <span>{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                    @error('company_codes')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label>Assign Roles</label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.5rem;padding:.5rem 0;">
                        @foreach($roles as $role)
                        <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;cursor:pointer;padding:.35rem .5rem;background:{{ in_array($role->id,$userRoleIds)?'#ede9fe':'#f8fafc' }};border-radius:6px;border:1px solid {{ in_array($role->id,$userRoleIds)?'#c4b5fd':'#e8ecf1' }};">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                                {{ in_array($role->id, old('roles', $userRoleIds)) ? 'checked' : '' }}
                                style="width:15px;height:15px;accent-color:#7c3aed;">
                            <span>{{ $role->display_name ?? $role->name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="form-group">
                    <label>Status <span style="color:#dc2626;">*</span></label>
                    <select name="status" required>
                        <option value="active" {{ old('status',$user->status)==='active'?'selected':'' }}>Active</option>
                        <option value="inactive" {{ old('status',$user->status)==='inactive'?'selected':'' }}>Inactive</option>
                        <option value="suspended" {{ old('status',$user->status)==='suspended'?'selected':'' }}>Suspended</option>
                    </select>
                    @if($user->id === auth()->id())
                        <span style="font-size:.72rem;color:#e8a838;display:block;margin-top:.25rem;"><i class="fas fa-info-circle"></i> Changing your own status to inactive/suspended will lock you out.</span>
                    @endif
                </div>

                <hr style="border:none;border-top:1px solid #e8ecf1;margin:1.5rem 0;">

                <div style="display:flex;gap:.5rem;justify-content:space-between;">
                    <div>
                        @if($user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.delete', $user) }}" style="display:inline;" onsubmit="return confirm('Delete this user?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete User</button>
                        </form>
                        @endif
                    </div>
                    <div style="display:flex;gap:.5rem;">
                        <a href="{{ route('admin.users') }}" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right:.3rem;"></i> Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleDepartment(){const t=document.getElementById('userType').value,g=document.getElementById('departmentGroup'),s=document.getElementById('departmentSelect');if(t==='internal'){g.style.display='block';s.required=true;}else{g.style.display='none';s.required=false;s.value='';}}
document.addEventListener('DOMContentLoaded',toggleDepartment);
</script>
@endpush
@endsection
