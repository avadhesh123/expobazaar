@extends('layouts.app')
@section('title', 'Sales Report')
@section('page-title', 'Sales Report')

@section('content')
<div class="kpi-card" style="margin-bottom:1.25rem;display:inline-block;"><div class="kpi-label">Total Sales</div><div class="kpi-value" style="color:#166534;">${{ number_format($totalSales, 2) }}</div></div>

<div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('vendor.sales') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Month</label><select name="month" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ request('month')==(string)$m?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Year</label><select name="year" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@for($y=date('Y');$y>=date('Y')-2;$y--)<option value="{{ $y }}" {{ request('year')==(string)$y?'selected':'' }}>{{ $y }}</option>@endfor</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('vendor.sales') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-chart-line" style="margin-right:.5rem;color:#166534;"></i> Orders</h3><span style="font-size:.78rem;color:#64748b;">{{ $orders->total() }} orders</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Order #</th><th>Platform</th><th>Date</th><th>Items</th><th>Amount</th><th>Tracking</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($orders as $o)
                <tr>
                    <td style="font-weight:600;font-family:monospace;font-size:.82rem;">{{ $o->order_number }}</td>
                    <td><span class="badge badge-info">{{ $o->salesChannel->name ?? '—' }}</span></td>
                    <td style="font-size:.82rem;">{{ $o->order_date->format('d M Y') }}</td>
                    <td style="text-align:center;">{{ $o->items->count() }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">${{ number_format($o->total_amount, 2) }}</td>
                    <td>@if($o->tracking_id)<span style="font-size:.72rem;color:#166534;"><i class="fas fa-check-circle"></i> {{ Str::limit($o->tracking_id,15) }}</span>@if($o->tracking_url)<br><a href="{{ $o->tracking_url }}" target="_blank" style="font-size:.62rem;color:#1e40af;">Track →</a>@endif @else<span style="font-size:.72rem;color:#94a3b8;">Pending</span>@endif</td>
                    <td>@php $ssc=['pending'=>'badge-warning','shipped'=>'badge-info','delivered'=>'badge-success']; @endphp <span class="badge {{ $ssc[$o->shipment_status??'pending']??'badge-gray' }}">{{ ucfirst($o->shipment_status??'pending') }}</span></td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;">No sales data yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $orders->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
