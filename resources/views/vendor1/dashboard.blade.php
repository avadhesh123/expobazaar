@extends('layouts.app')
@section('title', 'Vendor Dashboard')
@section('page-title', 'Vendor Dashboard – ' . ($vendor->company_name ?? ''))

@section('content')
<div class="grid-kpi">
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Products Approved</div><div class="kpi-value">{{ number_format($data['kpis']['products_approved'] ?? 0) }}</div></div><div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-check-circle"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Inventory Available</div><div class="kpi-value">{{ number_format($data['kpis']['inventory_available'] ?? 0) }}</div></div><div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-boxes"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Units Sold (Month)</div><div class="kpi-value">{{ number_format($data['kpis']['units_sold'] ?? 0) }}</div></div><div class="kpi-icon" style="background:#fef3c7;color:#92400e;"><i class="fas fa-shopping-cart"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Monthly Sales</div><div class="kpi-value">${{ number_format($data['kpis']['monthly_sales'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-chart-line"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Pending Payout</div><div class="kpi-value">${{ number_format($data['kpis']['pending_payout'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#e0e7ff;color:#3730a3;"><i class="fas fa-money-check-alt"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Chargebacks</div><div class="kpi-value">${{ number_format($data['kpis']['chargebacks'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-exclamation-triangle"></i></div></div></div>
</div>

<div class="grid-2">
    {{-- Product Status --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-box" style="margin-right:.5rem;color:#e8a838;"></i> Product Status</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8rem;">
                @foreach(['submitted' => ['Submitted','#64748b'], 'approved' => ['Approved','#166534'], 'rejected' => ['Rejected','#991b1b'], 'listed' => ['Listed','#1e40af']] as $key => [$label, $color])
                <div style="padding:.8rem;background:#f8fafc;border-radius:10px;border-left:3px solid {{ $color }};">
                    <div style="font-size:1.3rem;font-weight:800;color:{{ $color }};">{{ $data['product_status'][$key] ?? 0 }}</div>
                    <div style="font-size:.7rem;color:#64748b;font-weight:600;">{{ $label }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Charges Summary --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-calculator" style="margin-right:.5rem;color:#dc2626;"></i> Monthly Charges</h3></div>
        <div class="card-body">
            @foreach(['storage' => 'Storage Charges', 'inward' => 'Inward Charges', 'logistics' => 'Logistics Charges'] as $key => $label)
            <div style="display:flex;justify-content:space-between;padding:.65rem 0;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.82rem;color:#334155;">{{ $label }}</span>
                <span style="font-size:.85rem;font-weight:700;color:#0d1b2a;">${{ number_format($data['charges'][$key] ?? 0, 2) }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Recent Consignments --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-shipping-fast" style="margin-right:.5rem;color:#2d6a4f;"></i> Recent Consignments</h3><a href="{{ route('vendor.consignments') }}" class="btn btn-outline btn-sm">View All</a></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Consignment #</th><th>Country</th><th>Items</th><th>CBM</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @forelse(($data['consignments'] ?? collect()) as $con)
                <tr>
                    <td style="font-weight:600;">{{ $con->consignment_number }}</td>
                    <td>{{ $con->destination_country }}</td>
                    <td>{{ $con->total_items }}</td>
                    <td>{{ number_format($con->total_cbm, 2) }}</td>
                    <td><span class="badge {{ in_array($con->status, ['shipped','delivered']) ? 'badge-success' : (str_contains($con->status, 'pending') ? 'badge-warning' : 'badge-info') }}">{{ str_replace('_', ' ', $con->status) }}</span></td>
                    <td>{{ $con->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No consignments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Payouts --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-money-check-alt" style="margin-right:.5rem;color:#e8a838;"></i> Payout History</h3><a href="{{ route('vendor.payouts') }}" class="btn btn-outline btn-sm">View All</a></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Period</th><th>Sales</th><th>Deductions</th><th>Net Payout</th><th>Status</th></tr></thead>
            <tbody>
                @forelse(($data['payouts'] ?? collect()) as $payout)
                <tr>
                    <td style="font-weight:600;">{{ \Carbon\Carbon::createFromDate($payout->payout_year, $payout->payout_month, 1)->format('M Y') }}</td>
                    <td>${{ number_format($payout->total_sales, 2) }}</td>
                    <td style="color:#991b1b;">${{ number_format($payout->total_storage_charges + $payout->total_inward_charges + $payout->total_logistics_charges + $payout->total_platform_deductions + $payout->total_chargebacks, 2) }}</td>
                    <td style="font-weight:700;">${{ number_format($payout->net_payout, 2) }}</td>
                    <td><span class="badge {{ $payout->status === 'paid' ? 'badge-success' : ($payout->status === 'payment_pending' ? 'badge-warning' : 'badge-info') }}">{{ str_replace('_', ' ', $payout->status) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:2rem;">No payouts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
