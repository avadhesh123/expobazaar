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
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">Total Orders</div>
                <div class="kpi-value">{{ number_format($data['kpis']['orders_received'] ?? 0) }}</div>
            </div>
            <div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-shopping-cart"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">Pending Shipment</div>
                <div class="kpi-value" style="color:#dc2626;">{{ $data['kpis']['pending_shipment'] ?? 0 }}</div>
            </div>
            <div class="kpi-icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">This Month Revenue</div>
                <div class="kpi-value" style="color:#166534;font-size:1.4rem;">{{$activeCurrencySymbol}}{{ number_format($data['kpis']['current_month_sales'] ?? 0, 0) }}</div>
            </div>
            <div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>
    <!-- <div class="kpi-card"><div style="display:flex;justify-content:space-between;align-items:start;"><div><div class="kpi-label">Total Revenue</div><div class="kpi-value" style="font-size:1.4rem;">{{$activeCurrencySymbol}}{{ number_format($data['kpis']['daily_sales'] + ($data['kpis']['monthly_sales'] ?? 0), 0) }}</div></div><div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-dollar-sign"></i></div></div></div> -->
    <div class="kpi-card" style="border-left:3px solid #16a34a;">
        <div class="kpi-label">Period Revenue</div>
        <div class="kpi-value" style="color:#16a34a;font-size:1.2rem;">{{ $activeCurrencySymbol }}{{ number_format($data['kpis']['period_sales'] ?? 0, 2) }}</div>
    </div>

</div>

<div class="grid-2">
    {{-- By Channel --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-store" style="margin-right:.5rem;color:#e8a838;"></i> Revenue by Platform</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Platform</th>
                        <th>Orders</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['by_platform'] ?? [] as $item)
              
                    <tr>
                        <td style="font-weight:600;">{{ $item->salesChannel->name ?? 'Unknown Channel' }}</td>
                        <td style="text-align:center;font-weight:600;">{{ number_format($item['count']) }}</td>
                        <td style="font-family:monospace;font-weight:700;color:#166534;">{{$activeCurrencySymbol}}{{ number_format($item['total'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
{{-- Sales by Vendor --}}
@php
    $cs = $activeCurrencySymbol ?? '$';
    $grandTotal = $salesByVendor->sum('total_sales');
@endphp

<div class="card" style="margin-top:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-store" style="margin-right:.5rem;color:#7c3aed;"></i> Sales by Vendor</h3>
        <span style="font-size:.72rem;color:#94a3b8;">{{ $salesByVendor->count() }} vendors · {{ $cs }}{{ number_format($grandTotal, 2) }} total</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;margin:0;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="width:30px;">#</th>
                    <th>Vendor</th>
                    <th style="text-align:center;">Orders</th>
                    <th style="text-align:center;">SKUs</th>
                    <th style="text-align:center;">Shipped Qty</th>
                    <th style="text-align:right;">Sales Amount</th>
                    <th style="text-align:right;">% Share</th>
                    <!-- <th style="width:200px;">Distribution</th> -->
                </tr>
            </thead>
            <tbody>
                @forelse($salesByVendor as $idx => $vs)
                @php
                    $pct = $grandTotal > 0 ? round(($vs->total_sales / $grandTotal) * 100, 1) : 0;
                    $barColors = ['#1e40af','#16a34a','#7c3aed','#e8a838','#dc2626','#0d9488','#be185d','#854d0e'];
                    $barColor = $barColors[$idx % count($barColors)];
                @endphp
                <tr>
                    <td style="text-align:center;color:#94a3b8;">{{ $idx + 1 }}</td>
                    <td>
                        <div style="font-weight:600;">{{ $vs->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.62rem;color:#94a3b8;">{{ $vs->vendor->vendor_code ?? '' }}</div>
                    </td>
                    <td style="text-align:center;font-weight:600;">{{ $vs->total_orders }}</td>
                    <td style="text-align:center;">{{ $vs->total_skus }}</td>
                    <td style="text-align:center;font-weight:600;">{{ number_format($vs->total_qty) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:700;color:#166534;">{{ $cs }}{{ number_format($vs->total_sales, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;color:{{ $barColor }};">{{ $pct }}%</td>
                    <!-- <td>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;width:{{ $pct }}%;background:{{ $barColor }};border-radius:4px;"></div>
                            </div>
                            <span style="font-size:.6rem;color:#94a3b8;min-width:30px;">{{ $pct }}%</span>
                        </div>
                    </td> -->
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8;">No sales data found.</td></tr>
                @endforelse
            </tbody>
            @if($salesByVendor->isNotEmpty())
            <tfoot>
                <tr style="background:#f0f4f8;font-weight:700;">
                    <td colspan="2">TOTAL</td>
                    <td style="text-align:center;">{{ $salesByVendor->sum('total_orders') }}</td>
                    <td style="text-align:center;">{{ $salesByVendor->sum('total_skus') }}</td>
                    <td style="text-align:center;">{{ number_format($salesByVendor->sum('total_qty')) }}</td>
                    <td style="text-align:right;font-family:monospace;color:#166534;">{{ $cs }}{{ number_format($grandTotal, 2) }}</td>
                    <td style="text-align:right;">100%</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
    {{-- Recent Orders --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-receipt" style="margin-right:.5rem;color:#1e3a5f;"></i> Recent Orders</h3><a href="{{ route('sales.orders') }}" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Platform</th>
                        <th>Amount</th>
                        <th>Tracking</th>
                    </tr>
                </thead>
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
                    <tr>
                        <td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem;">No orders yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-bolt" style="margin-right:.5rem;color:#e8a838;"></i> Quick Actions</h3>
    </div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <a href="{{ route('sales.upload') }}" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Sales</a>
        <a href="{{ route('sales.orders') }}" class="btn btn-outline"><i class="fas fa-shopping-cart"></i> All Orders</a>
        <a href="{{ route('sales.download-template') }}" class="btn btn-outline"><i class="fas fa-download"></i> Download Template</a>
    </div>
</div>
@endsection