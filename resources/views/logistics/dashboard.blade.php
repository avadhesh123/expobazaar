@extends('layouts.app')
@section('title', 'Logistics Dashboard')
@section('page-title', 'Logistics Dashboard')

@section('content')
{{-- KPIs --}}
<div class="grid-kpi">
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Containers Planned</div><div class="kpi-value">{{ $data['kpis']['containers_planned'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-cubes"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">In Transit</div><div class="kpi-value" style="color:#e8a838;">{{ $data['kpis']['in_transit'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-ship"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">GRN Pending</div><div class="kpi-value" style="color:#dc2626;">{{ $data['kpis']['grn_pending'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-clipboard-check"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Received This Month</div><div class="kpi-value" style="color:#166534;">{{ $data['kpis']['received_this_month'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-check-circle"></i></div></div></div>
</div>

<div class="grid-2">
    {{-- Inventory Ageing --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-clock" style="margin-right:.5rem;color:#dc2626;"></i> Inventory Ageing</h3><a href="{{ route('logistics.inventory.ageing') }}" class="btn btn-outline btn-sm">Details</a></div>
        <div class="card-body">
            @php $ageing = $data['inventory_ageing'] ?? []; $total = array_sum($ageing) ?: 1; @endphp
            @foreach(['0_30'=>['0-30 Days','#16a34a'],'31_60'=>['31-60 Days','#e8a838'],'61_90'=>['61-90 Days','#f97316'],'91_120'=>['91-120 Days','#dc2626'],'120_plus'=>['120+ Days','#991b1b']] as $key=>[$label,$color])
            @php $val = $ageing[$key] ?? 0; $pct = round(($val/$total)*100); @endphp
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.6rem;">
                <span style="width:90px;font-size:.78rem;color:#334155;font-weight:500;">{{ $label }}</span>
                <div style="flex:1;height:20px;background:#f1f5f9;border-radius:4px;overflow:hidden;">
                    <div style="height:100%;width:{{ $pct }}%;background:{{ $color }};border-radius:4px;transition:width .3s;min-width:{{ $val>0?'2px':'0' }};"></div>
                </div>
                <span style="width:70px;text-align:right;font-size:.82rem;font-weight:700;color:{{ $color }};">{{ number_format($val) }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Recent GRNs --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-clipboard-check" style="margin-right:.5rem;color:#2d6a4f;"></i> Recent GRNs</h3><a href="{{ route('logistics.grn') }}" class="btn btn-outline btn-sm">View All</a></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>GRN #</th><th>Shipment</th><th>Warehouse</th><th>Ageing</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($data['recent_grns'] ?? [] as $grn)
                    <tr>
                        <td style="font-weight:600;font-family:monospace;font-size:.8rem;">{{ $grn->grn_number }}</td>
                        <td style="font-size:.8rem;">{{ $grn->shipment->shipment_code ?? '—' }}</td>
                        <td style="font-size:.8rem;">{{ $grn->warehouse->name ?? '—' }}</td>
                        <td>
                            @php $days = $grn->getAgeingDays(); @endphp
                            <span style="font-weight:700;color:{{ $days>90?'#dc2626':($days>60?'#f97316':($days>30?'#e8a838':'#16a34a')) }};">{{ $days }}d</span>
                        </td>
                        <td><span class="badge {{ $grn->status==='completed'?'badge-success':($grn->status==='verified'?'badge-info':'badge-warning') }}">{{ ucfirst($grn->status) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:1.5rem;">No GRNs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-bolt" style="margin-right:.5rem;color:#e8a838;"></i> Quick Actions</h3></div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <a href="{{ route('logistics.container-planning') }}" class="btn btn-outline"><i class="fas fa-cubes"></i> Container Planning</a>
        <a href="{{ route('logistics.shipments') }}" class="btn btn-outline"><i class="fas fa-ship"></i> Shipments</a>
        <a href="{{ route('logistics.grn') }}" class="btn btn-outline"><i class="fas fa-clipboard-check"></i> GRN Management</a>
        <a href="{{ route('logistics.inventory') }}" class="btn btn-outline"><i class="fas fa-boxes"></i> Inventory</a>
        <a href="{{ route('logistics.inventory.ageing') }}" class="btn btn-outline"><i class="fas fa-clock"></i> Inventory Ageing</a>
        <a href="{{ route('logistics.inventory.allocation') }}" class="btn btn-outline"><i class="fas fa-warehouse"></i> Warehouse Allocation</a>
        <a href="{{ route('logistics.warehouse-charges') }}" class="btn btn-outline"><i class="fas fa-calculator"></i> Warehouse Charges</a>
        <a href="{{ route('logistics.inventory.download', request()->query()) }}" class="btn btn-outline"><i class="fas fa-download"></i> Download Inventory</a>
    </div>
</div>
@endsection
