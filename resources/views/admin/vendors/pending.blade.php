@extends('layouts.app')
@section('title', 'Vendor Approvals')
@section('page-title', 'Vendor Approvals')

@section('content')
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-check" style="margin-right:.5rem;color:#e8a838;"></i> Pending Vendor Requests</h3>
        <span class="badge badge-warning" style="font-size:.78rem;">{{ $vendors->total() }} pending</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>Company Code</th>
                    <th>Contact</th>
                    <th>Requested By</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th style="width:200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendors as $vendor)
                <tr>
                    <td>
                        <div style="font-weight:600;color:#0d1b2a;">{{ $vendor->company_name }}</div>
                        <div style="font-size:.72rem;color:#94a3b8;">{{ $vendor->vendor_code }}</div>
                    </td>
                    <td>
                        @php $cc = ['2000'=>['India','🇮🇳','#dcfce7'],'2100'=>['USA','🇺🇸','#dbeafe'],'2200'=>['NL','🇳🇱','#fef3c7']]; @endphp
                        <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .5rem;background:{{ $cc[$vendor->company_code][2]??'#f1f5f9' }};border-radius:6px;font-size:.78rem;font-weight:600;">
                            {{ $cc[$vendor->company_code][1]??'' }} {{ $vendor->company_code }} – {{ $cc[$vendor->company_code][0]??'' }}
                        </span>
                    </td>
                    <td>
                        <div style="font-size:.83rem;">{{ $vendor->contact_person }}</div>
                        <div style="font-size:.72rem;color:#64748b;">{{ $vendor->email }}</div>
                        @if($vendor->phone)<div style="font-size:.68rem;color:#94a3b8;">{{ $vendor->phone }}</div>@endif
                    </td>
                    <td>
                        @if($vendor->creator)
                            <div style="font-size:.82rem;">{{ $vendor->creator->name }}</div>
                            <div style="font-size:.68rem;color:#94a3b8;">{{ ucfirst($vendor->creator->department ?? 'Admin') }}</div>
                        @else
                            <span style="color:#94a3b8;">—</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-size:.82rem;">{{ $vendor->created_at->format('d M Y') }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $vendor->created_at->diffForHumans() }}</div>
                    </td>
                    <td>
                        <span class="badge badge-warning">{{ str_replace('_', ' ', ucfirst($vendor->status)) }}</span>
                        @if($vendor->kyc_status !== 'pending')
                            <div style="margin-top:.2rem;"><span class="badge {{ $vendor->kyc_status==='approved'?'badge-success':($vendor->kyc_status==='rejected'?'badge-danger':'badge-info') }}" style="font-size:.6rem;">KYC: {{ ucfirst($vendor->kyc_status) }}</span></div>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                            {{-- Approve --}}
                            <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}" style="display:inline;" onsubmit="return confirm('Approve vendor \'{{ $vendor->company_name }}\'? They will receive an email to create their account.')">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                            </form>

                            {{-- Reject --}}
                            <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('rejectForm{{ $vendor->id }}').style.display='block';this.style.display='none';">
                                <i class="fas fa-times"></i> Reject
                            </button>

                            {{-- Waive Membership --}}
                            @if($vendor->membership_fee > 0 && !$vendor->membership_fee_waived)
                            <form method="POST" action="{{ route('admin.vendors.waive-membership', $vendor) }}" style="display:inline;" onsubmit="return confirm('Waive membership fee of ₹{{ number_format($vendor->membership_fee,2) }}?')">
                                @csrf
                                <button type="submit" class="btn btn-outline btn-sm" title="Waive Membership Fee"><i class="fas fa-gift"></i> Waive Fee</button>
                            </form>
                            @endif
                        </div>

                        {{-- Rejection form (hidden by default) --}}
                        <div id="rejectForm{{ $vendor->id }}" style="display:none;margin-top:.5rem;padding:.75rem;background:#fef2f2;border-radius:8px;">
                            <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}">
                                @csrf
                                <input type="hidden" name="_reject" value="1">
                                <div style="margin-bottom:.4rem;">
                                    <textarea name="reason" required placeholder="Reason for rejection..." style="width:100%;padding:.4rem .6rem;border:1px solid #fca5a5;border-radius:6px;font-size:.8rem;font-family:inherit;min-height:60px;"></textarea>
                                </div>
                                <div style="display:flex;gap:.3rem;">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Confirm Reject</button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="this.closest('[id^=rejectForm]').style.display='none';">Cancel</button>
                                </div>
                            </form>
                        </div>
                        <div><a href="{{ route('admin.vendors.show', $vendor) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</a></div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;">
                        <i class="fas fa-check-circle" style="font-size:2.5rem;color:#16a34a;margin-bottom:.5rem;display:block;"></i>
                        <div style="font-size:.9rem;font-weight:600;">All caught up!</div>
                        <div style="font-size:.8rem;">No pending vendor requests.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($vendors->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $vendors->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection
