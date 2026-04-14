@extends('layouts.app')
@section('title', 'Payout Detail')
@section('page-title', 'Payout Detail — ' . ($payout->vendor->company_name ?? ''))

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('finance.payouts') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Payouts</a>
    @if($payout->status === 'paid')<a href="{{ route('finance.payouts.advice', $payout) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-download"></i> Download Payment Advice</a>@endif
</div>

{{-- Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1.25rem 1.4rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <div>
                <div style="font-size:1.1rem;font-weight:800;color:#0d1b2a;">{{ $payout->vendor->company_name ?? '—' }}</div>
                <div style="font-size:.82rem;color:#64748b;">{{ $payout->vendor->vendor_code ?? '' }} · {{ $payout->company_code }} · {{ date('F',mktime(0,0,0,$payout->payout_month,1)) }} {{ $payout->payout_year }}</div>
            </div>
            @php $sc = ['calculated'=>'badge-warning','approved'=>'badge-info','payment_pending'=>'badge-warning','paid'=>'badge-success','invoice_received'=>'badge-success']; @endphp
            <span class="badge {{ $sc[$payout->status] ?? 'badge-gray' }}" style="font-size:.85rem;padding:.35rem .85rem;">{{ ucfirst(str_replace('_',' ',$payout->status)) }}</span>
        </div>

        {{-- Payout Breakdown --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:.75rem;">
            <div style="padding:.7rem;background:#dcfce7;border-radius:8px;text-align:center;"><div style="font-size:.62rem;color:#166534;font-weight:600;text-transform:uppercase;">Total Sales</div><div style="font-size:1.15rem;font-weight:800;color:#166534;font-family:monospace;">${{ number_format($payout->total_sales, 2) }}</div></div>
            <div style="padding:.7rem;background:#fee2e2;border-radius:8px;text-align:center;"><div style="font-size:.62rem;color:#dc2626;font-weight:600;text-transform:uppercase;">Storage</div><div style="font-size:1.05rem;font-weight:700;color:#dc2626;font-family:monospace;">-${{ number_format($payout->total_storage_charges, 2) }}</div></div>
            <div style="padding:.7rem;background:#fee2e2;border-radius:8px;text-align:center;"><div style="font-size:.62rem;color:#dc2626;font-weight:600;text-transform:uppercase;">Inward</div><div style="font-size:1.05rem;font-weight:700;color:#dc2626;font-family:monospace;">-${{ number_format($payout->total_inward_charges, 2) }}</div></div>
            <div style="padding:.7rem;background:#fee2e2;border-radius:8px;text-align:center;"><div style="font-size:.62rem;color:#dc2626;font-weight:600;text-transform:uppercase;">Logistics</div><div style="font-size:1.05rem;font-weight:700;color:#dc2626;font-family:monospace;">-${{ number_format($payout->total_logistics_charges, 2) }}</div></div>
            <div style="padding:.7rem;background:#fef3c7;border-radius:8px;text-align:center;"><div style="font-size:.62rem;color:#92400e;font-weight:600;text-transform:uppercase;">Platform Ded.</div><div style="font-size:1.05rem;font-weight:700;color:#92400e;font-family:monospace;">-${{ number_format($payout->total_platform_deductions, 2) }}</div></div>
            <div style="padding:.7rem;background:#fef2f2;border-radius:8px;text-align:center;"><div style="font-size:.62rem;color:#991b1b;font-weight:600;text-transform:uppercase;">Chargebacks</div><div style="font-size:1.05rem;font-weight:700;color:#991b1b;font-family:monospace;">-${{ number_format($payout->total_chargebacks, 2) }}</div></div>
            <div style="padding:.7rem;background:{{ $payout->net_payout >= 0 ? '#dcfce7' : '#fee2e2' }};border-radius:8px;text-align:center;border:2px solid {{ $payout->net_payout >= 0 ? '#16a34a' : '#dc2626' }};"><div style="font-size:.62rem;color:{{ $payout->net_payout >= 0 ? '#166534' : '#dc2626' }};font-weight:600;text-transform:uppercase;">Net Payout</div><div style="font-size:1.25rem;font-weight:800;color:{{ $payout->net_payout >= 0 ? '#166534' : '#dc2626' }};font-family:monospace;">${{ number_format($payout->net_payout, 2) }}</div></div>
        </div>

        @if($payout->payment_date)
        <div style="margin-top:.75rem;padding:.5rem .75rem;background:#f0fdf4;border-radius:6px;font-size:.82rem;color:#166534;">
            <i class="fas fa-check-circle" style="margin-right:.2rem;"></i> Paid on {{ $payout->payment_date->format('d M Y') }} · Ref: {{ $payout->payment_reference ?? '—' }} · Method: {{ ucfirst($payout->payment_method ?? '—') }}
        </div>
        @endif
    </div>
</div>

{{-- Orders for this period --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-shopping-cart" style="margin-right:.5rem;color:#1e3a5f;"></i> Orders ({{ $orders->count() }})</h3><span style="font-size:.78rem;color:#64748b;">${{ number_format($orders->sum('total_amount'), 2) }} total</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Order #</th><th>Platform</th><th>Date</th><th>Items</th><th>Amount</th></tr></thead>
            <tbody>
                @foreach($orders->take(20) as $order)
                <tr>
                    <td style="font-weight:600;font-family:monospace;font-size:.8rem;">{{ $order->order_number }}</td>
                    <td><span class="badge badge-info">{{ $order->salesChannel->name ?? '—' }}</span></td>
                    <td style="font-size:.82rem;">{{ $order->order_date->format('d M Y') }}</td>
                    <td style="text-align:center;">{{ $order->items->count() }}</td>
                    <td style="font-family:monospace;font-weight:600;">${{ number_format($order->total_amount, 2) }}</td>
                </tr>
                @endforeach
                @if($orders->count() > 20)<tr><td colspan="5" style="text-align:center;color:#94a3b8;font-size:.82rem;">... and {{ $orders->count() - 20 }} more orders</td></tr>@endif
            </tbody>
        </table>
    </div>
</div>

<div class="grid-2">
    {{-- Warehouse Charges --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-warehouse" style="margin-right:.5rem;color:#e8a838;"></i> Warehouse Charges</h3></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Warehouse</th><th>Type</th><th>Amount</th></tr></thead>
                <tbody>
                    @forelse($warehouseCharges as $wc)
                    <tr>
                        <td style="font-size:.82rem;">{{ $wc->warehouse->name ?? '—' }}</td>
                        <td><span class="badge badge-info">{{ ucfirst(str_replace('_',' ',$wc->charge_type)) }}</span></td>
                        <td style="font-family:monospace;font-weight:600;color:#dc2626;">${{ number_format($wc->calculated_amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:1rem;">No charges.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Chargebacks --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#dc2626;"></i> Chargebacks</h3></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Order</th><th>Reason</th><th>Amount</th></tr></thead>
                <tbody>
                    @forelse($chargebacks as $cb)
                    <tr>
                        <td style="font-family:monospace;font-size:.8rem;">{{ $cb->order->order_number ?? '—' }}</td>
                        <td style="font-size:.82rem;">{{ $cb->reason }}</td>
                        <td style="font-family:monospace;font-weight:600;color:#dc2626;">${{ number_format($cb->amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:1rem;">No chargebacks.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
