@extends('layouts.app')
@section('title', 'To Be Shipped')
@section('page-title', 'Sales Orders — To Be Shipped')

@section('content')
{{-- KPIs --}}
<style>
    .data-table td {
        border-bottom: 1px solid #ccc;
    }
</style>

<div style="display:flex;gap:1rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;border-left:3px solid #e8a838;">
        <div class="kpi-label">Pending Shipment</div>
        <div class="kpi-value" style="color:#e8a838;">{{ $stats['total_pending'] }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #dc2626;">
        <div class="kpi-label">Overdue (> 2 days)</div>
        <div class="kpi-value" style="color:#dc2626;">{{ $stats['overdue'] }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #1e40af;">
        <div class="kpi-label">Total Value</div>
        <div class="kpi-value" style="color:#1e40af;">{{$activeCurrencySymbol}}{{ number_format($stats['total_value'], 2) }}</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('sales.to-be-shipped') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Channel</label><select name="channel_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All</option>@foreach($channels as $ch)<option value="{{ $ch->id }}" {{ request('channel_id')==(string)$ch->id?'selected':'' }}>{{ $ch->name }}</option>@endforeach
                </select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('sales.to-be-shipped') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('sales.orders') }}" class="btn btn-outline btn-sm" style="margin-left:auto;"><i class="fas fa-arrow-left"></i> All Orders</a>
        </form>
    </div>
</div>

{{-- Orders Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-shipping-fast" style="margin-right:.5rem;color:#e8a838;"></i> To Be Shipped</h3><span style="font-size:.78rem;color:#64748b;">{{ $orders->total() }} orders</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.75rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="min-width:85px;">Order Date</th>
                    <th style="min-width:90px;">PO Number</th>
                    <th style="min-width:100px;">Invoice #</th>
                    <th>Channel</th>
                    <th style="min-width:70px;">Style Code</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:center;">Order Qty</th>
                    <th style="text-align:right;">Order Amt</th>
                    <th>Warehouse</th>
                    <th>Ship Method</th>
                    <th style="text-align:center;background:#fff7ed;min-width:70px;">Shipped Qty</th>
                    <th style="text-align:right;background:#fff7ed;">Shipped Amt</th>
                    <th style="min-width:100px;background:#fff7ed;">Tracking ID</th>
                    <th style="text-align:right;background:#fff7ed;min-width:80px;">Ship Cost</th>
                    <th style="background:#fff7ed;min-width:90px;">Order Pack Type</th>
                    <th style="background:#fff7ed;min-width:90px;">Carrier</th>

                    <th style="text-align:center;">Ageing</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>

                @forelse($orders as $o)
                @foreach($o->items as $item)
                <form method="POST" action="{{ route('sales.to-be-shipped.update', $o) }}">
                    @csrf
                    <tr style="{{ $o->is_overdue ? 'background:#fef2f2;' : '' }}">
                        @if($loop->first)
                        <td rowspan="{{ $o->items->count() }}" style="font-size:.78rem;">{{ $o->order_date?->format('d M Y') }}</td>
                        <td rowspan="{{ $o->items->count() }}" style="font-family:monospace;font-weight:600;">{{ $o->platform_order_id ?? '—' }}</td>
                        <td rowspan="{{ $o->items->count() }}" style="font-family:monospace;font-size:.72rem;">{{ $o->invoice_number ?? '—' }}</td>
                        <td rowspan="{{ $o->items->count() }}" style="font-size:.72rem;">{{ $o->salesChannel->name ?? '—' }}</td>
                        @endif
                        <td style="font-family:monospace;font-size:.78rem;">{{ $item->sku ?? $item->product->sku ?? '—' }}</td>
                        <td style="text-align:right;font-family:monospace;">{{$activeCurrencySymbol}}{{ number_format(floatval($item->unit_price), 2) }}</td>
                        <td style="text-align:center;font-weight:600;">{{ $item->quantity }}</td>
                        <td style="text-align:right;font-family:monospace;">{{$activeCurrencySymbol}}{{ number_format(floatval($item->total_price), 2) }}</td>
                        @if($loop->first)
                        <td rowspan="{{ $o->items->count() }}" style="font-size:.72rem;">{{ $o->warehouse->name ?? '—' }}</td>
                        <td rowspan="{{ $o->items->count() }}" style="font-size:.72rem;"> {{ strtoupper($o->shipping_method)}}</td>
                        @endif
                        <td style="background:#fff7ed;">
                            <input type="number" name="items[{{ $item->id }}][shipped_qty]"
                                value="{{ $item->shipped_qty ?? $item->quantity }}"
                                min="0" max="{{ $item->quantity }}" required
                                style="width:55px;padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.78rem;text-align:center;">
                        </td>
                        <td style="text-align:right;font-family:monospace;background:#fff7ed;" class="shipped-amt">
                            {{$activeCurrencySymbol}}{{ number_format(floatval($item->unit_price) * intval($item->quantity), 2) }}
                        </td>
                        @if($loop->first)
                        <td rowspan="{{ $o->items->count() }}" style="background:#fff7ed;">
                            <input type="text" name="tracking_id" required placeholder="Tracking..."
                                style="width:90px;padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.78rem;">
                        </td>
                        <td rowspan="{{ $o->items->count() }}" style="background:#fff7ed;">
                            <input type="number" step="0.01" name="shipping_cost" placeholder="0.00"
                                style="width:65px;padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.78rem;text-align:right;">
                        </td>
                        <td rowspan="{{ $o->items->count() }}" style="background:#fff7ed;">
                            <select name="order_pack_type" required style="padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.75rem;">
                                <option value="">Select</option>
                                <option value="palletize">Palletize</option>
                                <option value="non_palletize">Non Palletize</option>

                            </select>
                        </td>
                        <td rowspan="{{ $o->items->count() }}" style="background:#fff7ed;">
                            <select name="carrier" required style="padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.75rem;">
                                <option value="">Select</option>
                                @foreach(['Fedex','UPS','USPS','LTL','Other'] as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td rowspan="{{ $o->items->count() }}" style="text-align:center;">
                            @if($o->is_overdue)
                            <span style="font-weight:700;color:#dc2626;">{{ round($o->ageing_days,2) }}d</span>
                            <div style="font-size:.55rem;color:#dc2626;">OVERDUE</div>
                            @else
                            <span style="font-weight:600;color:#16a34a;">{{ round($o->ageing_days,2) }}d</span>
                            @endif
                        </td>
                        <td rowspan="{{ $o->items->count() }}">
                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Mark as shipped?')"><i class="fas fa-truck"></i></button>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </form>
                @empty<tr>
                    <td colspan="17" style="text-align:center;padding:3rem;color:#94a3b8;">All orders have been shipped!</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $orders->links('pagination::tailwind') }}</div>@endif
</div>
<script>
    document.querySelectorAll('input[name="shipped_qty"]').forEach(function(input) {
        input.addEventListener('input', function() {
            var row = this.closest('tr');
            var unitPriceText = row.querySelectorAll('td')[5].textContent.replace(/[^0-9.]/g, '');
            var unitPrice = parseFloat(unitPriceText) || 0;
            var shippedQty = parseInt(this.value) || 0;
            var shippedAmt = (unitPrice * shippedQty).toFixed(2);
            row.querySelectorAll('td')[11].textContent = '$' + shippedAmt;
        });
    });
</script>
@endsection