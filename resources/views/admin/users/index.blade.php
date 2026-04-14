@extends('layouts.app')
@section('title', 'User Management')
@section('page-title', 'User Management')

@section('content')
{{-- STAT CARDS --}}
<div class="grid-kpi" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));">
    <div class="kpi-card" style="cursor:pointer;" onclick="filterByStatus('')"><div class="kpi-label">Total Users</div><div class="kpi-value">{{ $stats['total'] }}</div></div>
    <div class="kpi-card" style="cursor:pointer;border-left:3px solid #16a34a;" onclick="filterByStatus('active')"><div class="kpi-label">Active</div><div class="kpi-value" style="color:#16a34a;">{{ $stats['active'] }}</div></div>
    <div class="kpi-card" style="cursor:pointer;border-left:3px solid #94a3b8;" onclick="filterByStatus('inactive')"><div class="kpi-label">Inactive</div><div class="kpi-value" style="color:#94a3b8;">{{ $stats['inactive'] }}</div></div>
    <div class="kpi-card" style="cursor:pointer;border-left:3px solid #dc2626;" onclick="filterByStatus('suspended')"><div class="kpi-label">Suspended</div><div class="kpi-value" style="color:#dc2626;">{{ $stats['suspended'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #7c3aed;"><div class="kpi-label">Admins</div><div class="kpi-value" style="color:#7c3aed;">{{ $stats['admins'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #1e40af;"><div class="kpi-label">Internal</div><div class="kpi-value" style="color:#1e40af;">{{ $stats['internal'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Vendors</div><div class="kpi-value" style="color:#e8a838;">{{ $stats['external'] }}</div></div>
</div>

{{-- FILTER BAR --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <form method="GET" action="{{ route('admin.users') }}" id="filterForm" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="flex:1;min-width:200px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, or phone..." style="width:100%;padding:.45rem .75rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
            </div>
            <div style="min-width:130px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">User Type</label>
                <select name="type" style="width:100%;padding:.45rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All Types</option>
                    <option value="admin" {{ request('type')==='admin'?'selected':'' }}>Admin</option>
                    <option value="internal" {{ request('type')==='internal'?'selected':'' }}>Internal</option>
                    <option value="external" {{ request('type')==='external'?'selected':'' }}>External</option>
                </select>
            </div>
            <div style="min-width:140px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Department</label>
                <select name="department" style="width:100%;padding:.45rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All Depts</option>
                    @foreach(['sourcing','logistics','cataloguing','sales','finance','hod'] as $dept)
                        <option value="{{ $dept }}" {{ request('department')===$dept?'selected':'' }}>{{ ucfirst($dept) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:120px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" id="statusFilter" style="width:100%;padding:.45rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    <option value="active" {{ request('status')==='active'?'selected':'' }}>Active</option>
                    <option value="inactive" {{ request('status')==='inactive'?'selected':'' }}>Inactive</option>
                    <option value="suspended" {{ request('status')==='suspended'?'selected':'' }}>Suspended</option>
                </select>
            </div>
            <div style="min-width:110px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label>
                <select name="company_code" style="width:100%;padding:.45rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option>
                    <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option>
                    <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option>
                </select>
            </div>
            <div style="display:flex;gap:.4rem;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
                <a href="{{ route('admin.users') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            </div>
            <div style="margin-left:auto;display:flex;gap:.4rem;">
                <a href="{{ route('admin.users.export', request()->query()) }}" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> CSV</a>
                <a href="{{ route('admin.users', ['show_deleted'=>'yes']) }}" class="btn btn-outline btn-sm" style="{{ request('show_deleted')==='yes'?'background:#fee2e2;color:#991b1b;':'' }}"><i class="fas fa-trash-restore"></i> Deleted ({{ $stats['deleted'] }})</a>
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> Add User</a>
            </div>
        </form>
    </div>
</div>

{{-- BULK ACTION BAR (only checkboxes + action selector inside this form) --}}
<form method="POST" action="{{ route('admin.users.bulk-action') }}" id="bulkForm">
    @csrf
    <div id="bulkBar" style="display:none;margin-bottom:1rem;">
        <div class="card" style="border-color:#e8a838;">
            <div class="card-body" style="padding:.75rem 1.4rem;display:flex;align-items:center;gap:1rem;">
                <span style="font-size:.82rem;font-weight:600;color:#0d1b2a;"><span id="selectedCount">0</span> user(s) selected</span>
                <select name="action" style="padding:.35rem .6rem;border:1px solid #d1d5db;border-radius:6px;font-size:.8rem;font-family:inherit;">
                    <option value="">Choose action...</option>
                    <option value="activate">Activate</option>
                    <option value="deactivate">Deactivate</option>
                    <option value="suspend">Suspend</option>
                    <option value="delete">Delete</option>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Apply this action to selected users?')"><i class="fas fa-bolt"></i> Apply</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="clearSelection()">Cancel</button>
            </div>
        </div>
    </div>

    {{-- USERS TABLE --}}
    <div class="card">
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        @if(request('show_deleted')!=='yes')<th style="width:40px;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>@endif
                        <th>User</th>
                        <th>Type / Department</th>
                        <th>Company Codes</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                    <tr style="{{ $u->trashed()?'opacity:.6;background:#fef2f2;':'' }}">
                        @if(request('show_deleted')!=='yes')<td><input type="checkbox" name="user_ids[]" value="{{ $u->id }}" class="user-checkbox" onchange="updateBulkBar()"></td>@endif
                        <td>
                            <div style="display:flex;align-items:center;gap:.6rem;">
                                <div style="width:36px;height:36px;border-radius:50%;background:{{ $u->user_type==='admin'?'linear-gradient(135deg,#7c3aed,#a855f7)':($u->user_type==='external'?'linear-gradient(135deg,#e8a838,#f59e0b)':'linear-gradient(135deg,#1e3a5f,#2d6a4f)') }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:.72rem;font-weight:700;flex-shrink:0;">{{ strtoupper(substr($u->name,0,2)) }}</div>
                                <div>
                                    <a href="{{ $u->trashed()?'#':route('admin.users.show',$u) }}" style="font-weight:600;color:#0d1b2a;text-decoration:none;font-size:.83rem;">{{ $u->name }}</a>
                                    <div style="font-size:.72rem;color:#94a3b8;">{{ $u->email }}</div>
                                    @if($u->phone)<div style="font-size:.68rem;color:#b0b8c4;">{{ $u->phone }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge {{ $u->user_type==='admin'?'badge-info':($u->user_type==='external'?'badge-warning':'badge-gray') }}">{{ ucfirst($u->user_type) }}</span>
                            @if($u->department)<div style="font-size:.72rem;color:#64748b;margin-top:.15rem;">{{ ucfirst($u->department) }}</div>@endif
                        </td>
                        <td>@foreach($u->company_codes??[] as $c)<span style="display:inline-block;padding:.12rem .35rem;background:#f1f5f9;border-radius:4px;font-size:.68rem;font-weight:600;color:#475569;margin:.05rem;">{{ $c }}</span>@endforeach</td>
                        <td>@forelse($u->roles as $r)<span style="display:inline-block;padding:.12rem .4rem;background:#ede9fe;border-radius:4px;font-size:.66rem;font-weight:600;color:#6d28d9;margin:.05rem;">{{ $r->display_name??$r->name }}</span>@empty<span style="font-size:.7rem;color:#94a3b8;">—</span>@endforelse</td>
                        <td><span class="badge {{ ['active'=>'badge-success','inactive'=>'badge-gray','suspended'=>'badge-danger','pending'=>'badge-warning'][$u->status]??'badge-gray' }}">{{ ucfirst($u->status) }}</span></td>
                        <td>@if($u->last_login_at)<div style="font-size:.78rem;color:#334155;">{{ $u->last_login_at->format('d M Y') }}</div><div style="font-size:.66rem;color:#94a3b8;">{{ $u->last_login_at->diffForHumans() }}</div>@else<span style="font-size:.72rem;color:#94a3b8;">Never</span>@endif</td>
                        <td>
                            @if($u->trashed())
                                {{-- Deleted user actions — use JS to avoid nested forms --}}
                                <div style="display:flex;gap:.3rem;">
                                    <button type="button" class="btn btn-success btn-sm" title="Restore" onclick="submitAction('{{ route('admin.users.restore',$u->id) }}','POST')"><i class="fas fa-undo"></i></button>
                                    <button type="button" class="btn btn-danger btn-sm" title="Permanent Delete" onclick="if(confirm('PERMANENTLY delete? Cannot be undone.'))submitAction('{{ route('admin.users.force-delete',$u->id) }}','DELETE')"><i class="fas fa-skull-crossbones"></i></button>
                                </div>
                            @else
                                {{-- Active user actions — use JS to avoid nested forms --}}
                                <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                                    <a href="{{ route('admin.users.show',$u) }}" class="btn btn-outline btn-sm" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('admin.users.edit',$u) }}" class="btn btn-outline btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                                    @if($u->id !== auth()->id())
                                        @if($u->status==='active')
                                            <button type="button" class="btn btn-sm" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;" title="Suspend" onclick="submitAction('{{ route('admin.users.toggle-status',[$u,'suspended']) }}','POST')"><i class="fas fa-ban"></i></button>
                                        @else
                                            <button type="button" class="btn btn-success btn-sm" title="Activate" onclick="submitAction('{{ route('admin.users.toggle-status',[$u,'active']) }}','POST')"><i class="fas fa-check"></i></button>
                                        @endif
                                        <button type="button" class="btn btn-danger btn-sm" title="Delete" onclick="if(confirm('Delete \'{{ $u->name }}\'?'))submitAction('{{ route('admin.users.delete',$u) }}','DELETE')"><i class="fas fa-trash"></i></button>
                                    @else
                                        <span style="font-size:.65rem;color:#94a3b8;padding:.3rem;" title="Your account"><i class="fas fa-lock"></i></span>
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ request('show_deleted')==='yes'?7:8 }}" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-users" style="font-size:2rem;margin-bottom:.5rem;display:block;"></i>No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
        <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:.78rem;color:#64748b;">Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span>
            {{ $users->links('pagination::tailwind') }}
        </div>
        @endif
    </div>
</form>

{{-- HIDDEN FORM for per-row actions (sits OUTSIDE the bulk form) --}}
<form id="rowActionForm" method="POST" style="display:none;">
    @csrf
    <input type="hidden" name="_method" id="rowActionMethod" value="POST">
</form>

@push('scripts')
<script>
function filterByStatus(s){document.getElementById('statusFilter').value=s;document.getElementById('filterForm').submit();}
function toggleAll(el){document.querySelectorAll('.user-checkbox').forEach(c=>c.checked=el.checked);updateBulkBar();}
function updateBulkBar(){const n=document.querySelectorAll('.user-checkbox:checked').length;document.getElementById('bulkBar').style.display=n>0?'block':'none';document.getElementById('selectedCount').textContent=n;}
function clearSelection(){document.querySelectorAll('.user-checkbox').forEach(c=>c.checked=false);document.getElementById('selectAll').checked=false;updateBulkBar();}

/**
 * Submit a per-row action using the hidden form outside the bulk form.
 * This avoids the nested <form> problem in HTML.
 */
function submitAction(url, method) {
    var form = document.getElementById('rowActionForm');
    form.action = url;
    // For DELETE, set the _method hidden field; actual HTTP is always POST
    if (method === 'DELETE') {
        document.getElementById('rowActionMethod').value = 'DELETE';
    } else {
        document.getElementById('rowActionMethod').value = 'POST';
    }
    form.submit();
}
</script>
@endpush
@endsection