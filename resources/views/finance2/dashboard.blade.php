@extends('layouts.app')
@section('title', 'Finance Dashboard')
@section('page-title', 'Finance Dashboard')

@section('content')
{{-- KPIs --}}
<div class="grid-kpi">
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Marketplace Receivables</div><div class="kpi-value" style="color:#dc2626;">${{ number_format($data['kpis']['receivables'] ?? 0, 0) }}</div></div>
            <div class="kpi-icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-hand-holding-usd"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Vendor Payouts Pending</div><div class="kpi-value" style="color:#e8a838;">${{ number_format($data['kpis']['payouts_pending'] ?? 0, 0) }}</div></div>
            <div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-money-check-alt"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Platform Deductions (Month)</div><div class="kpi-value" style="color:#1e40af;">${{ number_format($data['kpis']['platform_deductions'] ?? 0, 0) }}</div></div>
            <div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-percentage"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Chargebacks (Month)</div><div class="kpi-value" style="color:#991b1b;">${{ number_format($data['kpis']['chargebacks'] ?? 0, 0) }}</div></div>
            <div class="kpi-icon" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
</div>

<div class="grid-2">
    {{-- Unpaid by Platform --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-store" style="margin-right:.5rem;color:#1e3a5f;"></i> Receivables by Platform</h3></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Platform</th><th>Orders</th><th>Amount Due</th></tr></thead>
                <tbody>
                    @forelse($data['unpaid_by_platform'] ?? [] as $item)
                    <tr>
                        <td style="font-weight:600;">{{ $item->salesChannel->name ?? 'Unknown' }}</td>
                        <td>{{ number_format($item->count) }}</td>
                        <td style="font-weight:700;color:#dc2626;">${{ number_format($item->total, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:1.5rem;">No outstanding receivables.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Vendor Settlements This Month --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-money-check-alt" style="margin-right:.5rem;color:#2d6a4f;"></i> Vendor Settlements (This Month)</h3></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Vendor</th><th>Net Payout</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($data['vendor_settlements'] ?? [] as $payout)
                    <tr>
                        <td>
                            <div style="font-weight:600;">{{ $payout->vendor->company_name ?? '—' }}</div>
                            <div style="font-size:.68rem;color:#94a3b8;">{{ $payout->vendor->vendor_code ?? '' }}</div>
                        </td>
                        <td style="font-weight:700;">${{ number_format($payout->net_payout, 2) }}</td>
                        <td>
                            @php $sc = ['draft'=>'badge-gray','calculated'=>'badge-info','approved'=>'badge-info','payment_pending'=>'badge-warning','paid'=>'badge-success','invoice_received'=>'badge-success']; @endphp
                            <span class="badge {{ $sc[$payout->status] ?? 'badge-gray' }}">{{ str_replace('_',' ',ucfirst($payout->status)) }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:1.5rem;">No settlements this month.</td></tr>
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
        <a href="{{ route('finance.kyc') }}" class="btn btn-outline"><i class="fas fa-id-card"></i> KYC Review</a>
        <a href="{{ route('finance.receivables') }}" class="btn btn-outline"><i class="fas fa-hand-holding-usd"></i> Receivables</a>
        <a href="{{ route('finance.chargebacks') }}" class="btn btn-outline"><i class="fas fa-exclamation-triangle"></i> Chargebacks</a>
        <a href="{{ route('finance.payouts') }}" class="btn btn-outline"><i class="fas fa-money-check-alt"></i> Vendor Payouts</a>
        <a href="{{ route('finance.pricing-review') }}" class="btn btn-outline"><i class="fas fa-file-invoice-dollar"></i> Pricing Review</a>
    </div>
</div>
@endsection
