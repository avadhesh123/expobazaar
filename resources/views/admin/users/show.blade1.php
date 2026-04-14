@extends('layouts.app')
@section('title', 'User: ' . $user->name)
@section('page-title', 'User Details')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('admin.users') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Users</a>
    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Edit User</a>
    @if($user->id !== auth()->id())
        @if($user->status !== 'active')
            <form method="POST" action="{{ route('admin.users.toggle-status', [$user, 'active']) }}" style="display:inline;">@csrf<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Activate</button></form>
        @endif
        @if($user->status === 'active')
            <form method="POST" action="{{ route('admin.users.toggle-status', [$user, 'suspended']) }}" style="display:inline;">@csrf<button type="submit" class="btn btn-sm" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;"><i class="fas fa-ban"></i> Suspend</button></form>
        @endif
    @endif
</div>

<div class="grid-2">
    {{-- PROFILE CARD --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-user" style="margin-right:.5rem;color:#1e3a5f;"></i> Profile</h3></div>
        <div class="card-body">
            {{-- Avatar & Name --}}
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
                <div style="width:64px;height:64px;border-radius:50%;background:{{ $user->user_type==='admin'?'linear-gradient(135deg,#7c3aed,#a855f7)':($user->user_type==='external'?'linear-gradient(135deg,#e8a838,#f59e0b)':'linear-gradient(135deg,#1e3a5f,#2d6a4f)') }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.2rem;font-weight:800;">{{ strtoupper(substr($user->name,0,2)) }}</div>
                <div>
                    <div style="font-size:1.15rem;font-weight:700;color:#0d1b2a;">{{ $user->name }}</div>
                    <div style="font-size:.82rem;color:#64748b;">{{ $user->email }}</div>
                    @if($user->phone)<div style="font-size:.78rem;color:#94a3b8;">{{ $user->phone }}</div>@endif
                </div>
                <span class="badge {{ ['active'=>'badge-success','inactive'=>'badge-gray','suspended'=>'badge-danger'][$user->status]??'badge-gray' }}" style="margin-left:auto;font-size:.72rem;">{{ ucfirst($user->status) }}</span>
            </div>

            {{-- Info Grid --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div style="padding:.75rem;background:#f8fafc;border-radius:8px;">
                    <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;margin-bottom:.2rem;">User Type</div>
                    <div style="font-size:.88rem;font-weight:600;color:#0d1b2a;">{{ ucfirst($user->user_type) }}</div>
                </div>
                <div style="padding:.75rem;background:#f8fafc;border-radius:8px;">
                    <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;margin-bottom:.2rem;">Department</div>
                    <div style="font-size:.88rem;font-weight:600;color:#0d1b2a;">{{ $user->department ? ucfirst($user->department) : '—' }}</div>
                </div>
                <div style="padding:.75rem;background:#f8fafc;border-radius:8px;">
                    <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;margin-bottom:.2rem;">User ID</div>
                    <div style="font-size:.88rem;font-weight:600;color:#0d1b2a;">#{{ $user->id }}</div>
                </div>
                <div style="padding:.75rem;background:#f8fafc;border-radius:8px;">
                    <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;margin-bottom:.2rem;">Created</div>
                    <div style="font-size:.88rem;font-weight:600;color:#0d1b2a;">{{ $user->created_at->format('d M Y') }}</div>
                </div>
                <div style="padding:.75rem;background:#f8fafc;border-radius:8px;">
                    <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;margin-bottom:.2rem;">Last Login</div>
                    <div style="font-size:.88rem;font-weight:600;color:#0d1b2a;">{{ $user->last_login_at ? $user->last_login_at->format('d M Y H:i') : 'Never' }}</div>
                </div>
                <div style="padding:.75rem;background:#f8fafc;border-radius:8px;">
                    <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;margin-bottom:.2rem;">Email Verified</div>
                    <div style="font-size:.88rem;font-weight:600;color:{{ $user->email_verified_at?'#166534':'#dc2626' }};">{{ $user->email_verified_at ? $user->email_verified_at->format('d M Y') : 'Not verified' }}</div>
                </div>
            </div>

            {{-- Company Codes --}}
            <div style="margin-top:1rem;padding:.75rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;margin-bottom:.4rem;">Company Code Access</div>
                <div style="display:flex;gap:.5rem;">
                    @foreach($user->company_codes ?? [] as $code)
                        @php $companies = ['2000'=>['India','🇮🇳'],'2100'=>['USA','🇺🇸'],'2200'=>['Netherlands','🇳🇱']]; @endphp
                        <div style="padding:.4rem .75rem;background:#fff;border:1px solid #e2e8f0;border-radius:8px;display:flex;align-items:center;gap:.4rem;">
                            <span>{{ $companies[$code][1] ?? '' }}</span>
                            <div>
                                <div style="font-size:.8rem;font-weight:700;color:#0d1b2a;">{{ $code }}</div>
                                <div style="font-size:.65rem;color:#64748b;">{{ $companies[$code][0] ?? '' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ROLES & PERMISSIONS --}}
    <div>
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header"><h3><i class="fas fa-shield-alt" style="margin-right:.5rem;color:#7c3aed;"></i> Roles & Permissions</h3></div>
            <div class="card-body">
                @forelse($user->roles as $role)
                    <div style="padding:.75rem;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;margin-bottom:.5rem;">
                        <div style="font-size:.88rem;font-weight:700;color:#6d28d9;">{{ $role->display_name ?? $role->name }}</div>
                        @if($role->description)<div style="font-size:.72rem;color:#7c3aed;margin-top:.15rem;">{{ $role->description }}</div>@endif
                        @if($role->permissions->count() > 0)
                            <div style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.5rem;">
                                @foreach($role->permissions->take(12) as $perm)
                                    <span style="display:inline-block;padding:.1rem .35rem;background:#ede9fe;border-radius:3px;font-size:.62rem;font-weight:600;color:#7c3aed;">{{ $perm->display_name ?? $perm->name }}</span>
                                @endforeach
                                @if($role->permissions->count() > 12)
                                    <span style="font-size:.62rem;color:#94a3b8;">+{{ $role->permissions->count() - 12 }} more</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div style="text-align:center;padding:1.5rem;color:#94a3b8;">
                        <i class="fas fa-shield-alt" style="font-size:1.5rem;margin-bottom:.3rem;display:block;"></i>
                        <div style="font-size:.82rem;">No roles assigned</div>
                        <a href="{{ route('admin.users.edit', $user) }}" style="font-size:.78rem;color:#1e3a5f;">Assign roles →</a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Vendor info if external user --}}
        @if($user->vendor)
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-store" style="margin-right:.5rem;color:#e8a838;"></i> Vendor Details</h3></div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
                    <div><span style="font-size:.7rem;color:#64748b;">Company:</span><br><strong>{{ $user->vendor->company_name }}</strong></div>
                    <div><span style="font-size:.7rem;color:#64748b;">Vendor Code:</span><br><strong>{{ $user->vendor->vendor_code }}</strong></div>
                    <div><span style="font-size:.7rem;color:#64748b;">KYC Status:</span><br><span class="badge {{ $user->vendor->kyc_status==='approved'?'badge-success':'badge-warning' }}">{{ ucfirst($user->vendor->kyc_status) }}</span></div>
                    <div><span style="font-size:.7rem;color:#64748b;">Contract:</span><br><span class="badge {{ $user->vendor->contract_status==='signed'?'badge-success':'badge-info' }}">{{ ucfirst($user->vendor->contract_status) }}</span></div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ACTIVITY LOG --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-history" style="margin-right:.5rem;color:#2d6a4f;"></i> Recent Activity</h3></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Action</th><th>Module</th><th>Description</th><th>Date</th></tr></thead>
            <tbody>
                @forelse($activities as $log)
                <tr>
                    <td><span class="badge badge-info">{{ $log->action }}</span></td>
                    <td style="font-size:.82rem;">{{ $log->module }}</td>
                    <td style="font-size:.82rem;color:#334155;">{{ $log->description ?? '—' }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $log->created_at->format('d M Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="4" style="text-align:center;padding:2rem;color:#94a3b8;">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
