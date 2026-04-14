@extends('layouts.app')
@section('title', 'KYC Registration')
@section('page-title', 'Customer / Vendor Registration Form')

@section('content')
@php
    $isApproved = $vendor && $vendor->kyc_status === 'approved';
    $isLocked = $isApproved;
    $disabled = $isLocked ? 'disabled' : '';
@endphp

{{-- Status Banners --}}
@if($isApproved)
<div style="padding:.85rem 1.2rem;background:#dcfce7;border-radius:10px;border:1px solid #bbf7d0;margin-bottom:1.25rem;">
    <div style="font-size:.85rem;font-weight:700;color:#166534;"><i class="fas fa-lock" style="margin-right:.3rem;"></i> KYC Approved by Finance. No changes allowed.</div>
</div>
@elseif($vendor && $vendor->kyc_status === 'submitted')
<div style="padding:.85rem 1.2rem;background:#fef3c7;border-radius:10px;border:1px solid #fde68a;margin-bottom:1.25rem;">
    <div style="font-size:.85rem;font-weight:600;color:#92400e;"><i class="fas fa-clock" style="margin-right:.3rem;"></i> KYC submitted. Pending Finance team review.</div>
</div>
@elseif($vendor && $vendor->kyc_status === 'rejected')
<div style="padding:.85rem 1.2rem;background:#fee2e2;border-radius:10px;border:1px solid #fecaca;margin-bottom:1.25rem;">
    <div style="font-size:.85rem;font-weight:600;color:#dc2626;"><i class="fas fa-times-circle" style="margin-right:.3rem;"></i> KYC Rejected. Please re-submit with corrections.</div>
    @if($vendor->kyc_rejection_reason)<div style="font-size:.82rem;color:#991b1b;margin-top:.25rem;">Reason: {{ $vendor->kyc_rejection_reason }}</div>@endif
</div>
@endif

<div style="max-width:780px;">
    <form method="POST" action="{{ route('vendor.kyc.submit') }}" enctype="multipart/form-data" id="kycForm">
        @csrf

        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header" style="background:linear-gradient(135deg,#1e3a5f,#2d6a4f);border-radius:14px 14px 0 0;">
                <h3 style="color:#fff;"><i class="fas fa-id-card" style="margin-right:.5rem;"></i> Customer / Vendor Registration Form</h3>
            </div>
            <div class="card-body">

                {{-- 1. VAT/GST Number --}}
                <div style="display:flex;gap:1rem;align-items:flex-end;margin-bottom:1rem;">
                    <div style="flex:1;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>1. V.A.T Number / GST <span style="color:#dc2626;">*</span></label>
                            <input type="text" name="gst_number" value="{{ old('gst_number', $vendor->gst_number ?? '') }}" required placeholder="e.g. 22AAAAA0000A1Z5" {{ $disabled }}>
                            @error('gst_number')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div style="min-width:200px;">
                        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem;">GST Certificate <span style="color:#dc2626;">*</span></label>
                        @if(!$isLocked)<input type="file" name="documents[gst_certificate]" accept=".pdf,.jpg,.jpeg,.png" style="font-size:.78rem;">@endif
                        @php $gstDoc = $documents->where('document_type', 'gst_certificate')->first(); @endphp
                        @if($gstDoc)<div style="font-size:.68rem;color:#16a34a;margin-top:.2rem;"><i class="fas fa-check-circle"></i> <a href="{{ asset('storage/app/public/'.$gstDoc->file_path) }}" target="_blank" style="color:#16a34a;">Uploaded</a></div>@endif
                        @error('documents.gst_certificate')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                    </div>
                </div>

                {{-- 2. Vendor/Customer Name --}}
                <div class="form-group">
                    <label>2. Vendor / Customer Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="company_name" value="{{ old('company_name', $vendor->company_name ?? '') }}" required placeholder="Legal entity name" {{ $disabled }}>
                </div>

                {{-- 3. Registered Address --}}
                <div style="font-size:.82rem;font-weight:700;color:#0d1b2a;margin-bottom:.5rem;margin-top:.5rem;">3. Registered Address</div>
                <div class="form-group">
                    <label>Street Name & Number <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="street_address" value="{{ old('street_address', $vendor->street_address ?? '') }}" required placeholder="Street name and number" {{ $disabled }}>
                    @error('street_address')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>
                <div class="grid-2">
                    <div class="form-group"><label>City / Town <span style="color:#dc2626;">*</span></label><input type="text" name="city" value="{{ old('city', $vendor->city ?? '') }}" required placeholder="City or town" {{ $disabled }}>@error('city')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror</div>
                    <div class="form-group"><label>Province / State <span style="color:#dc2626;">*</span></label><input type="text" name="province_state" value="{{ old('province_state', $vendor->province_state ?? $vendor->state ?? '') }}" required placeholder="Province or state" {{ $disabled }}>@error('province_state')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror</div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label>Pin Code <span style="color:#dc2626;">*</span></label><input type="text" name="pincode" value="{{ old('pincode', $vendor->pincode ?? '') }}" required placeholder="Postal / Pin code" {{ $disabled }}>@error('pincode')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror</div>
                    <div class="form-group"><label>Country <span style="color:#dc2626;">*</span></label><input type="text" name="country" value="{{ old('country', $vendor->country ?? '') }}" required placeholder="Country" {{ $disabled }}>@error('country')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror</div>
                </div>

                {{-- 4. Contact Persons --}}
                <div class="grid-2">
                    <div class="form-group"><label>4. Contact Person <span style="color:#dc2626;">*</span></label><input type="text" name="contact_person" value="{{ old('contact_person', $vendor->contact_person ?? '') }}" required placeholder="Primary contact name" {{ $disabled }}>@error('contact_person')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror</div>
                    <div class="form-group"><label>Finance Contact Person</label><input type="text" name="finance_contact_person" value="{{ old('finance_contact_person', $vendor->finance_contact_person ?? '') }}" placeholder="Finance contact name" {{ $disabled }}></div>
                </div>

                {{-- 5. Mobile --}}
                <div class="form-group">
                    <label>5. Mobile No <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="phone" value="{{ old('phone', $vendor->phone ?? '') }}" required placeholder="+91 98765 43210" {{ $disabled }}>
                    @error('phone')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>

                {{-- 6. Email --}}
                <div class="form-group">
                    <label>6. Email I.D <span style="color:#dc2626;">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $vendor->email ?? auth()->user()->email) }}" required placeholder="vendor@company.com" {{ $disabled }}>
                    @error('email')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>

                {{-- 7. IEC Code --}}
                <div style="display:flex;gap:1rem;align-items:flex-end;margin-bottom:1rem;">
                    <div style="flex:1;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>7. IEC Code</label>
                            <input type="text" name="iec_code" value="{{ old('iec_code', $vendor->iec_code ?? '') }}" placeholder="Import Export Code" {{ $disabled }}>
                        </div>
                    </div>
                    <div style="min-width:200px;">
                        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem;">IEC Certificate</label>
                        @if(!$isLocked)<input type="file" name="documents[iec_certificate]" accept=".pdf,.jpg,.jpeg,.png" style="font-size:.78rem;">@endif
                        @php $iecDoc = $documents->where('document_type', 'iec_certificate')->first(); @endphp
                        @if($iecDoc)<div style="font-size:.68rem;color:#16a34a;margin-top:.2rem;"><i class="fas fa-check-circle"></i> Uploaded</div>@endif
                    </div>
                </div>

                {{-- 8. MSME --}}
                <div style="display:flex;gap:1rem;align-items:flex-end;margin-bottom:1rem;">
                    <div style="flex:1;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>8. MSME Number</label>
                            <input type="text" name="msme_number" value="{{ old('msme_number', $vendor->msme_number ?? '') }}" placeholder="MSME registration number" {{ $disabled }}>
                        </div>
                    </div>
                    <div style="min-width:200px;">
                        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem;">MSME Certificate</label>
                        @if(!$isLocked)<input type="file" name="documents[msme_certificate]" accept=".pdf,.jpg,.jpeg,.png" style="font-size:.78rem;">@endif
                        @php $msmeDoc = $documents->where('document_type', 'msme_certificate')->first(); @endphp
                        @if($msmeDoc)<div style="font-size:.68rem;color:#16a34a;margin-top:.2rem;"><i class="fas fa-check-circle"></i> Uploaded</div>@endif
                    </div>
                </div>

                {{-- 9. Landline --}}
                <div class="form-group"><label>9. Landline No</label><input type="text" name="landline" value="{{ old('landline', $vendor->landline ?? '') }}" placeholder="Landline with STD code" {{ $disabled }}></div>

                {{-- 10. Website --}}
                <div class="form-group"><label>10. Official Web Site</label><input type="url" name="official_website" value="{{ old('official_website', $vendor->official_website ?? '') }}" placeholder="https://www.company.com" {{ $disabled }}></div>

                <hr style="border:none;border-top:2px solid #e8ecf1;margin:1.5rem 0;">

                {{-- Bank Details --}}
                <div style="font-size:.9rem;font-weight:700;color:#0d1b2a;margin-bottom:.75rem;"><i class="fas fa-university" style="margin-right:.4rem;color:#1e3a5f;"></i> Bank Details</div>

                <div class="form-group"><label>11. Bank Name <span style="color:#dc2626;">*</span></label><input type="text" name="bank_name" value="{{ old('bank_name', $vendor->bank_name ?? '') }}" required placeholder="Bank name" {{ $disabled }}>@error('bank_name')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror</div>

                <div class="form-group"><label>12. IFSC Code <span style="color:#dc2626;">*</span></label><input type="text" name="bank_ifsc" value="{{ old('bank_ifsc', $vendor->bank_ifsc ?? '') }}" required placeholder="e.g. SBIN0001234" {{ $disabled }}>@error('bank_ifsc')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror</div>

                <div class="form-group"><label>13. SWIFT / BIC Code <span style="color:#dc2626;">*</span></label><input type="text" name="bank_swift_code" value="{{ old('bank_swift_code', $vendor->bank_swift_code ?? '') }}" required placeholder="SWIFT/BIC code (mandatory)" {{ $disabled }}>@error('bank_swift_code')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror</div>

                <div class="form-group"><label>14. Account Number <span style="color:#dc2626;">*</span></label><input type="text" name="bank_account_number" value="{{ old('bank_account_number', $vendor->bank_account_number ?? '') }}" required placeholder="Bank account number" {{ $disabled }}>@error('bank_account_number')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror</div>

                {{-- Cancelled Cheque — MANDATORY --}}
                <div style="margin-top:.5rem;padding:.75rem;background:#fef2f2;border-radius:8px;border:1px solid #fecaca;">
                    <label style="font-size:.78rem;font-weight:700;color:#991b1b;display:block;margin-bottom:.3rem;">Cancelled Cheque / Bank Proof <span style="color:#dc2626;">* (Mandatory)</span></label>
                    @if(!$isLocked)<input type="file" name="documents[cancelled_cheque]" accept=".pdf,.jpg,.jpeg,.png" style="font-size:.78rem;">@endif
                    @php $chequeDoc = $documents->where('document_type', 'cancelled_cheque')->first(); @endphp
                    @if($chequeDoc)<div style="font-size:.68rem;color:#16a34a;margin-top:.2rem;"><i class="fas fa-check-circle"></i> <a href="{{ asset('storage/app/public/'.$chequeDoc->file_path) }}" target="_blank" style="color:#16a34a;">Uploaded</a></div>@endif
                    @error('documents.cancelled_cheque')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>

                @if(!$isLocked)
                <hr style="border:none;border-top:2px solid #e8ecf1;margin:1.5rem 0;">
                <div style="font-size:.9rem;font-weight:700;color:#0d1b2a;margin-bottom:.75rem;"><i class="fas fa-folder-open" style="margin-right:.4rem;color:#e8a838;"></i> Additional Documents</div>
                <div style="padding:.75rem;background:#f8fafc;border-radius:8px;border:1px dashed #d1d5db;margin-bottom:.75rem;">
                    <input type="file" name="documents[other][]" multiple accept=".pdf,.jpg,.jpeg,.png" style="font-size:.82rem;">
                    <div style="font-size:.72rem;color:#64748b;margin-top:.3rem;">Upload additional documents (PAN Card, Company Registration, etc.). Max 10MB each.</div>
                </div>
                @endif

                {{-- Previously Uploaded Documents --}}
                @if($documents->count() > 0)
                <div style="margin-top:.75rem;">
                    <div style="font-size:.78rem;font-weight:700;color:#64748b;margin-bottom:.4rem;">Uploaded Documents:</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;">
                        @foreach($documents as $doc)
                        <div style="display:flex;align-items:center;gap:.4rem;padding:.4rem .6rem;background:#f8fafc;border-radius:6px;border:1px solid #e8ecf1;">
                            <i class="fas {{ str_contains($doc->file_path ?? '', '.pdf') ? 'fa-file-pdf' : 'fa-file-image' }}" style="color:#94a3b8;"></i>
                            <div style="flex:1;min-width:0;">
                                <a href="{{ asset('storage/app/public/' . $doc->file_path) }}" target="_blank" style="font-size:.75rem;color:#1e40af;text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $doc->document_name }}</a>
                                <div style="font-size:.62rem;color:#94a3b8;">{{ ucfirst(str_replace('_',' ',$doc->document_type ?? 'other')) }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Submit — only if not locked --}}
        @if(!$isLocked)
        <div style="display:flex;gap:.5rem;justify-content:flex-end;">
            <a href="{{ route('vendor.dashboard') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Submit KYC registration form for Finance review?')">
                <i class="fas fa-paper-plane" style="margin-right:.3rem;"></i> Submit Registration
            </button>
        </div>
        @endif
    </form>
</div>
@endsection