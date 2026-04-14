@extends('layouts.app')
@section('title', 'Create Vendor Request')
@section('page-title', 'Create Vendor Request')

@section('content')
<div style="max-width:680px;">
    {{-- Process indicator --}}
    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.25rem;padding:.85rem 1.2rem;background:#eff6ff;border-radius:10px;border:1px solid #bfdbfe;">
        <i class="fas fa-info-circle" style="color:#1e40af;"></i>
        <span style="font-size:.82rem;color:#1e40af;font-weight:500;">Step 1: Create vendor request → Admin will approve → Vendor receives email to set up account & KYC.</span>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-plus" style="margin-right:.5rem;color:#e8a838;"></i> New Vendor Onboarding Request</h3>
            <a href="{{ route('sourcing.vendors') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('sourcing.vendors.store') }}">
                @csrf

                <div class="form-group">
                    <label>Company Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="company_name" value="{{ old('company_name') }}" required placeholder="e.g. Rajasthan Handicrafts Pvt Ltd">
                    @error('company_name')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Contact Person <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="contact_person" value="{{ old('contact_person') }}" required placeholder="Full name">
                        @error('contact_person')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Email Address <span style="color:#dc2626;">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required placeholder="vendor@company.com">
                        @error('email')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                        <span style="font-size:.68rem;color:#94a3b8;">This email will be used for vendor login</span>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+91 98765 43210">
                    </div>
                    <div class="form-group">
                        <label>Company Code <span style="color:#dc2626;">*</span></label>
                        <select name="company_code" required>
                            <option value="">Select company...</option>
                            <option value="2000" {{ old('company_code')==='2000'?'selected':'' }}>2000 – India (Expo Digital India Pvt Ltd)</option>
                            <option value="2100" {{ old('company_code')==='2100'?'selected':'' }}>2100 – USA (Expo Digital SCM Inc.)</option>
                            <option value="2200" {{ old('company_code')==='2200'?'selected':'' }}>2200 – Netherlands (Expo Digital SCM B.V.)</option>
                        </select>
                        @error('company_code')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                        <span style="font-size:.68rem;color:#94a3b8;">Vendor account will be specific to this company code</span>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" value="{{ old('city') }}" placeholder="City">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country" value="{{ old('country', 'India') }}" placeholder="Country">
                    </div>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="2" placeholder="Full address...">{{ old('address') }}</textarea>
                </div>

                <hr style="border:none;border-top:1px solid #e8ecf1;margin:1.5rem 0;">

                {{-- What happens next --}}
                <div style="padding:.85rem;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;margin-bottom:1.25rem;">
                    <div style="font-size:.78rem;font-weight:700;color:#166534;margin-bottom:.3rem;">What happens next:</div>
                    <ol style="font-size:.75rem;color:#166534;padding-left:1.2rem;line-height:1.8;">
                        <li>This request goes to Admin for approval</li>
                        <li>On approval, vendor gets email to create account</li>
                        <li>Vendor uploads KYC → Finance reviews</li>
                        <li>Contract sent via DocuSign → Vendor panel activated</li>
                    </ol>
                </div>

                <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                    <a href="{{ route('sourcing.vendors') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane" style="margin-right:.3rem;"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
