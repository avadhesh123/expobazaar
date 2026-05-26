@extends('layouts.app')
@section('title', 'Sales Dashboard')
@section('page-title', 'Sales Dashboard')

@section('content')
 {{-- Date Range Filter --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.7rem 1.4rem;">
        <form method="GET" action="{{ route('sales.dashboard') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div>
                <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">From</label>
                <input type="date" name="date_from" value="{{ $data['date_from'] ?? now()->startOfMonth()->toDateString() }}" style="padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
            </div>
            <div>
                <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">To</label>
                <input type="date" name="date_to" value="{{ $data['date_to'] ?? now()->toDateString() }}" style="padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
            <a href="{{ route('sales.dashboard') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Reset</a>
            <div style="margin-left:auto;font-size:.72rem;color:#64748b;">
                Showing: <strong>{{ \Carbon\Carbon::parse($data['date_from'])->format('d M Y') }}</strong> — <strong>{{ \Carbon\Carbon::parse($data['date_to'])->format('d M Y') }}</strong>
            </div>

            <div style="display:flex;gap:.3rem;">
    <a href="{{ route('sales.dashboard', ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]) }}" class="btn btn-outline btn-sm" style="font-size:.68rem;">Today</a>
    <a href="{{ route('sales.dashboard', ['date_from' => now()->startOfWeek()->toDateString(), 'date_to' => now()->toDateString()]) }}" class="btn btn-outline btn-sm" style="font-size:.68rem;">This Week</a>
    <a href="{{ route('sales.dashboard', ['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->toDateString()]) }}" class="btn btn-outline btn-sm" style="font-size:.68rem;">This Month</a>
    <a href="{{ route('sales.dashboard', ['date_from' => now()->startOfQuarter()->toDateString(), 'date_to' => now()->toDateString()]) }}" class="btn btn-outline btn-sm" style="font-size:.68rem;">This Quarter</a>
    <a href="{{ route('sales.dashboard', ['date_from' => now()->startOfYear()->toDateString(), 'date_to' => now()->toDateString()]) }}" class="btn btn-outline btn-sm" style="font-size:.68rem;">YTD</a>
</div>
        </form>
    </div>
</div>
<div class="grid-kpi">
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Total Orders</div><div class="kpi-value">{{ number_format($data['kpis']['orders_received'] ?? 0) }}</div></div><div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-shopping-cart"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Pending Shipment</div><div class="kpi-value" style="color:#dc2626;">{{ $data['kpis']['pending_shipment'] ?? 0 }}</div></div><div class="kpi-icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-clock"></i></div></div></div>
    <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">This Month Revenue</div><div class="kpi-value" style="color:#166534;font-size:1.4rem;">{{$activeCurrencySymbol}}{{ number_format($data['kpis']['monthly_sales'] ?? 0, 0) }}</div></div><div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-chart-line"></i></div></div></div>
    <!-- <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Total Revenue</div><div class="kpi-value" style="font-size:1.4rem;">{{$activeCurrencySymbol}}{{ number_format($data['kpis']['daily_sales'] + ($data['kpis']['monthly_sales'] ?? 0), 0) }}</div></div><div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-dollar-sign"></i></div></div></div> -->
<div class="kpi-card" style="border-left:3px solid #16a34a;">
    <div class="kpi-label">Period Revenue</div>
    <div class="kpi-value" style="color:#16a34a;font-size:1.2rem;">{{ $activeCurrencySymbol }}{{ number_format($data['kpis']['period_sales'] ?? 0, 2) }}</div>
</div>

</div>

<div class="grid-2">
    {{-- By Channel --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-store" style="margin-right:.5rem;color:#e8a838;"></i> Revenue by Platform</h3></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Platform</th><th>Orders</th><th>Revenue</th></tr></thead>
                <tbody>
                    @foreach($data['by_platform'] ?? [] as $item)
                    <tr>
                        <td style="font-weight:600;">{{ $item->salesChannel->name ?? 'Unknown Channel' }}</td>
                        <td style="text-align:center;font-weight:600;">{{ number_format($item['orders']) }}</td>
                        <td style="font-family:monospace;font-weight:700;color:#166534;">{{$activeCurrencySymbol}}{{ number_format($item['revenue'], 2) }}</td>
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
                        <td style="font-family:monospace;font-weight:600;">{{$activeCurrencySymbol}}{{ number_format($order->total_amount, 2) }}</td>
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
