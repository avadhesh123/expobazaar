@extends('layouts.app')
@section('title', 'GRN Management')
@section('page-title', 'Goods Receipt Notes (GRN)')

@section('content')
{{-- Pending Shipments for GRN --}}
@if($pendingShipments->count() > 0)
<div class="card" style="margin-bottom:1.25rem;border-color:#fde68a;">
    <div class="card-header" style="background:#fffbeb;"><h3><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#e8a838;"></i> Shipments Pending GRN ({{ $pendingShipments->count() }})</h3></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Shipment</th><th>Type</th><th>Company</th><th>Vendors</th><th>CBM</th><th>Warehouse</th><th>Action</th></tr></thead>
            <tbody>
                @foreach($pendingShipments as $sh)
                <tr>
                    <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $sh->shipment_code }}</td>
                    <td><span class="badge badge-info">{{ $sh->shipment_type }}</span></td>
                    <td>{{ $sh->company_code }}</td>
                    <td style="font-size:.8rem;">{{ $sh->consignments->pluck('vendor.company_name')->unique()->implode(', ') }}</td>
                    <td style="font-family:monospace;">{{ number_format($sh->total_cbm, 2) }}</td>
                    <td style="font-size:.8rem;">{{ $sh->warehouse->name ?? '—' }}</td>
                    <td><a href="{{ route('logistics.grn.upload', $sh) }}" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload GRN</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('logistics.grn') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="min-width:120px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @foreach(['pending','uploaded','verified','completed'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach
                </select></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label>
                <select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option>
                </select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Warehouse</label>
                <select name="warehouse_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @foreach($warehouses as $wh)<option value="{{ $wh->id }}" {{ request('warehouse_id')==(string)$wh->id?'selected':'' }}>{{ $wh->name }}</option>@endforeach
                </select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('logistics.grn') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

{{-- GRN Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-clipboard-check" style="margin-right:.5rem;color:#2d6a4f;"></i> All GRNs</h3><span style="font-size:.78rem;color:#64748b;">{{ $grns->total() }} records</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>GRN Number</th><th>Shipment</th><th>Warehouse</th><th>Company</th><th>Date</th><th>Expected</th><th>Received</th><th>Damaged</th><th>Ageing</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($grns as $grn)
                <tr>
                    <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $grn->grn_number }}</td>
                    <td style="font-size:.8rem;">{{ $grn->shipment->shipment_code ?? '—' }}</td>
                    <td style="font-size:.8rem;">{{ $grn->warehouse->name ?? '—' }}</td>
                    <td>{{ $grn->company_code }}</td>
                    <td style="font-size:.8rem;">{{ $grn->receipt_date->format('d M Y') }}</td>
                    <td style="text-align:center;">{{ $grn->total_items_expected }}</td>
                    <td style="text-align:center;font-weight:600;color:#166534;">{{ $grn->total_items_received }}</td>
                    <td style="text-align:center;color:{{ $grn->damaged_items>0?'#dc2626':'#94a3b8' }};">{{ $grn->damaged_items }}</td>
                    <td>
                        @php $days = $grn->getAgeingDays(); @endphp
                        <span style="display:inline-flex;align-items:center;gap:.2rem;padding:.15rem .4rem;border-radius:5px;font-size:.75rem;font-weight:700;background:{{ $days>90?'#fee2e2':($days>60?'#fef3c7':($days>30?'#fefce8':'#dcfce7')) }};color:{{ $days>90?'#dc2626':($days>60?'#e8a838':($days>30?'#854d0e':'#166534')) }};">
                            {{ $days }}d
                        </span>
                    </td>
                    <td><span class="badge {{ $grn->status==='completed'?'badge-success':($grn->status==='verified'?'badge-info':'badge-warning') }}">{{ ucfirst($grn->status) }}</span></td>
                    <td><a href="{{ route('logistics.grn.show', $grn) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</a></td>
                </tr>
                @empty
                <tr><td colspan="11" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-clipboard-check" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No GRNs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($grns->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $grns->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
