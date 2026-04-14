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
                <tr><th>Vendor</th><th>Company Code</th><th>Contact</th><th>Key IDs</th><th>Documents</th><th>Submitted</th><th style="width:250px;">Actions</th></tr>
            </thead>
            <tbody>
                @forelse($vendors as $vendor)
                @php $docs = $vendor->documents; @endphp
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
                        <div style="font-size:.82rem;font-weight:600;">{{ $vendor->contact_person }}</div>
                        <div style="font-size:.72rem;color:#64748b;">{{ $vendor->email }}</div>
                        <div style="font-size:.72rem;color:#64748b;">{{ $vendor->phone }}</div>
                    </td>
                    <td>
                        <div style="display:flex;flex-direction:column;gap:.15rem;">
                            @if($vendor->gst_number)<span style="font-size:.7rem;"><strong>GST:</strong> {{ $vendor->gst_number }}</span>@endif
                            @if($vendor->iec_code)<span style="font-size:.7rem;"><strong>IEC:</strong> {{ $vendor->iec_code }}</span>@endif
                            @if($vendor->msme_number)<span style="font-size:.7rem;"><strong>MSME:</strong> {{ $vendor->msme_number }}</span>@endif
                        </div>
                    </td>
                    <td>
                        @if($docs->count() > 0)
                            <span style="font-size:.78rem;font-weight:600;color:#1e40af;">{{ $docs->count() }} file(s)</span>
                            <div style="display:flex;flex-wrap:wrap;gap:.15rem;margin-top:.2rem;">
                                @foreach($docs->take(3) as $doc)
                                <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" title="{{ $doc->document_name }}" style="display:inline-flex;align-items:center;gap:.15rem;padding:.1rem .3rem;background:#f1f5f9;border-radius:3px;font-size:.6rem;color:#1e40af;text-decoration:none;">
                                    <i class="fas {{ str_contains($doc->file_path ?? '', '.pdf') ? 'fa-file-pdf' : 'fa-file-image' }}" style="font-size:.55rem;"></i>
                                    {{ Str::limit(ucfirst(str_replace('_',' ',$doc->document_type ?? 'doc')), 12) }}
                                </a>
                                @endforeach
                                @if($docs->count() > 3)<span style="font-size:.6rem;color:#94a3b8;">+{{ $docs->count()-3 }}</span>@endif
                            </div>
                        @else
                            <span style="font-size:.78rem;color:#dc2626;"><i class="fas fa-exclamation-circle"></i> None</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-size:.82rem;">{{ $vendor->kyc_submitted_at?->format('d M Y') ?? '—' }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $vendor->kyc_submitted_at?->diffForHumans() ?? '' }}</div>
                    </td>
                    <td>
                        <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="toggleRow('detail{{ $vendor->id }}')"><i class="fas fa-eye"></i> Review</button>
                            <form method="POST" action="{{ route('finance.kyc.approve', $vendor) }}" style="display:inline;" onsubmit="return confirm('Approve KYC for {{ $vendor->company_name }}?\n\nA contract will be sent via DocuSign.')">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                            </form>
                            <button type="button" class="btn btn-danger btn-sm" onclick="toggleRow('reject{{ $vendor->id }}')"><i class="fas fa-times"></i> Reject</button>
                        </div>
                    </td>
                </tr>

                {{-- Expandable Detail Row — Full Registration Form Review --}}
                <tr id="detail{{ $vendor->id }}" style="display:none;">
                    <td colspan="7" style="padding:0;background:#f8fafc;">
                        <div style="padding:1.25rem 1.5rem;">
                            <div style="font-size:.9rem;font-weight:800;color:#0d1b2a;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;">
                                <i class="fas fa-clipboard-check" style="color:#e8a838;"></i> Registration Form — {{ $vendor->company_name }}
                                <span style="margin-left:auto;font-size:.72rem;font-weight:500;color:#64748b;">Vendor Code: {{ $vendor->vendor_code }}</span>
                            </div>

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
                                {{-- Left Column: Business & Contact --}}
                                <div>
                                    <div style="font-size:.78rem;font-weight:700;color:#1e3a5f;margin-bottom:.5rem;padding-bottom:.25rem;border-bottom:2px solid #e8a838;">Business Details</div>

                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;">
                                        @foreach([
                                            ['1. VAT/GST Number', $vendor->gst_number, true, 'gst_certificate'],
                                            ['2. Company Name', $vendor->company_name, false, null],
                                            ['7. IEC Code', $vendor->iec_code, true, 'iec_certificate'],
                                            ['8. MSME Number', $vendor->msme_number, false, 'msme_certificate'],
                                        ] as [$label, $value, $required, $docType])
                                        <div style="grid-column:{{ $loop->index % 2 == 0 ? '1' : '2' }};padding:.4rem .6rem;background:#fff;border-radius:6px;border:1px solid #e8ecf1;">
                                            <div style="font-size:.62rem;color:#64748b;font-weight:600;text-transform:uppercase;">{{ $label }}{{ $required?' *':'' }}</div>
                                            <div style="font-size:.82rem;font-weight:{{ $value?'600':'400' }};color:{{ $value?'#0d1b2a':'#dc2626' }};">
                                                {{ $value ?: ($required ? '⚠ Missing' : '—') }}
                                            </div>
                                            @if($docType)
                                                @php $d = $docs->where('document_type', $docType)->first(); @endphp
                                                @if($d)
                                                    <a href="{{ asset('storage/'.$d->file_path) }}" target="_blank" style="font-size:.62rem;color:#1e40af;"><i class="fas fa-paperclip"></i> {{ Str::limit($d->document_name, 20) }}</a>
                                                @elseif($required)
                                                    <span style="font-size:.62rem;color:#dc2626;"><i class="fas fa-exclamation-triangle"></i> No certificate</span>
                                                @endif
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>

                                    <div style="font-size:.78rem;font-weight:700;color:#1e3a5f;margin-top:1rem;margin-bottom:.5rem;padding-bottom:.25rem;border-bottom:2px solid #e8a838;">Registered Address</div>
                                    <div style="padding:.5rem .7rem;background:#fff;border-radius:6px;border:1px solid #e8ecf1;">
                                        <div style="font-size:.82rem;color:#0d1b2a;">
                                            {{ $vendor->street_address ?? $vendor->address ?? '—' }}<br>
                                            {{ $vendor->city ?? '' }}{{ ($vendor->city && ($vendor->province_state || $vendor->state)) ? ', ' : '' }}{{ $vendor->province_state ?? $vendor->state ?? '' }}<br>
                                            {{ $vendor->pincode ?? '' }}{{ ($vendor->pincode && $vendor->country) ? ', ' : '' }}{{ $vendor->country ?? '' }}
                                        </div>
                                    </div>

                                    <div style="font-size:.78rem;font-weight:700;color:#1e3a5f;margin-top:1rem;margin-bottom:.5rem;padding-bottom:.25rem;border-bottom:2px solid #e8a838;">Contact Information</div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;">
                                        @foreach([
                                            ['4. Contact Person', $vendor->contact_person],
                                            ['Finance Contact', $vendor->finance_contact_person],
                                            ['5. Mobile No', $vendor->phone],
                                            ['6. Email', $vendor->email],
                                            ['9. Landline', $vendor->landline],
                                            ['10. Website', $vendor->official_website],
                                        ] as [$label, $value])
                                        <div style="padding:.35rem .6rem;background:#fff;border-radius:6px;border:1px solid #e8ecf1;">
                                            <div style="font-size:.62rem;color:#64748b;font-weight:600;">{{ $label }}</div>
                                            @if($label === '10. Website' && $value)
                                                <a href="{{ $value }}" target="_blank" style="font-size:.78rem;color:#1e40af;">{{ Str::limit($value, 30) }}</a>
                                            @else
                                                <div style="font-size:.82rem;font-weight:{{ $value?'600':'400' }};color:{{ $value?'#0d1b2a':'#94a3b8' }};">{{ $value ?: '—' }}</div>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Right Column: Bank & Documents --}}
                                <div>
                                    <div style="font-size:.78rem;font-weight:700;color:#1e3a5f;margin-bottom:.5rem;padding-bottom:.25rem;border-bottom:2px solid #2d6a4f;">Bank Details</div>

                                    @foreach([
                                        ['11. Bank Name', $vendor->bank_name, true],
                                        ['12. IFSC Code', $vendor->bank_ifsc, true],
                                        ['13. SWIFT / BIC', $vendor->bank_swift_code, false],
                                        ['14. Account Number', $vendor->bank_account_number, true],
                                    ] as [$label, $value, $required])
                                    <div style="padding:.45rem .7rem;background:#fff;border-radius:6px;border:1px solid #e8ecf1;margin-bottom:.35rem;">
                                        <div style="display:flex;justify-content:space-between;align-items:center;">
                                            <div>
                                                <div style="font-size:.62rem;color:#64748b;font-weight:600;text-transform:uppercase;">{{ $label }}{{ $required?' *':'' }}</div>
                                                <div style="font-size:.88rem;font-weight:700;font-family:monospace;color:{{ $value?'#0d1b2a':'#dc2626' }};">{{ $value ?: ($required ? '⚠ Missing' : '—') }}</div>
                                            </div>
                                            @if($value)<i class="fas fa-check-circle" style="color:#16a34a;"></i>@endif
                                        </div>
                                    </div>
                                    @endforeach

                                    @php $chequeDoc = $docs->where('document_type', 'cancelled_cheque')->first(); @endphp
                                    <div style="padding:.45rem .7rem;background:{{ $chequeDoc?'#f0fdf4':'#fef2f2' }};border-radius:6px;border:1px solid {{ $chequeDoc?'#bbf7d0':'#fecaca' }};margin-bottom:.35rem;">
                                        <div style="font-size:.62rem;color:#64748b;font-weight:600;">CANCELLED CHEQUE / BANK PROOF</div>
                                        @if($chequeDoc)
                                            <a href="{{ asset('storage/'.$chequeDoc->file_path) }}" target="_blank" style="font-size:.82rem;color:#16a34a;font-weight:600;"><i class="fas fa-file-pdf"></i> {{ $chequeDoc->document_name }}</a>
                                        @else
                                            <span style="font-size:.82rem;color:#dc2626;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Not uploaded</span>
                                        @endif
                                    </div>

                                    <div style="font-size:.78rem;font-weight:700;color:#1e3a5f;margin-top:1rem;margin-bottom:.5rem;padding-bottom:.25rem;border-bottom:2px solid #7c3aed;">All Uploaded Documents ({{ $docs->count() }})</div>
                                    @if($docs->count() > 0)
                                        @foreach($docs as $doc)
                                        <div style="display:flex;align-items:center;gap:.5rem;padding:.4rem .6rem;margin-bottom:.25rem;background:#fff;border-radius:6px;border:1px solid #e8ecf1;">
                                            <i class="fas {{ str_contains($doc->file_path ?? '', '.pdf') ? 'fa-file-pdf' : 'fa-file-image' }}" style="color:#dc2626;font-size:.85rem;"></i>
                                            <div style="flex:1;min-width:0;">
                                                <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" style="font-size:.78rem;color:#1e40af;text-decoration:none;font-weight:600;">{{ $doc->document_name }}</a>
                                                <div style="font-size:.62rem;color:#94a3b8;">{{ ucfirst(str_replace('_',' ',$doc->document_type ?? 'other')) }} · {{ $doc->created_at?->format('d M Y') }}</div>
                                            </div>
                                            <span class="badge {{ $doc->status==='verified'?'badge-success':($doc->status==='rejected'?'badge-danger':'badge-gray') }}" style="font-size:.6rem;">{{ ucfirst($doc->status ?? 'pending') }}</span>
                                        </div>
                                        @endforeach
                                    @else
                                        <div style="text-align:center;padding:1rem;color:#dc2626;font-size:.82rem;"><i class="fas fa-exclamation-triangle"></i> No documents uploaded</div>
                                    @endif

                                    {{-- Verification Checklist --}}
                                    <div style="font-size:.78rem;font-weight:700;color:#1e3a5f;margin-top:1rem;margin-bottom:.5rem;padding-bottom:.25rem;border-bottom:2px solid #dc2626;">Verification Checklist</div>
                                    @php
                                        $checks = [
                                            ['VAT/GST Number', !empty($vendor->gst_number)],
                                            ['GST Certificate', $docs->where('document_type','gst_certificate')->count() > 0],
                                            ['IEC Code', !empty($vendor->iec_code)],
                                            ['IEC Certificate', $docs->where('document_type','iec_certificate')->count() > 0],
                                            ['Bank Name', !empty($vendor->bank_name)],
                                            ['IFSC Code', !empty($vendor->bank_ifsc)],
                                            ['Account Number', !empty($vendor->bank_account_number)],
                                            ['Cancelled Cheque', $docs->where('document_type','cancelled_cheque')->count() > 0],
                                            ['Mobile Number', !empty($vendor->phone)],
                                            ['Email ID', !empty($vendor->email)],
                                        ];
                                        $passCount = collect($checks)->where(1, true)->count();
                                    @endphp
                                    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
                                        <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;"><div style="height:100%;width:{{ round(($passCount/count($checks))*100) }}%;background:{{ $passCount===count($checks)?'#16a34a':'#e8a838' }};border-radius:4px;"></div></div>
                                        <span style="font-size:.72rem;font-weight:700;color:{{ $passCount===count($checks)?'#16a34a':'#e8a838' }};">{{ $passCount }}/{{ count($checks) }}</span>
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.2rem;">
                                        @foreach($checks as [$checkLabel, $passed])
                                        <div style="display:flex;align-items:center;gap:.25rem;font-size:.72rem;color:{{ $passed?'#16a34a':'#dc2626' }};">
                                            <i class="fas {{ $passed?'fa-check-circle':'fa-times-circle' }}" style="font-size:.6rem;"></i> {{ $checkLabel }}
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>

                {{-- Reject Form Row --}}
                <tr id="reject{{ $vendor->id }}" style="display:none;">
                    <td colspan="7" style="padding:.75rem 1.5rem;background:#fef2f2;">
                        <form method="POST" action="{{ route('finance.kyc.reject', $vendor) }}" style="display:flex;gap:.5rem;align-items:flex-end;">
                            @csrf
                            <div style="flex:1;">
                                <label style="font-size:.72rem;font-weight:600;color:#dc2626;">Reason for Rejection *</label>
                                <textarea name="reason" required placeholder="Specify what needs to be corrected..." style="width:100%;padding:.4rem .6rem;border:1px solid #fca5a5;border-radius:6px;font-size:.82rem;font-family:inherit;min-height:50px;"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Confirm Reject</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="toggleRow('reject{{ $vendor->id }}')">Cancel</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;">
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

@push('scripts')
<script>function toggleRow(id){var r=document.getElementById(id);r.style.display=r.style.display==='none'?'table-row':'none';}</script>
@endpush
@endsection