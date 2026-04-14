@extends('layouts.app')
@section('title', 'KYC Registration')
@section('page-title', 'Customer / Vendor Registration Form')

@section('content')
{{-- Status Banners --}}
@if($vendor && $vendor->kyc_status === 'approved')
<div style="padding:.85rem 1.2rem;background:#dcfce7;border-radius:10px;border:1px solid #bbf7d0;margin-bottom:1.25rem;">
    <div style="font-size:.85rem;font-weight:700;color:#166534;"><i class="fas fa-check-circle" style="margin-right:.3rem;"></i> KYC Approved! Your vendor panel is now fully active.</div>
</div>
@elseif($vendor && $vendor->kyc_status === 'submitted')
<div style="padding:.85rem 1.2rem;background:#fef3c7;border-radius:10px;border:1px solid #fde68a;margin-bottom:1.25rem;">
    <div style="font-size:.85rem;font-weight:600;color:#92400e;"><i class="fas fa-clock" style="margin-right:.3rem;"></i> KYC submitted. Pending Finance team review. Other modules will unlock once approved.</div>
</div>
@elseif($vendor && $vendor->kyc_status === 'rejected')
<div style="padding:.85rem 1.2rem;background:#fee2e2;border-radius:10px;border:1px solid #fecaca;margin-bottom:1.25rem;">
    <div style="font-size:.85rem;font-weight:600;color:#dc2626;"><i class="fas fa-times-circle" style="margin-right:.3rem;"></i> KYC Rejected. Please re-submit with corrections.</div>
    @if($vendor->kyc_rejection_reason)<div style="font-size:.82rem;color:#991b1b;margin-top:.25rem;">Reason: {{ $vendor->kyc_rejection_reason }}</div>@endif
</div>
@endif

<div style="max-width:780px;">

    {{-- Contract Download Section --}}
    <div class="card" style="margin-bottom:1.25rem;border-color:#1e3a5f;">
        <div class="card-header" style="background:#1e3a5f;">
            <h3 style="color:#fff;"><i class="fas fa-file-contract" style="margin-right:.5rem;"></i> Consignment Vendor Agreement</h3>
        </div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:1rem;">
                <div style="width:56px;height:56px;background:#fee2e2;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-file-pdf" style="font-size:1.5rem;color:#dc2626;"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-size:.88rem;font-weight:700;color:#0d1b2a;">Consignment Contract — ExpoBazaar</div>
                    <div style="font-size:.78rem;color:#64748b;margin-top:.15rem;">Download, review, sign, and upload the contract as part of your KYC submission. This is mandatory.</div>
                </div>
                <a href="{{ route('vendor.contract.download') }}" class="btn btn-primary btn-sm" style="flex-shrink:0;"><i class="fas fa-download" style="margin-right:.3rem;"></i> Download Contract</a>
            </div>
            <div style="margin-top:.6rem;padding:.5rem .75rem;background:#fef3c7;border-radius:6px;font-size:.75rem;color:#92400e;">
                <i class="fas fa-exclamation-triangle" style="margin-right:.2rem;"></i>
                <strong>Steps:</strong> 1) Download the contract above → 2) Print, review & sign → 3) Scan/photograph the signed copy → 4) Upload below with your KYC submission
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('vendor.kyc.submit') }}" enctype="multipart/form-data">
        @csrf

        {{-- Registration Form --}}
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header" style="background:linear-gradient(135deg,#1e3a5f,#2d6a4f);border-radius:14px 14px 0 0;">
                <h3 style="color:#fff;"><i class="fas fa-id-card" style="margin-right:.5rem;"></i> Registration Form</h3>
            </div>
            <div class="card-body">

                {{-- 1. VAT/GST --}}
                <div style="display:flex;gap:1rem;align-items:flex-end;margin-bottom:1rem;">
                    <div style="flex:1;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>1. V.A.T Number / GST <span style="color:#dc2626;">*</span></label>
                            <input type="text" name="gst_number" value="{{ old('gst_number', $vendor->gst_number ?? '') }}" required placeholder="e.g. 22AAAAA0000A1Z5">
                        </div>
                    </div>
                    <div style="min-width:200px;">
                        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem;">GST Certificate <span style="color:#dc2626;">*</span></label>
                        <input type="file" name="documents[gst_certificate]" accept=".pdf,.jpg,.jpeg,.png" style="font-size:.78rem;">
                        @php $gstDoc = $documents->where('document_type', 'gst_certificate')->first(); @endphp
                        @if($gstDoc)<div style="font-size:.68rem;color:#16a34a;margin-top:.2rem;"><i class="fas fa-check-circle"></i> <a href="{{ asset('storage/'.$gstDoc->file_path) }}" target="_blank" style="color:#16a34a;">{{ $gstDoc->document_name }}</a></div>@endif
                    </div>
                </div>

                {{-- 2. Vendor Name --}}
                <div class="form-group">
                    <label>2. Vendor / Customer Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="company_name" value="{{ old('company_name', $vendor->company_name ?? '') }}" required placeholder="Legal entity name" {{ $vendor ? 'readonly style=background:#f1f5f9;' : '' }}>
                </div>

                {{-- 3. Address --}}
                <div style="font-size:.82rem;font-weight:700;color:#0d1b2a;margin-bottom:.5rem;">3. Registered Address</div>
                <div class="form-group"><label>Street Name & Number</label><input type="text" name="street_address" value="{{ old('street_address', $vendor->street_address ?? '') }}" placeholder="Street name and number"></div>
                <div class="grid-2">
                    <div class="form-group"><label>City / Town</label><input type="text" name="city" value="{{ old('city', $vendor->city ?? '') }}" placeholder="City or town"></div>
                    <div class="form-group"><label>Province / State</label><input type="text" name="province_state" value="{{ old('province_state', $vendor->province_state ?? $vendor->state ?? '') }}" placeholder="Province or state"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label>Pin Code</label><input type="text" name="pincode" value="{{ old('pincode', $vendor->pincode ?? '') }}" placeholder="Postal / Pin code"></div>
                    <div class="form-group"><label>Country</label><input type="text" name="country" value="{{ old('country', $vendor->country ?? '') }}" placeholder="Country"></div>
                </div>

                {{-- 4. Contact --}}
                <div class="grid-2">
                    <div class="form-group"><label>4. Contact Person <span style="color:#dc2626;">*</span></label><input type="text" name="contact_person" value="{{ old('contact_person', $vendor->contact_person ?? '') }}" required placeholder="Primary contact"></div>
                    <div class="form-group"><label>Finance Contact Person</label><input type="text" name="finance_contact_person" value="{{ old('finance_contact_person', $vendor->finance_contact_person ?? '') }}" placeholder="Finance contact"></div>
                </div>

                {{-- 5. Mobile --}}
                <div class="form-group"><label>5. Mobile No <span style="color:#dc2626;">*</span></label><input type="text" name="phone" value="{{ old('phone', $vendor->phone ?? '') }}" required placeholder="+91 98765 43210"></div>

                {{-- 6. Email --}}
                <div class="form-group"><label>6. Email I.D <span style="color:#dc2626;">*</span></label><input type="email" name="email" value="{{ old('email', $vendor->email ?? auth()->user()->email) }}" required placeholder="vendor@company.com"></div>

                {{-- 7. IEC Code --}}
                <div style="display:flex;gap:1rem;align-items:flex-end;margin-bottom:1rem;">
                    <div style="flex:1;"><div class="form-group" style="margin-bottom:0;"><label>7. IEC Code <span style="color:#dc2626;">*</span></label><input type="text" name="iec_code" value="{{ old('iec_code', $vendor->iec_code ?? '') }}" required placeholder="e.g. 0305012345"></div></div>
                    <div style="min-width:200px;">
                        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem;">IEC Certificate <span style="color:#dc2626;">*</span></label>
                        <input type="file" name="documents[iec_certificate]" accept=".pdf,.jpg,.jpeg,.png" style="font-size:.78rem;">
                        @php $iecDoc = $documents->where('document_type', 'iec_certificate')->first(); @endphp
                        @if($iecDoc)<div style="font-size:.68rem;color:#16a34a;margin-top:.2rem;"><i class="fas fa-check-circle"></i> <a href="{{ asset('storage/'.$iecDoc->file_path) }}" target="_blank" style="color:#16a34a;">{{ $iecDoc->document_name }}</a></div>@endif
                    </div>
                </div>

                {{-- 8. MSME --}}
                <div style="display:flex;gap:1rem;align-items:flex-end;margin-bottom:1rem;">
                    <div style="flex:1;"><div class="form-group" style="margin-bottom:0;"><label>8. MSME Number</label><input type="text" name="msme_number" value="{{ old('msme_number', $vendor->msme_number ?? '') }}" placeholder="MSME registration (if applicable)"></div></div>
                    <div style="min-width:200px;">
                        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem;">MSME Certificate</label>
                        <input type="file" name="documents[msme_certificate]" accept=".pdf,.jpg,.jpeg,.png" style="font-size:.78rem;">
                        @php $msmeDoc = $documents->where('document_type', 'msme_certificate')->first(); @endphp
                        @if($msmeDoc)<div style="font-size:.68rem;color:#16a34a;margin-top:.2rem;"><i class="fas fa-check-circle"></i> <a href="{{ asset('storage/'.$msmeDoc->file_path) }}" target="_blank" style="color:#16a34a;">{{ $msmeDoc->document_name }}</a></div>@endif
                    </div>
                </div>

                {{-- 9. Landline --}}
                <div class="form-group"><label>9. Landline No</label><input type="text" name="landline" value="{{ old('landline', $vendor->landline ?? '') }}" placeholder="Landline with STD code"></div>

                {{-- 10. Website --}}
                <div class="form-group"><label>10. Official Web Site</label><input type="url" name="official_website" value="{{ old('official_website', $vendor->official_website ?? '') }}" placeholder="https://www.company.com"></div>

                <hr style="border:none;border-top:2px solid #e8ecf1;margin:1.5rem 0;">

                {{-- Bank Details --}}
                <div style="font-size:.9rem;font-weight:700;color:#0d1b2a;margin-bottom:.75rem;"><i class="fas fa-university" style="margin-right:.4rem;color:#1e3a5f;"></i> Bank Details</div>
                <div class="form-group"><label>11. Bank Name <span style="color:#dc2626;">*</span></label><input type="text" name="bank_name" value="{{ old('bank_name', $vendor->bank_name ?? '') }}" required placeholder="Bank name"></div>
                <div class="form-group"><label>12. IFSC Code <span style="color:#dc2626;">*</span></label><input type="text" name="bank_ifsc" value="{{ old('bank_ifsc', $vendor->bank_ifsc ?? '') }}" required placeholder="e.g. SBIN0001234"></div>
                <div class="form-group"><label>13. SWIFT / BIC Code</label><input type="text" name="bank_swift_code" value="{{ old('bank_swift_code', $vendor->bank_swift_code ?? '') }}" placeholder="SWIFT/BIC code (international)"></div>
                <div class="form-group"><label>14. Account Number <span style="color:#dc2626;">*</span></label><input type="text" name="bank_account_number" value="{{ old('bank_account_number', $vendor->bank_account_number ?? '') }}" required placeholder="Bank account number"></div>

                {{-- Cancelled Cheque --}}
                <div style="margin-top:.5rem;margin-bottom:1rem;">
                    <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem;">Cancelled Cheque / Bank Proof</label>
                    <input type="file" name="documents[cancelled_cheque]" accept=".pdf,.jpg,.jpeg,.png" style="font-size:.78rem;">
                    @php $chequeDoc = $documents->where('document_type', 'cancelled_cheque')->first(); @endphp
                    @if($chequeDoc)<div style="font-size:.68rem;color:#16a34a;margin-top:.2rem;"><i class="fas fa-check-circle"></i> <a href="{{ asset('storage/'.$chequeDoc->file_path) }}" target="_blank" style="color:#16a34a;">{{ $chequeDoc->document_name }}</a></div>@endif
                </div>

                <hr style="border:none;border-top:2px solid #e8ecf1;margin:1.5rem 0;">

                {{-- Signed Contract Upload (MANDATORY) --}}
                <div style="font-size:.9rem;font-weight:700;color:#dc2626;margin-bottom:.75rem;"><i class="fas fa-file-signature" style="margin-right:.4rem;"></i> Signed Contract Upload <span style="font-size:.72rem;font-weight:500;color:#64748b;">(Mandatory)</span></div>
                <div style="padding:1rem;background:#fef2f2;border-radius:10px;border:2px dashed #fca5a5;margin-bottom:.75rem;text-align:center;" id="contractDropZone" onclick="document.getElementById('contractInput').click()" ondragover="event.preventDefault();this.style.borderColor='#dc2626';this.style.background='#fee2e2'" ondragleave="this.style.borderColor='#fca5a5';this.style.background='#fef2f2'" ondrop="event.preventDefault();document.getElementById('contractInput').files=event.dataTransfer.files;showContractName();">
                    <i class="fas fa-file-signature" style="font-size:1.5rem;color:#dc2626;display:block;margin-bottom:.3rem;" id="contractIcon"></i>
                    <div style="font-size:.85rem;font-weight:600;color:#991b1b;" id="contractText">Upload signed contract (PDF/JPG/PNG)</div>
                    <div style="font-size:.72rem;color:#94a3b8;margin-top:.15rem;" id="contractHint">Download the contract above, sign it, then upload here</div>
                </div>
                <input type="file" name="documents[signed_contract]" id="contractInput" required accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="showContractName()">
                @php $contractDoc = $documents->where('document_type', 'signed_contract')->first(); @endphp
                @if($contractDoc)
                <div style="font-size:.78rem;color:#16a34a;margin-bottom:.5rem;"><i class="fas fa-check-circle"></i> Previously uploaded: <a href="{{ asset('storage/'.$contractDoc->file_path) }}" target="_blank" style="color:#16a34a;font-weight:600;">{{ $contractDoc->document_name }}</a></div>
                @endif
                @error('documents.signed_contract')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror

                <hr style="border:none;border-top:2px solid #e8ecf1;margin:1.5rem 0;">

                {{-- Additional Documents --}}
                <div style="font-size:.9rem;font-weight:700;color:#0d1b2a;margin-bottom:.75rem;"><i class="fas fa-folder-open" style="margin-right:.4rem;color:#e8a838;"></i> Additional Documents</div>
                <div style="padding:.75rem;background:#f8fafc;border-radius:8px;border:1px dashed #d1d5db;">
                    <input type="file" name="documents[other][]" multiple accept=".pdf,.jpg,.jpeg,.png" style="font-size:.82rem;">
                    <div style="font-size:.72rem;color:#64748b;margin-top:.3rem;">PAN Card, Company Registration, etc. (PDF/JPG/PNG, max 10MB each)</div>
                </div>

                {{-- Previously Uploaded --}}
                @if($documents->count() > 0)
                <div style="margin-top:.75rem;">
                    <div style="font-size:.78rem;font-weight:700;color:#64748b;margin-bottom:.4rem;">Previously Uploaded ({{ $documents->count() }}):</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;">
                        @foreach($documents as $doc)
                        <div style="display:flex;align-items:center;gap:.4rem;padding:.35rem .6rem;background:{{ $doc->status==='verified'?'#f0fdf4':($doc->status==='rejected'?'#fef2f2':'#f8fafc') }};border-radius:6px;border:1px solid {{ $doc->status==='verified'?'#bbf7d0':($doc->status==='rejected'?'#fecaca':'#e8ecf1') }};">
                            <i class="fas {{ str_contains($doc->file_path ?? '', '.pdf') ? 'fa-file-pdf' : 'fa-file-image' }}" style="color:{{ $doc->status==='verified'?'#16a34a':($doc->status==='rejected'?'#dc2626':'#94a3b8') }};font-size:.75rem;"></i>
                            <div style="flex:1;min-width:0;">
                                <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" style="font-size:.72rem;color:#1e40af;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;">{{ $doc->document_name }}</a>
                                <div style="font-size:.6rem;color:#94a3b8;">{{ ucfirst(str_replace('_',' ',$doc->document_type ?? 'other')) }}</div>
                            </div>
                            <span class="badge {{ $doc->status==='verified'?'badge-success':($doc->status==='rejected'?'badge-danger':'badge-gray') }}" style="font-size:.5rem;">{{ ucfirst($doc->status ?? 'pending') }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>
        </div>

        <div style="display:flex;gap:.5rem;justify-content:flex-end;">
            <a href="{{ route('vendor.dashboard') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary" onclick="return validateKyc()"><i class="fas fa-paper-plane" style="margin-right:.3rem;"></i> Submit Registration</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function showContractName() {
    var input = document.getElementById('contractInput');
    if (input.files.length > 0) {
        document.getElementById('contractIcon').className = 'fas fa-check-circle';
        document.getElementById('contractIcon').style.color = '#16a34a';
        document.getElementById('contractText').textContent = input.files[0].name;
        document.getElementById('contractText').style.color = '#16a34a';
        document.getElementById('contractHint').textContent = (input.files[0].size / 1024 / 1024).toFixed(2) + ' MB';
        document.getElementById('contractDropZone').style.borderColor = '#16a34a';
        document.getElementById('contractDropZone').style.background = '#f0fdf4';
    }
}
function validateKyc() {
    var contract = document.getElementById('contractInput');
    if (!contract.files.length && !{{ $contractDoc ? 'true' : 'false' }}) {
        alert('Please upload the signed contract. Download it first, sign it, then upload.');
        return false;
    }
    return confirm('Submit KYC registration for Finance review?');
}
</script>
@endpush
@endsection
