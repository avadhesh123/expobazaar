@extends('layouts.app')
@section('title', 'Vendor — ' . $vendor->company_name)
@section('page-title', 'Vendor Information — ' . $vendor->company_name)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    @if(auth()->user()->isAdmin())
        <a href="{{ route('admin.vendors.pending') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Vendors</a>
    @else
        <a href="{{ route('sourcing.vendors') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Vendors</a>
    @endif
</div>

{{-- Status Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:1.2rem;font-weight:800;color:#0d1b2a;">{{ $vendor->company_name }}</div>
                <div style="font-size:.82rem;color:#64748b;font-family:monospace;">{{ $vendor->vendor_code }} · Company {{ $vendor->company_code }}</div>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;">
                @php
                    $sc = ['pending_approval'=>'badge-warning','pending_kyc'=>'badge-warning','active'=>'badge-success','suspended'=>'badge-danger','rejected'=>'badge-danger'];
                    $kc = ['pending'=>'badge-gray','submitted'=>'badge-warning','approved'=>'badge-success','rejected'=>'badge-danger'];
                @endphp
                <span class="badge {{ $sc[$vendor->status] ?? 'badge-gray' }}" style="font-size:.82rem;padding:.3rem .75rem;">{{ ucfirst(str_replace('_',' ',$vendor->status)) }}</span>
                <span class="badge {{ $kc[$vendor->kyc_status] ?? 'badge-gray' }}" style="font-size:.82rem;padding:.3rem .75rem;">KYC: {{ ucfirst($vendor->kyc_status ?? 'pending') }}</span>
            </div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
    {{-- Company Information --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-building" style="margin-right:.5rem;color:#1e3a5f;"></i> Company Information</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Company Name</div><div style="font-weight:600;">{{ $vendor->company_name }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Vendor Code</div><div style="font-family:monospace;font-weight:600;">{{ $vendor->vendor_code }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Contact Person</div><div>{{ $vendor->contact_person }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Finance Contact</div><div>{{ $vendor->finance_contact_person ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Email</div><div><a href="mailto:{{ $vendor->email }}" style="color:#1e40af;">{{ $vendor->email }}</a></div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Phone</div><div>{{ $vendor->phone ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Landline</div><div>{{ $vendor->landline ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Website</div><div>@if($vendor->official_website)<a href="{{ $vendor->official_website }}" target="_blank" style="color:#1e40af;">{{ $vendor->official_website }}</a>@else — @endif</div></div>
            </div>
        </div>
    </div>

    {{-- Address --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-map-marker-alt" style="margin-right:.5rem;color:#dc2626;"></i> Address</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div style="grid-column:span 2;"><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Street Address</div><div>{{ $vendor->street_address ?? $vendor->address ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">City</div><div>{{ $vendor->city ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Province / State</div><div>{{ $vendor->province_state ?? $vendor->state ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Country</div><div>{{ $vendor->country ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Pincode</div><div>{{ $vendor->pincode ?? '—' }}</div></div>
            </div>
        </div>
    </div>

    {{-- Tax & Compliance --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-file-invoice" style="margin-right:.5rem;color:#e8a838;"></i> Tax & Compliance</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">GST / VAT Number</div><div style="font-family:monospace;font-weight:600;">{{ $vendor->gst_number ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">PAN Number</div><div style="font-family:monospace;">{{ $vendor->pan_number ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">IEC Code</div><div style="font-family:monospace;">{{ $vendor->iec_code ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">MSME Number</div><div style="font-family:monospace;">{{ $vendor->msme_number ?? '—' }}</div></div>
            </div>
        </div>
    </div>

    {{-- Bank Details --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-university" style="margin-right:.5rem;color:#2d6a4f;"></i> Bank Details</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Bank Name</div><div style="font-weight:600;">{{ $vendor->bank_name ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Account Number</div><div style="font-family:monospace;">{{ $vendor->bank_account_number ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">IFSC Code</div><div style="font-family:monospace;">{{ $vendor->bank_ifsc ?? '—' }}</div></div>
                <div><div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">SWIFT Code</div><div style="font-family:monospace;">{{ $vendor->bank_swift_code ?? '—' }}</div></div>
            </div>
        </div>
    </div>
</div>

{{-- Documents / Certificates --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-folder-open" style="margin-right:.5rem;color:#7c3aed;"></i> Uploaded Documents & Certificates</h3></div>
    <div class="card-body">
        @if($vendor->documents && $vendor->documents->count() > 0)
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.75rem;">
            @foreach($vendor->documents as $doc)
            <div style="padding:.75rem;background:#f8fafc;border-radius:10px;border:1px solid #e8ecf1;display:flex;align-items:center;gap:.65rem;">
                <div style="width:40px;height:40px;border-radius:8px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas {{ str_contains($doc->file_type ?? '', 'pdf') ? 'fa-file-pdf' : 'fa-file-image' }}" style="color:#7c3aed;"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.78rem;font-weight:600;color:#0d1b2a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ ucfirst(str_replace('_',' ',$doc->document_name)) }}</div>
                    <div style="font-size:.65rem;color:#94a3b8;">{{ ucfirst($doc->document_type) }} · {{ $doc->created_at?->format('d M Y') }}</div>
                </div>
                @if($doc->file_path)
                <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" class="btn btn-outline btn-sm" title="View"><i class="fas fa-eye"></i></a>
                @endif
            </div>
            @endforeach
        </div>
        @else
        <div style="text-align:center;padding:2rem;color:#94a3b8;"><i class="fas fa-folder-open" style="font-size:1.5rem;display:block;margin-bottom:.5rem;"></i>No documents uploaded yet.</div>
        @endif
    </div>
</div>

{{-- KYC Timeline --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-history" style="margin-right:.5rem;color:#64748b;"></i> KYC Timeline</h3></div>
    <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:.75rem;">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="width:10px;height:10px;border-radius:50%;background:#16a34a;flex-shrink:0;"></div>
                <div><div style="font-size:.82rem;font-weight:600;">Vendor Created</div><div style="font-size:.72rem;color:#94a3b8;">{{ $vendor->created_at?->format('d M Y H:i') }} · By {{ $vendor->creator->name ?? 'System' }}</div></div>
            </div>
            @if($vendor->kyc_submitted_at)
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="width:10px;height:10px;border-radius:50%;background:#1e40af;flex-shrink:0;"></div>
                <div><div style="font-size:.82rem;font-weight:600;">KYC Submitted</div><div style="font-size:.72rem;color:#94a3b8;">{{ $vendor->kyc_submitted_at->format('d M Y H:i') }}</div></div>
            </div>
            @endif
            @if($vendor->kyc_approved_at)
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="width:10px;height:10px;border-radius:50%;background:#16a34a;flex-shrink:0;"></div>
                <div><div style="font-size:.82rem;font-weight:600;">KYC Approved</div><div style="font-size:.72rem;color:#94a3b8;">{{ $vendor->kyc_approved_at->format('d M Y H:i') }}</div></div>
            </div>
            @elseif($vendor->kyc_status === 'rejected')
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="width:10px;height:10px;border-radius:50%;background:#dc2626;flex-shrink:0;"></div>
                <div><div style="font-size:.82rem;font-weight:600;color:#dc2626;">KYC Rejected</div><div style="font-size:.72rem;color:#94a3b8;">Reason: {{ $vendor->kyc_rejection_reason ?? '—' }}</div></div>
            </div>
            @endif
            @if($vendor->contract_signed_at)
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="width:10px;height:10px;border-radius:50%;background:#16a34a;flex-shrink:0;"></div>
                <div><div style="font-size:.82rem;font-weight:600;">Contract Signed</div><div style="font-size:.72rem;color:#94a3b8;">{{ $vendor->contract_signed_at->format('d M Y H:i') }}</div></div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Admin Actions --}}
@if(auth()->user()->isAdmin() && $vendor->status === 'pending_approval')
<div class="card" style="margin-top:1.25rem;border-color:#e8a838;">
    <div class="card-header" style="background:#fffbeb;"><h3 style="color:#92400e;"><i class="fas fa-user-check" style="margin-right:.5rem;"></i> Admin Actions</h3></div>
    <div class="card-body" style="display:flex;gap:.75rem;">
        <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}" onsubmit="return confirm('Approve this vendor? Welcome email will be sent.')">@csrf<button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Approve Vendor</button></form>
    </div>
</div>
@endif
@endsection
