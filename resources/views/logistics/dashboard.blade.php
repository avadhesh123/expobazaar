@extends('layouts.app')
@section('title', 'Logistics Dashboard')
@section('page-title', 'Logistics Dashboard')

@section('content')
{{-- KPIs --}}
<div class="grid-kpi">
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">Containers Planned</div>
                <div class="kpi-value">{{ $data['kpis']['containers_planned'] ?? 0 }}</div>
            </div>
            <div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-cubes"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">In Transit</div>
                <div class="kpi-value" style="color:#e8a838;">{{ $data['kpis']['in_transit'] ?? 0 }}</div>
            </div>
            <div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-ship"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">GRN Pending</div>
                <div class="kpi-value" style="color:#dc2626;">{{ $data['kpis']['grn_pending'] ?? 0 }}</div>
            </div>
            <div class="kpi-icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-clipboard-check"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">Received This Month</div>
                <div class="kpi-value" style="color:#166534;">{{ $data['kpis']['received_this_month'] ?? 0 }}</div>
            </div>
            <div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
</div>

<div class="grid-2">
    {{-- Inventory Ageing --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock" style="margin-right:.5rem;color:#dc2626;"></i> Inventory Ageing</h3><a href="{{ route('logistics.inventory.ageing') }}" class="btn btn-outline btn-sm">Details</a>
        </div>
        <div class="card-body">
         <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0;border-bottom:1px solid #e2e8f0;">
        @php
            $bucketColors = ['0-30'=>'#16a34a','31-60'=>'#1e40af','61-90'=>'#e8a838','91-180'=>'#dc2626','180+'=>'#7c3aed'];
            $bucketLabels = ['0-30'=>'0-30 Days','31-60'=>'31-60 Days','61-90'=>'61-90 Days','91-180'=>'91-180 Days','180+'=>'180+ Days'];
        @endphp
        @foreach($data['inventory_ageing']['buckets'] as $bucket => $count)
        <div style="padding:.75rem;text-align:center;border-right:1px solid #e2e8f0;">
            <div style="font-size:.65rem;font-weight:600;color:#64748b;">{{ $bucketLabels[$bucket] }}</div>
            <div style="font-size:1.1rem;font-weight:800;color:{{ $bucketColors[$bucket] }};">{{ $count }}</div>
            <div style="font-size:.6rem;color:#94a3b8;">{{ $data['inventory_ageing']['qty_buckets'][$bucket] }} units</div>
        </div>
        @endforeach
    </div>
        </div>
    </div>

    {{-- Recent GRNs --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-check" style="margin-right:.5rem;color:#2d6a4f;"></i> Recent GRNs</h3><a href="{{ route('logistics.grn') }}" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>GRN #</th>
                        <th>Shipment</th>
                        <th>Warehouse</th>
                        <th>Ageing</th>
                        <th>Status</th>
                    </tr>
                </thead>
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
                    <tr>
                        <td colspan="5" style="text-align:center;color:#94a3b8;padding:1.5rem;">No GRNs yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-bolt" style="margin-right:.5rem;color:#e8a838;"></i> Quick Actions</h3>
    </div>
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