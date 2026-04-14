@extends('layouts.app')
@section('title', 'Vendor Dashboard')
@section('page-title', 'Vendor Dashboard')

@section('content')
{{-- Onboarding Status --}}
@if($vendor->status !== 'active')
<div style="padding:.85rem 1.2rem;background:#fef3c7;border-radius:10px;border:1px solid #fde68a;margin-bottom:1.25rem;">
    <div style="font-size:.85rem;font-weight:700;color:#92400e;margin-bottom:.4rem;">Account Setup Progress</div>
    <div style="display:flex;gap:.5rem;align-items:center;">
        @php
    $steps = [
        ['Account Created', 'fas fa-user-check', true],
        ['KYC Submitted',   'fas fa-id-card',     in_array($vendor->kyc_status ?? '', ['submitted', 'approved'])],
        ['KYC Approved',    'fas fa-check-circle', $vendor->kyc_status === 'approved'],
        ['Contract Signed', 'fas fa-file-signature', in_array($vendor->contract_status ?? '', ['signed', 'sent'])],
        ['Active',          'fas fa-store',       !in_array($vendor->status ?? '', ['pending_kyc', 'pending_approval'])],
    ];
@endphp
@foreach($steps as $index => [$label, $icon, $done])
        <div style="display:flex;align-items:center;gap:.3rem;padding:.3rem .6rem;background:{{ $done?'#dcfce7':'#f1f5f9' }};border-radius:6px;">
            <i class="{{ $icon }}" style="color:{{ $done?'#16a34a':'#94a3b8' }};font-size:.7rem;"></i>
            <span style="font-size:.72rem;font-weight:600;color:{{ $done?'#166534':'#94a3b8' }};">{{ $label }}</span>
        </div>
        @if(!$loop->last)<i class="fas fa-arrow-right" style="color:#d1d5db;font-size:.5rem;"></i>@endif
        @endforeach
    </div>
    @if($vendor->kyc_status === 'pending')<a href="{{ route('vendor.kyc') }}" class="btn btn-secondary btn-sm" style="margin-top:.5rem;"><i class="fas fa-upload"></i> Submit KYC Documents</a>@endif
</div>
@endif

{{-- KPIs --}}
<div class="grid-kpi">
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;"><div><div class="kpi-label">Offer Sheets</div><div class="kpi-value">{{ $data['stats']['offer_sheets'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-file-alt"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;"><div><div class="kpi-label">Consignments</div><div class="kpi-value">{{ $data['stats']['consignments'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-box"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;"><div><div class="kpi-label">Total Sales</div><div class="kpi-value" style="color:#166534;font-size:1.3rem;">${{ number_format($data['stats']['total_sales'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-chart-line"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;"><div><div class="kpi-label">Pending Payout</div><div class="kpi-value" style="color:#e8a838;">${{ number_format($data['stats']['pending_payout'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-money-check-alt"></i></div></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-receipt" style="margin-right:.5rem;color:#1e3a5f;"></i> Recent Sales</h3><a href="{{ route('vendor.sales') }}" class="btn btn-outline btn-sm">View All</a></div>
        <div class="card-body" style="padding:0;"><table class="data-table"><thead><tr><th>Order</th><th>Platform</th><th>Amount</th><th>Date</th></tr></thead><tbody>
            @forelse($data['recent_orders'] ?? [] as $o)
            <tr><td style="font-weight:600;font-family:monospace;font-size:.8rem;">{{ $o->order_number }}</td><td><span class="badge badge-info">{{ $o->salesChannel->name ?? '—' }}</span></td><td style="font-family:monospace;font-weight:600;">${{ number_format($o->total_amount,2) }}</td><td style="font-size:.82rem;">{{ $o->order_date->format('d M Y') }}</td></tr>
            @empty<tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem;">No sales yet.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-box" style="margin-right:.5rem;color:#2d6a4f;"></i> Active Consignments</h3><a href="{{ route('vendor.consignments') }}" class="btn btn-outline btn-sm">View All</a></div>
        <div class="card-body" style="padding:0;"><table class="data-table"><thead><tr><th>Consignment</th><th>Items</th><th>Live Sheet</th><th>Status</th></tr></thead><tbody>
            @forelse($data['active_consignments'] ?? [] as $c)
            <tr><td style="font-weight:600;font-family:monospace;font-size:.8rem;">{{ $c->consignment_number }}</td><td style="text-align:center;">{{ $c->total_items }}</td><td>@if($c->liveSheet)<span class="badge {{ $c->liveSheet->is_locked?'badge-success':($c->liveSheet->status==='submitted'?'badge-warning':'badge-gray') }}">{{ $c->liveSheet->is_locked?'Locked':ucfirst($c->liveSheet->status) }}</span>@else<span class="badge badge-gray">Pending</span>@endif</td><td><span class="badge badge-info">{{ ucfirst(str_replace('_',' ',$c->status)) }}</span></td></tr>
            @empty<tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem;">No active consignments.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</div>

<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-bolt" style="margin-right:.5rem;color:#e8a838;"></i> Quick Actions</h3></div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <a href="{{ route('vendor.offer-sheets') }}" class="btn btn-outline"><i class="fas fa-file-alt"></i> Offer Sheets</a>
        <a href="{{ route('vendor.consignments') }}" class="btn btn-outline"><i class="fas fa-box"></i> Consignments</a>
        <a href="{{ route('vendor.live-sheets') }}" class="btn btn-outline"><i class="fas fa-clipboard-list"></i> Live Sheets</a>
        <a href="{{ route('vendor.sales') }}" class="btn btn-outline"><i class="fas fa-chart-line"></i> Sales</a>
        <a href="{{ route('vendor.chargebacks') }}" class="btn btn-outline"><i class="fas fa-exclamation-triangle"></i> Chargebacks</a>
        <a href="{{ route('vendor.payouts') }}" class="btn btn-outline"><i class="fas fa-money-check-alt"></i> Payouts</a>
    </div>
</div>
@endsection
