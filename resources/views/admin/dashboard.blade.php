@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')

@section('content')
{{-- KPIs --}}
<div class="grid-kpi">
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Total Vendors</div><div class="kpi-value">{{ number_format($data['kpis']['total_vendors'] ?? 0) }}</div></div><div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-users"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Active Vendors</div><div class="kpi-value">{{ number_format($data['kpis']['active_vendors'] ?? 0) }}</div></div><div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-user-check"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Total SKUs</div><div class="kpi-value">{{ number_format($data['kpis']['total_skus'] ?? 0) }}</div></div><div class="kpi-icon" style="background:#fef3c7;color:#92400e;"><i class="fas fa-box"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">SKUs Listed</div><div class="kpi-value">{{ number_format($data['kpis']['listed_skus'] ?? 0) }}</div></div><div class="kpi-icon" style="background:#e0e7ff;color:#3730a3;"><i class="fas fa-list-check"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">In Transit</div><div class="kpi-value">{{ number_format($data['kpis']['shipments_in_transit'] ?? 0) }}</div></div><div class="kpi-icon" style="background:#fce4ec;color:#c62828;"><i class="fas fa-ship"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Monthly Sales</div><div class="kpi-value">${{ number_format($data['kpis']['monthly_sales'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-chart-line"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">YTD Sales</div><div class="kpi-value">${{ number_format($data['kpis']['ytd_sales'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-dollar-sign"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Pending Payouts</div><div class="kpi-value">${{ number_format($data['kpis']['pending_payouts'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#fef3c7;color:#92400e;"><i class="fas fa-money-check"></i></div></div></div>
</div>

<div class="grid-2">
    {{-- Vendor Activity --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-user-plus" style="margin-right:.5rem;color:#e8a838;"></i> Vendor Activity</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div style="padding:1rem;background:#f8fafc;border-radius:10px;text-align:center;"><div style="font-size:1.5rem;font-weight:800;color:#0d1b2a;">{{ $data['vendor_activity']['onboarded_this_month'] ?? 0 }}</div><div style="font-size:.7rem;color:#64748b;font-weight:600;">Onboarded This Month</div></div>
                <div style="padding:1rem;background:#fef3c7;border-radius:10px;text-align:center;"><div style="font-size:1.5rem;font-weight:800;color:#92400e;">{{ $data['vendor_activity']['pending_kyc'] ?? 0 }}</div><div style="font-size:.7rem;color:#92400e;font-weight:600;">Pending KYC</div></div>
                <div style="padding:1rem;background:#dbeafe;border-radius:10px;text-align:center;"><div style="font-size:1.5rem;font-weight:800;color:#1e40af;">{{ $data['vendor_activity']['pending_contract'] ?? 0 }}</div><div style="font-size:.7rem;color:#1e40af;font-weight:600;">Pending Contract</div></div>
                <div style="padding:1rem;background:#fee2e2;border-radius:10px;text-align:center;"><div style="font-size:1.5rem;font-weight:800;color:#991b1b;">{{ $data['vendor_activity']['pending_approval'] ?? 0 }}</div><div style="font-size:.7rem;color:#991b1b;font-weight:600;">Pending Approval</div></div>
            </div>
        </div>
    </div>

    {{-- Operations Overview --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-cogs" style="margin-right:.5rem;color:#2d6a4f;"></i> Operations Overview</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div style="padding:1rem;background:#f0fdf4;border-radius:10px;text-align:center;"><div style="font-size:1.5rem;font-weight:800;color:#166534;">{{ $data['operations']['consignments_in_production'] ?? 0 }}</div><div style="font-size:.7rem;color:#166534;font-weight:600;">In Production</div></div>
                <div style="padding:1rem;background:#eff6ff;border-radius:10px;text-align:center;"><div style="font-size:1.5rem;font-weight:800;color:#1e40af;">{{ $data['operations']['containers_planned'] ?? 0 }}</div><div style="font-size:.7rem;color:#1e40af;font-weight:600;">Containers Planned</div></div>
                <div style="padding:1rem;background:#fefce8;border-radius:10px;text-align:center;"><div style="font-size:1.5rem;font-weight:800;color:#854d0e;">{{ $data['operations']['shipments_in_transit'] ?? 0 }}</div><div style="font-size:.7rem;color:#854d0e;font-weight:600;">In Transit</div></div>
            </div>
        </div>
    </div>
</div>

<div class="grid-2" style="margin-top:1.25rem;">
    {{-- Inventory Ageing --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-clock" style="margin-right:.5rem;color:#dc2626;"></i> Inventory Ageing</h3></div>
        <div class="card-body">
            @php $ageing = $data['inventory_ageing'] ?? []; @endphp
            @foreach(['0_30' => '0-30 Days', '31_60' => '31-60 Days', '61_90' => '61-90 Days', '91_120' => '91-120 Days', '120_plus' => '120+ Days'] as $key => $label)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.82rem;color:#334155;">{{ $label }}</span>
                <span style="font-size:.85rem;font-weight:700;color:#0d1b2a;">{{ number_format($ageing[$key] ?? 0) }} units</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-bolt" style="margin-right:.5rem;color:#e8a838;"></i> Quick Actions</h3></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:.6rem;">
            <a href="{{ route('admin.vendors.pending') }}" class="btn btn-outline" style="justify-content:space-between;"><span><i class="fas fa-user-check" style="margin-right:.5rem;"></i> Review Pending Vendors</span><span class="badge badge-warning">{{ $data['vendor_activity']['pending_approval'] ?? 0 }}</span></a>
            <a href="{{ route('admin.users.create') }}" class="btn btn-outline"><i class="fas fa-user-plus" style="margin-right:.5rem;"></i> Create New User</a>
            <a href="{{ route('admin.categories') }}" class="btn btn-outline"><i class="fas fa-tags" style="margin-right:.5rem;"></i> Manage Categories</a>
            <a href="{{ route('admin.sales-channels') }}" class="btn btn-outline"><i class="fas fa-store" style="margin-right:.5rem;"></i> Manage Sales Channels</a>
            <a href="{{ route('admin.warehouses') }}" class="btn btn-outline"><i class="fas fa-warehouse" style="margin-right:.5rem;"></i> Manage Warehouses</a>
            <a href="{{ route('admin.activity-log') }}" class="btn btn-outline"><i class="fas fa-history" style="margin-right:.5rem;"></i> View Activity Log</a>
        </div>
    </div>
</div>
@endsection
