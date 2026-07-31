@extends('layouts.app')
@section('title', 'Vendor Access')
@section('page-title', 'Vendor Access — Login as Vendor')

@section('content')
<div style="margin-bottom:1rem;padding:.6rem 1rem;background:#fefce8;border:1px solid #fde68a;border-radius:8px;font-size:.78rem;color:#854d0e;">
    <i class="fas fa-user-secret" style="margin-right:.3rem;"></i>
    Click <strong>"Login as Vendor"</strong> to view the vendor portal exactly as the vendor sees it. A red banner will appear at the top — click "Back to Admin" to return.
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-store" style="margin-right:.5rem;color:#1e3a5f;"></i> All Vendors ({{ $vendors->total() }})</h3>
        <div style="display:flex;gap:.4rem;">
            <form method="GET" style="display:flex;gap:.4rem;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search vendor..." style="padding:.3rem .6rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;width:180px;">
                <select name="status" style="padding:.3rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status')==='active'?'selected':'' }}>Active</option>
                    <option value="pending" {{ request('status')==='pending'?'selected':'' }}>Pending</option>
                    <option value="rejected" {{ request('status')==='rejected'?'selected':'' }}>Rejected</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
                @if(request('search') || request('status'))
                <a href="{{ route('admin.vendor-access') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
                @endif
            </form>
        </div>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="width:30px;">#</th>
                    <th>Vendor</th>
                    <th>Email</th>
                    <th>Company Code</th>
                    <th>Products</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th style="text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendors as $idx => $vendor)
                @php
                    $user = $vendor->user;
                    $statusColors = ['active'=>'badge-success','pending'=>'badge-warning','rejected'=>'badge-danger'];
                @endphp
                <tr>
                    <td style="color:#94a3b8;">{{ $vendors->firstItem() + $idx }}</td>
                    <td>
                        <div style="font-weight:600;">{{ $vendor->company_name }}</div>
                        <div style="font-size:.65rem;color:#94a3b8;">{{ $vendor->contact_person ?? '' }}</div>
                    </td>
                    <td style="font-size:.75rem;">{{ $user->email ?? '—' }}</td>
                    <td>
                        @foreach($user->company_codes ?? [] as $code)
                        @php $cColors = ['2100'=>'#1e40af','2200'=>'#16a34a','2400'=>'#dc2626']; $cLabels = ['2100'=>'US','2200'=>'EU','2400'=>'UK']; @endphp
                        <span style="display:inline-block;padding:1px 5px;background:{{ $cColors[$code] ?? '#64748b' }};color:#fff;border-radius:3px;font-size:.6rem;font-weight:700;">{{ $cLabels[$code] ?? $code }}</span>
                        @endforeach
                    </td>
                    <td style="text-align:center;font-weight:600;">{{ $vendor->products()->count() }}</td>
                    <td><span class="badge {{ $statusColors[$vendor->status] ?? 'badge-gray' }}">{{ ucfirst($vendor->status) }}</span></td>
                    <td style="font-size:.72rem;color:#64748b;">{{ $user->last_login_at ? $user->last_login_at->format('d M Y H:i') : 'Never' }}</td>
                    <td style="text-align:center;">
                        @if($user)
                        <a href="{{ route('admin.vendors.impersonate', $vendor) }}" target="_blank"
                            class="btn btn-primary btn-sm" title="Login as {{ $vendor->company_name }}"
                            onclick="return confirm('Open vendor portal as {{ $vendor->company_name }}?')">
                            <i class="fas fa-sign-in-alt" style="margin-right:.2rem;"></i> Login as Vendor
                        </a>
                        @else
                        <span style="font-size:.72rem;color:#94a3b8;">No user account</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8;">No vendors found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($vendors->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $vendors->links() }}</div>
    @endif
</div>
@endsection
