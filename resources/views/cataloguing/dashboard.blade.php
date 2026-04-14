@extends('layouts.app')
@section('title', 'Cataloguing Dashboard')
@section('page-title', 'Cataloguing Dashboard')

@section('content')
<div class="grid-kpi">
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Total SKUs</div><div class="kpi-value">{{ $data['listing_summary']['total_skus'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-box"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Listed</div><div class="kpi-value" style="color:#16a34a;">{{ $data['listing_summary']['listed'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#dcfce7;color:#16a34a;"><i class="fas fa-check-circle"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Pending Listing</div><div class="kpi-value" style="color:#e8a838;">{{ $data['listing_summary']['pending'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-clock"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Pricing Ready</div><div class="kpi-value" style="color:#7c3aed;">{{ $data['listing_summary']['pricing_ready'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-tags"></i></div></div></div>
</div>

{{-- Per Channel Listing Status --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-store" style="margin-right:.5rem;color:#e8a838;"></i> Listings by Platform</h3><a href="{{ route('cataloguing.sku-dashboard') }}" class="btn btn-outline btn-sm">Full Dashboard</a></div>
    <div class="card-body">
        @foreach($data['by_channel'] ?? [] as $item)
        @php $ch = $item['channel']; $listed = $item['listed']; $total = $item['total'] ?: 1; $pct = round(($listed / $total) * 100); @endphp
        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.65rem;">
            <span style="width:100px;font-size:.82rem;font-weight:600;color:#334155;">{{ $ch->name }}</span>
            <div style="flex:1;height:22px;background:#f1f5f9;border-radius:5px;overflow:hidden;">
                <div style="height:100%;width:{{ $pct }}%;background:linear-gradient(90deg,#16a34a,#22c55e);border-radius:5px;min-width:{{ $listed > 0 ? '3px' : '0' }};transition:width .3s;"></div>
            </div>
            <span style="width:80px;text-align:right;font-size:.82rem;font-weight:700;color:#166534;">{{ $listed }} / {{ $item['total'] }}</span>
            <span style="width:40px;text-align:right;font-size:.72rem;color:#64748b;">{{ $pct }}%</span>
        </div>
        @endforeach
    </div>
</div>

{{-- Quick Actions --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-bolt" style="margin-right:.5rem;color:#e8a838;"></i> Quick Actions</h3></div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <a href="{{ route('cataloguing.pricing-sheets') }}" class="btn btn-outline"><i class="fas fa-file-invoice-dollar"></i> Pricing Sheets</a>
        <a href="{{ route('cataloguing.listing-panel') }}" class="btn btn-outline"><i class="fas fa-list"></i> Listing Panel</a>
        <a href="{{ route('cataloguing.sku-dashboard') }}" class="btn btn-outline"><i class="fas fa-chart-bar"></i> SKU Dashboard</a>
        <a href="{{ route('cataloguing.pricing-sheets.download', request()->query()) }}" class="btn btn-outline"><i class="fas fa-download"></i> Download Pricing CSV</a>
    </div>
</div>
@endsection
