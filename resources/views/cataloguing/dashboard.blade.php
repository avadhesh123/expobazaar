@extends('layouts.app')
@section('title', 'Cataloguing Dashboard')
@section('page-title', 'Cataloguing Dashboard')

@section('content')
{{-- KPIs --}}
<div style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <div class="kpi-card" style="flex:1;border-left:3px solid #1e40af;"><div class="kpi-label">Total SKUs</div><div class="kpi-value" style="color:#1e40af;">{{ number_format($data['kpis']['total_skus']) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #16a34a;"><div class="kpi-label">Listed</div><div class="kpi-value" style="color:#16a34a;">{{ number_format($data['kpis']['listed']) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #e8a838;"><div class="kpi-label">Pending</div><div class="kpi-value" style="color:#e8a838;">{{ number_format($data['kpis']['pending']) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #dc2626;"><div class="kpi-label">Not Listed</div><div class="kpi-value" style="color:#dc2626;">{{ number_format($data['kpis']['not_listed']) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #7c3aed;"><div class="kpi-label">Last 7 Days</div><div class="kpi-value" style="color:#7c3aed;">+{{ number_format($data['kpis']['recently_listed']) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #0891b2;"><div class="kpi-label">Overall Coverage</div><div class="kpi-value" style="color:#0891b2;">{{ $data['kpis']['overall_coverage'] }}%</div></div>
</div>

{{-- Platform Coverage Cards --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-store" style="margin-right:.5rem;color:#e8a838;"></i> Channel Coverage</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead><tr style="background:#f0f4f8;">
                <th>Channel</th><th>Type</th>
                <th style="text-align:center;color:#16a34a;">Listed</th>
                <th style="text-align:center;color:#e8a838;">Pending</th>
                <!-- <th style="text-align:center;color:#dc2626;">Unlisted</th> -->
                <th style="text-align:center;color:#94a3b8;">Not Listed</th>
                <th style="min-width:150px;">Coverage</th>
            </tr></thead>
            <tbody>
                @foreach($data['by_platform'] as $slug => $ch)
                <tr>
                    <td style="font-weight:600;">{{ $ch['name'] }}</td>
                    <td><span class="badge {{ $ch['type'] === 'b2b' ? 'badge-info' : 'badge-warning' }}">{{ strtoupper($ch['type']) }}</span></td>
                    <td style="text-align:center;font-weight:700;color:#16a34a;">{{ $ch['listed'] }}</td>
                    <td style="text-align:center;font-weight:600;color:#e8a838;">{{ $ch['pending'] }}</td>
                    <!-- <td style="text-align:center;color:#dc2626;">{{ $ch['unlisted'] }}</td> -->
                    <td style="text-align:center;color:#94a3b8;">{{ $ch['not_listed'] }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;width:{{ $ch['coverage'] }}%;background:{{ $ch['coverage'] > 70 ? '#16a34a' : ($ch['coverage'] > 40 ? '#e8a838' : '#dc2626') }};border-radius:4px;"></div>
                            </div>
                            <span style="font-size:.72rem;font-weight:700;color:#334155;">{{ $ch['coverage'] }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Top Vendors --}}
@if(!empty($data['top_vendors']) && count($data['top_vendors']) > 0)
<div class="card">
    <div class="card-header"><h3><i class="fas fa-trophy" style="margin-right:.5rem;color:#e8a838;"></i> Top Vendors by Listed SKUs</h3></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table" style="font-size:.78rem;">
            <thead><tr style="background:#f0f4f8;"><th>#</th><th>Vendor</th><th style="text-align:center;">Listed SKUs</th></tr></thead>
            <tbody>
                @foreach($data['top_vendors'] as $idx => $v)
                <tr>
                    <td style="text-align:center;font-weight:700;color:#e8a838;">{{ $idx + 1 }}</td>
                    <td style="font-weight:600;">{{ $v['vendor_name'] }}</td>
                    <td style="text-align:center;font-weight:700;color:#16a34a;">{{ $v['count'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
{{-- Quick Actions --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-bolt" style="margin-right:.5rem;color:#e8a838;"></i> Quick Actions</h3></div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <a href="{{ route('cataloguing.pricing-sheets') }}" class="btn btn-outline"><i class="fas fa-file-invoice-dollar"></i> Pricing Sheets</a>
        <a href="{{ route('cataloguing.listing-panel') }}" class="btn btn-outline"><i class="fas fa-list"></i> Listing Panel</a>
        <a href="{{ route('cataloguing.pricing-sheets.download', request()->query()) }}" class="btn btn-outline"><i class="fas fa-download"></i> Download Pricing CSV</a>
    </div>
</div>
@endsection
