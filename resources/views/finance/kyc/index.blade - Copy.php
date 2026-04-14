@extends('layouts.app')
@section('title', 'KYC Review')
@section('page-title', 'Vendor KYC Review')

@section('content')
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-id-card" style="margin-right:.5rem;color:#e8a838;"></i> Pending KYC Approvals</h3>
        <span class="badge badge-warning" style="font-size:.78rem;">{{ $vendors->total() }} pending</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr><th>Vendor</th><th>Company Code</th><th>Contact</th><th>Documents</th><th>Submitted</th><th style="width:220px;">Actions</th></tr>
            </thead>
            <tbody>
                @forelse($vendors as $vendor)
                <tr>
                    <td>
                        <div style="font-weight:600;color:#0d1b2a;">{{ $vendor->company_name }}</div>
                        <div style="font-size:.7rem;color:#94a3b8;">{{ $vendor->vendor_code }}</div>
                    </td>
                    <td>
                        @php $cc = ['2000'=>['🇮🇳','India','#dcfce7'],'2100'=>['🇺🇸','USA','#dbeafe'],'2200'=>['🇳🇱','NL','#fef3c7']]; @endphp
                        <span style="padding:.2rem .5rem;background:{{ $cc[$vendor->company_code][2] ?? '#f1f5f9' }};border-radius:6px;font-size:.78rem;font-weight:600;">
                            {{ $cc[$vendor->company_code][0] ?? '' }} {{ $vendor->company_code }}
                        </span>
                    </td>
                    <td>
                        <div style="font-size:.83rem;">{{ $vendor->contact_person }}</div>
                        <div style="font-size:.72rem;color:#64748b;">{{ $vendor->email }}</div>
                    </td>
                    <td>
                        @php $docs = $vendor->documents->where('document_type', 'kyc'); @endphp
                        @if($docs->count() > 0)
                            @foreach($docs as $doc)
                            <div style="display:flex;align-items:center;gap:.3rem;margin-bottom:.2rem;">
                                <i class="fas fa-file-pdf" style="color:#dc2626;font-size:.75rem;"></i>
                                <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" style="font-size:.78rem;color:#1e40af;text-decoration:none;">
                                    {{ Str::limit($doc->document_name, 25) }}
                                </a>
                                <span class="badge {{ $doc->status==='verified'?'badge-success':($doc->status==='rejected'?'badge-danger':'badge-gray') }}" style="font-size:.55rem;">{{ ucfirst($doc->status) }}</span>
                            </div>
                            @endforeach
                        @else
                            <span style="font-size:.78rem;color:#94a3b8;">No documents uploaded</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-size:.82rem;">{{ $vendor->kyc_submitted_at?->format('d M Y') ?? '—' }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $vendor->kyc_submitted_at?->diffForHumans() ?? '' }}</div>
                    </td>
                    <td>
                        <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                            {{-- Approve --}}
                            <form method="POST" action="{{ route('finance.kyc.approve', $vendor) }}" style="display:inline;" onsubmit="return confirm('Approve KYC for {{ $vendor->company_name }}? A contract will be sent via DocuSign.')">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                            </form>

                            {{-- Reject Toggle --}}
                            <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('reject{{ $vendor->id }}').style.display='block';this.style.display='none';">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>

                        {{-- Reject Form --}}
                        <div id="reject{{ $vendor->id }}" style="display:none;margin-top:.5rem;padding:.6rem;background:#fef2f2;border-radius:8px;">
                            <form method="POST" action="{{ route('finance.kyc.reject', $vendor) }}">
                                @csrf
                                <textarea name="reason" required placeholder="Reason for rejection..." style="width:100%;padding:.35rem .5rem;border:1px solid #fca5a5;border-radius:6px;font-size:.78rem;font-family:inherit;min-height:50px;margin-bottom:.4rem;"></textarea>
                                <div style="display:flex;gap:.3rem;">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Confirm Reject</button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="this.closest('[id^=reject]').style.display='none'">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:3rem;color:#94a3b8;">
                        <i class="fas fa-check-circle" style="font-size:2rem;color:#16a34a;display:block;margin-bottom:.5rem;"></i>
                        No pending KYC reviews.
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