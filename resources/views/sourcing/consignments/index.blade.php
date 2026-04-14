@extends('layouts.app')
@section('title', 'Consignments')
@section('page-title', 'Consignment Pipeline')

@section('content')
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('sourcing.consignments') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:160px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @foreach(['created','live_sheet_pending','live_sheet_submitted','live_sheet_approved','live_sheet_locked','in_production','ready_for_shipment','shipped','delivered'] as $s)
                        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:110px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label>
                <select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option>
                    <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option>
                    <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('sourcing.consignments') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-box" style="margin-right:.5rem;color:#2d6a4f;"></i> Consignments</h3><span style="font-size:.78rem;color:#64748b;">{{ $consignments->total() }} total</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Consignment #</th><th>Vendor</th><th>Company</th><th>Country</th><th>Items</th><th>CBM</th><th>Value</th><th>Live Sheet</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
                @forelse($consignments as $con)
                <tr>
                    <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $con->consignment_number }}</td>
                    <td>
                        <div style="font-size:.82rem;">{{ $con->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $con->vendor->vendor_code ?? '' }}</div>
                    </td>
                    <td>
                        @php $cc = ['2000'=>'🇮🇳','2100'=>'🇺🇸','2200'=>'🇳🇱']; @endphp
                        {{ $cc[$con->company_code]??'' }} {{ $con->company_code }}
                    </td>
                    <td style="font-weight:600;">{{ $con->destination_country }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $con->total_items }}</td>
                    <td style="font-family:monospace;">{{ number_format($con->total_cbm, 2) }}</td>
                    <td style="font-family:monospace;"> {{ ($con->company_code == '2200' ? '€' : ($con->company_code == '2100' ? '$' : '')) . number_format($con->total_value, 2) }}</td>
                    <td>
                        @if($con->liveSheet)
                            @if($con->liveSheet->is_locked)
                                <span class="badge badge-success"><i class="fas fa-lock" style="margin-right:.2rem;font-size:.55rem;"></i> Locked</span>
                                <div style="font-size:.65rem;color:#166534;margin-top:.15rem;">Ready for logistics</div>
                            @else
                                <span class="badge {{ $con->liveSheet->status==='submitted'?'badge-warning':'badge-gray' }}">{{ ucfirst($con->liveSheet->status) }}</span>
                            @endif
                        @else
                            <span class="badge badge-gray">Pending</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusColors = [
                                'created'=>'badge-gray','live_sheet_pending'=>'badge-gray','live_sheet_submitted'=>'badge-warning',
                                'live_sheet_approved'=>'badge-info','live_sheet_locked'=>'badge-success',
                                'in_production'=>'badge-info','ready_for_shipment'=>'badge-warning',
                                'shipped'=>'badge-success','delivered'=>'badge-success','cancelled'=>'badge-danger',
                            ];
                        @endphp
                        <span class="badge {{ $statusColors[$con->status]??'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$con->status)) }}</span>
                    </td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $con->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-box-open" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No consignments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($consignments->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $consignments->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection
