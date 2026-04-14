@extends('layouts.app')
@section('title', 'Orders')
@section('page-title', 'Sales Orders')

@section('content')
{{-- KPI Stats --}}
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card"><div class="kpi-label">Total Orders</div><div class="kpi-value">{{ number_format($stats['total_orders'] ?? 0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;"><div class="kpi-label">Total Revenue</div><div class="kpi-value" style="color:#16a34a;font-size:1.2rem;">${{ number_format($stats['total_revenue'] ?? 0, 2) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Pending</div><div class="kpi-value" style="color:#e8a838;">{{ number_format($stats['pending_orders'] ?? 0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #1e40af;"><div class="kpi-label">Today</div><div class="kpi-value" style="color:#1e40af;">{{ number_format($stats['today_orders'] ?? 0) }}</div><div style="font-size:.65rem;color:#94a3b8;">${{ number_format($stats['today_revenue'] ?? 0, 0) }}</div></div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('sales.orders') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="flex:1;min-width:180px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Order #, platform ID..." style="width:100%;padding:.4rem .55rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option></select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Channel</label><select name="sales_channel_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($channels as $ch)<option value="{{ $ch->id }}" {{ request('sales_channel_id')==(string)$ch->id?'selected':'' }}>{{ $ch->name }}</option>@endforeach</select></div>
            <div style="min-width:120px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label><select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach(['pending','processing','confirmed','shipped','delivered','cancelled','refunded'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('sales.orders') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

{{-- Orders Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-shopping-cart" style="margin-right:.5rem;color:#1e3a5f;"></i> Orders</h3><span style="font-size:.78rem;color:#64748b;">{{ $orders->total() }} orders</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Order #</th><th>Date</th><th>Customer</th><th>Channel</th><th>Items</th><th>Amount</th><th>Status</th><th>Payment</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($orders as $o)
                <tr>
                    <td style="font-family:monospace;font-weight:600;font-size:.78rem;">
                        <div>{{ $o->order_number }}</div>
                        <div style="font-size:.62rem;color:#94a3b8;">{{ $o->platform_order_id }}</div>
                    </td>
                    <td style="font-size:.78rem;">{{ $o->order_date ? \Carbon\Carbon::parse($o->order_date)->format('d M Y') : '—' }}</td>
                    <td style="font-size:.78rem;">{{ $o->customer_name ?? '—' }}<div style="font-size:.62rem;color:#94a3b8;">{{ $o->customer_email ?? '' }}</div></td>
                    <td><span class="badge badge-info">{{ $o->salesChannel->name ?? '—' }}</span></td>
                    <td style="text-align:center;font-weight:600;">{{ $o->items->count() }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">{{ $o->currency ?? '$' }}{{ number_format($o->total_amount, 2) }}</td>
                    <td>
                        @php $sc = ['pending'=>'badge-warning','processing'=>'badge-info','confirmed'=>'badge-info','shipped'=>'badge-success','delivered'=>'badge-success','cancelled'=>'badge-danger','refunded'=>'badge-gray']; @endphp
                        <span class="badge {{ $sc[$o->status] ?? 'badge-gray' }}">{{ ucfirst($o->status ?? 'unknown') }}</span>
                    </td>
                    <td>
                        <span class="badge {{ $o->payment_status==='paid'?'badge-success':'badge-warning' }}">{{ ucfirst($o->payment_status ?? 'unpaid') }}</span>
                    </td>
                    <td><a href="{{ route('sales.orders.show', $o) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i></a></td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-shopping-cart" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $orders->links('pagination::tailwind') }}</div>@endif
</div>
@endsection