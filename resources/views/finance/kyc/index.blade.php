@extends('layouts.app')
@section('title', 'KYC Review')
@section('page-title', 'Finance — KYC Review')

@section('content')
<div style="padding:.65rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.82rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Review vendor KYC documents and <strong>assign a Vendor Code</strong> during approval. The vendor code becomes permanent after approval.
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-id-card" style="margin-right:.5rem;color:#1e3a5f;"></i> Pending KYC Approvals</h3><span style="font-size:.78rem;color:#64748b;">{{ $vendors->total() }} pending</span>
    </div>
    <div class="card-body" style="padding:0;">
        @forelse($vendors as $vendor)
        <div style="padding:1.25rem 1.4rem;border-bottom:1px solid #f1f5f9;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:.75rem;">
                <div>
                    <div style="font-size:1.05rem;font-weight:800;color:#0d1b2a;">{{ $vendor->company_name }}</div>
                    <div style="font-size:.72rem;color:#64748b;">Current code: <span style="font-family:monospace;color:#94a3b8;">{{ $vendor->vendor_code ?? '—' }}</span> (auto-generated)</div>
                    <div style="font-size:.78rem;color:#475569;margin-top:.3rem;">
                        <strong>Contact:</strong> {{ $vendor->contact_person }} · {{ $vendor->email }} · {{ $vendor->phone ?? '—' }}<br>
                        <strong>GST:</strong> <span style="font-family:monospace;">{{ $vendor->gst_number }}</span>
                        @if($vendor->iec_code) · <strong>IEC:</strong> <span style="font-family:monospace;">{{ $vendor->iec_code }}</span>@endif
                        @if($vendor->msme_number) · <strong>MSME:</strong> <span style="font-family:monospace;">{{ $vendor->msme_number }}</span>@endif
                    </div>
                    <div style="font-size:.78rem;color:#475569;margin-top:.2rem;">
                        <strong>Address:</strong> {{ $vendor->street_address ?? $vendor->address }}, {{ $vendor->city }}, {{ $vendor->province_state ?? $vendor->state }}, {{ $vendor->country }} - {{ $vendor->pincode }}
                    </div>
                    <div style="font-size:.78rem;color:#475569;margin-top:.2rem;">
                        <strong>Bank:</strong> {{ $vendor->bank_name }} ·
                        A/c: <span style="font-family:monospace;">{{ $vendor->bank_account_number }}</span> ·
                        IFSC: <span style="font-family:monospace;">{{ $vendor->bank_ifsc }}</span> ·
                        SWIFT: <span style="font-family:monospace;">{{ $vendor->bank_swift_code }}</span>
                    </div>
                </div>
                <div>
                    <div style="font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:.4rem;">Uploaded Documents ({{ $vendor->documents->count() }})</div>
                    <div style="display:flex;flex-wrap:wrap;gap:.35rem;">
                        @forelse($vendor->documents as $doc)
                        <a href="{{ asset('storage/app/public/' . $doc->file_path) }}" target="_blank" style="display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .55rem;background:#f1f5f9;border-radius:6px;font-size:.7rem;color:#1e40af;text-decoration:none;"><i class="fas fa-file-pdf"></i> {{ ucfirst(str_replace('_',' ',$doc->document_type)) }}</a>
                        @empty
                        <span style="font-size:.72rem;color:#94a3b8;">No documents uploaded.</span>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Approval Form --}}
            <form method="POST" action="{{ route('finance.kyc.approve', $vendor) }}" style="display:flex;gap:.5rem;align-items:flex-end;margin-top:.75rem;padding-top:.75rem;border-top:1px dashed #e2e8f0;">
                @csrf
                <div style="flex:1;max-width:260px;">
                    <label style="font-size:.72rem;font-weight:700;color:#1e40af;display:block;margin-bottom:.2rem;">Assign Vendor Code <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="vendor_code" value="{{ old('vendor_code', $vendor->vendor_code) }}" required maxlength="50" pattern="[A-Za-z0-9\-_]+" placeholder="e.g. VIN00123" style="width:100%;padding:.45rem .6rem;border:2px solid #93c5fd;border-radius:8px;font-family:monospace;font-size:.88rem;text-transform:uppercase;background:#fff;" title="Letters, digits, hyphens, underscores only">
                </div>
                <button type="submit" class="btn btn-success" onclick="return confirm('Approve KYC and assign this vendor code? The code will become permanent and contract will be sent.')"><i class="fas fa-check"></i> Approve KYC</button>
                <button type="button" class="btn btn-outline" style="color:#dc2626;border-color:#fca5a5;" onclick="document.getElementById('reject-{{ $vendor->id }}').style.display='block';this.style.display='none';"><i class="fas fa-times"></i> Reject</button>
            </form>

            {{-- Reject Form (hidden by default) --}}
            <form method="POST" action="{{ route('finance.kyc.reject', $vendor) }}" id="reject-{{ $vendor->id }}" style="display:none;margin-top:.75rem;padding:.75rem;background:#fef2f2;border-radius:8px;border:1px solid #fca5a5;">
                @csrf
                <label style="font-size:.72rem;font-weight:700;color:#dc2626;display:block;margin-bottom:.2rem;">Rejection Reason <span>*</span></label>
                <textarea name="reason" required rows="2" placeholder="Explain why the KYC is being rejected..." style="width:100%;padding:.5rem;border:1px solid #fca5a5;border-radius:6px;font-family:inherit;font-size:.82rem;margin-bottom:.5rem;"></textarea>
                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this KYC? Vendor will be notified.')"><i class="fas fa-times"></i> Confirm Rejection</button>
            </form>
        </div>
        @empty
        <div style="text-align:center;padding:3rem;color:#94a3b8;">
            <i class="fas fa-check-circle" style="font-size:2rem;display:block;margin-bottom:.5rem;color:#86efac;"></i>
            No pending KYC submissions. All caught up!
        </div>
        @endforelse
    </div>
    @if($vendors->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $vendors->links('pagination::tailwind') }}</div>@endif
</div>
@endsection