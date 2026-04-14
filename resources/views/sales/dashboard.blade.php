@extends('layouts.app')
@section('title', 'Sales Dashboard')
@section('page-title', 'Sales Dashboard')

@section('content')
<div class="grid-kpi">
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Total Orders</div><div class="kpi-value">{{ number_format($data['order_stats']['total_orders'] ?? 0) }}</div></div><div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-shopping-cart"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Pending Shipment</div><div class="kpi-value" style="color:#dc2626;">{{ $data['order_stats']['pending_shipment'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-clock"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">This Month Revenue</div><div class="kpi-value" style="color:#166534;font-size:1.4rem;">${{ number_format($data['order_stats']['this_month'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-chart-line"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Total Revenue</div><div class="kpi-value" style="font-size:1.4rem;">${{ number_format($data['order_stats']['total_revenue'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-dollar-sign"></i></div></div></div>
</div>

<div class="grid-2">
    {{-- By Channel --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-store" style="margin-right:.5rem;color:#e8a838;"></i> Revenue by Platform</h3></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Platform</th><th>Orders</th><th>Revenue</th></tr></thead>
                <tbody>
                    @foreach($data['by_channel'] ?? [] as $item)
                    <tr>
                        <td style="font-weight:600;">{{ $item['channel']->name }}</td>
                        <td style="text-align:center;font-weight:600;">{{ number_format($item['orders']) }}</td>
                        <td style="font-family:monospace;font-weight:700;color:#166534;">${{ number_format($item['revenue'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent Orders --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-receipt" style="margin-right:.5rem;color:#1e3a5f;"></i> Recent Orders</h3><a href="{{ route('sales.orders') }}" class="btn btn-outline btn-sm">View All</a></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Order #</th><th>Platform</th><th>Amount</th><th>Tracking</th></tr></thead>
                <tbody>
                    @forelse($data['recent_orders'] ?? [] as $order)
                    <tr>
                        <td style="font-weight:600;font-family:monospace;font-size:.82rem;">{{ $order->order_number }}</td>
                        <td><span class="badge badge-info">{{ $order->salesChannel->name ?? '—' }}</span></td>
                        <td style="font-family:monospace;font-weight:600;">${{ number_format($order->total_amount, 2) }}</td>
                        <td>
                            @if($order->tracking_id)
                                <span style="font-size:.72rem;color:#166534;"><i class="fas fa-check-circle"></i> {{ Str::limit($order->tracking_id, 15) }}</span>
                            @else
                                <span style="font-size:.72rem;color:#e8a838;"><i class="fas fa-clock"></i> Pending</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem;">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-bolt" style="margin-right:.5rem;color:#e8a838;"></i> Quick Actions</h3></div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <a href="{{ route('sales.upload') }}" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Sales</a>
        <a href="{{ route('sales.orders') }}" class="btn btn-outline"><i class="fas fa-shopping-cart"></i> All Orders</a>
        <a href="{{ route('sales.download-template') }}" class="btn btn-outline"><i class="fas fa-download"></i> Download Template</a>
    </div>
</div>
@endsection
