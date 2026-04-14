@extends('layouts.app')
@section('title', 'Vendors')
@section('page-title', 'Vendor Management')

@section('content')
{{-- FILTERS --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('sourcing.vendors') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="min-width:140px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All Statuses</option>
                    @foreach(['pending_approval','pending_kyc','pending_contract','active','inactive','suspended'] as $s)
                        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:120px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label>
                <select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000 – India</option>
                    <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100 – USA</option>
                    <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200 – NL</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('sourcing.vendors') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('sourcing.vendors.create') }}" class="btn btn-primary btn-sm" style="margin-left:auto;"><i class="fas fa-user-plus"></i> New Vendor Request</a>
        </form>
    </div>
</div>

{{-- VENDORS TABLE --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-users" style="margin-right:.5rem;color:#1e3a5f;"></i> Vendors</h3><span style="font-size:.78rem;color:#64748b;">{{ $vendors->total() }} total</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Vendor</th><th>Company</th><th>Contact</th><th>Onboarding Progress</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($vendors as $v)
                <tr>
                    <td>
                        <div style="font-weight:600;color:#0d1b2a;">{{ $v->company_name }}</div>
                        <div style="font-size:.7rem;color:#94a3b8;font-family:monospace;">{{ $v->vendor_code }}</div>
                    </td>
                    <td>
                        @php $cc = ['2000'=>['🇮🇳','#dcfce7'],'2100'=>['🇺🇸','#dbeafe'],'2200'=>['🇳🇱','#fef3c7']]; @endphp
                        <span style="padding:.2rem .45rem;background:{{ $cc[$v->company_code][1]??'#f1f5f9' }};border-radius:5px;font-size:.78rem;font-weight:600;">{{ $cc[$v->company_code][0]??'' }} {{ $v->company_code }}</span>
                    </td>
                    <td>
                        <div style="font-size:.82rem;">{{ $v->contact_person }}</div>
                        <div style="font-size:.7rem;color:#64748b;">{{ $v->email }}</div>
                    </td>
                    <td>
                        {{-- Onboarding progress bar --}}
                        @php
                            $steps = [
                                ['label'=>'Approval','done'=>!in_array($v->status,['pending_approval'])],
                                ['label'=>'KYC','done'=>in_array($v->kyc_status,['approved'])],
                                ['label'=>'Contract','done'=>$v->contract_status==='signed'],
                                ['label'=>'Active','done'=>$v->status==='active'],
                            ];
                            $completed = collect($steps)->where('done',true)->count();
                        @endphp
                        <div style="display:flex;gap:.2rem;align-items:center;margin-bottom:.3rem;">
                            @foreach($steps as $step)
                                <div title="{{ $step['label'] }}" style="width:28px;height:4px;border-radius:2px;background:{{ $step['done']?'#16a34a':'#e2e8f0' }};"></div>
                            @endforeach
                            <span style="font-size:.65rem;color:#64748b;margin-left:.3rem;">{{ $completed }}/4</span>
                        </div>
                        <div style="display:flex;gap:.2rem;flex-wrap:wrap;">
                            <span class="badge {{ $v->kyc_status==='approved'?'badge-success':($v->kyc_status==='submitted'?'badge-warning':($v->kyc_status==='rejected'?'badge-danger':'badge-gray')) }}" style="font-size:.55rem;">KYC: {{ ucfirst($v->kyc_status) }}</span>
                            <span class="badge {{ $v->contract_status==='signed'?'badge-success':($v->contract_status==='sent'?'badge-info':'badge-gray') }}" style="font-size:.55rem;">Contract: {{ ucfirst($v->contract_status) }}</span>
                        </div>
                    </td>
                    <td>
                        @php $sc = ['pending_approval'=>'badge-warning','pending_kyc'=>'badge-info','pending_contract'=>'badge-info','active'=>'badge-success','inactive'=>'badge-gray','suspended'=>'badge-danger']; @endphp
                        <span class="badge {{ $sc[$v->status]??'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$v->status)) }}</span>
                    </td>
                    <td>
                        <div style="font-size:.82rem;">{{ $v->created_at->format('d M Y') }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $v->created_at->diffForHumans() }}</div>
                    </td>
                    <td><a href="{{ route('sourcing.vendors.show', $v) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</a></td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No vendors found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($vendors->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $vendors->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection