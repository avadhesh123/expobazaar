@extends('layouts.app')
@section('title', 'Pricing Review')
@section('page-title', 'Platform Pricing — Finance Review')

@section('content')
<div style="padding:.75rem 1.2rem;background:#ede9fe;border-radius:10px;border:1px solid #c4b5fd;margin-bottom:1.25rem;font-size:.82rem;color:#6d28d9;display:flex;align-items:center;gap:.5rem;">
    <i class="fas fa-info-circle"></i>
    <span>Review pricing submitted by HOD. Approving sends it back to HOD for final approval, then to Cataloguing.</span>
</div>

@php
    $grouped = $pricings->groupBy('asn_id');
    $avgMargin = $pricings->count() > 0 ? $pricings->avg('margin_percent') : 0;
    $lowMarginCount = $pricings->where('margin_percent', '<', 15)->count();
@endphp
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card"><div class="kpi-label">Pricing Items</div><div class="kpi-value">{{ $pricings->total() }}</div></div>
    <div class="kpi-card"><div class="kpi-label">ASNs Pending</div><div class="kpi-value" style="color:#e8a838;">{{ $grouped->count() }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Avg Margin</div><div class="kpi-value" style="color:{{ $avgMargin>=25?'#166534':'#e8a838' }};">{{ number_format($avgMargin, 1) }}%</div></div>
    <div class="kpi-card"><div class="kpi-label" style="color:#dc2626;">Low Margin (&lt;15%)</div><div class="kpi-value" style="color:#dc2626;">{{ $lowMarginCount }}</div></div>
</div>

@foreach($grouped as $asnId => $asnPricings)
@php $asnObj = $asnPricings->first()->asn; @endphp
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header" style="background:#faf5ff;">
        <div>
            <h3 style="display:flex;align-items:center;gap:.5rem;">
                <i class="fas fa-file-alt" style="color:#7c3aed;"></i>
                <span style="font-family:monospace;">{{ $asnObj->asn_number ?? 'ASN' }}</span>
            </h3>
            <div style="font-size:.72rem;color:#64748b;margin-top:.15rem;">
                Shipment: {{ $asnObj->shipment->shipment_code ?? '—' }} · {{ $asnObj->company_code }} · {{ $asnPricings->count() }} items
                · Avg Margin: <strong style="color:{{ $asnPricings->avg('margin_percent')>=25?'#166534':'#e8a838' }};">{{ number_format($asnPricings->avg('margin_percent'), 1) }}%</strong>
            </div>
        </div>
        <form method="POST" action="{{ route('finance.pricing.approve', $asnObj) }}" style="display:inline;" onsubmit="return confirm('Approve ALL pricing for this ASN?')">
            @csrf
            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve ASN</button>
        </form>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Product</th><th>SKU</th><th>Platform</th><th>Cost</th><th>Platform Price</th><th>Selling</th><th>MAP</th><th>Margin</th><th>By</th></tr></thead>
            <tbody>
                @foreach($asnPricings->sortBy('salesChannel.name') as $p)
                <tr style="{{ $p->margin_percent < 15 ? 'background:#fef2f2;' : '' }}">
                    <td style="font-weight:600;font-size:.82rem;">{{ $p->product->name ?? '—' }}</td>
                    <td style="font-family:monospace;font-size:.8rem;">{{ $p->product->sku ?? '—' }}</td>
                    <td><span class="badge badge-info">{{ $p->salesChannel->name ?? '—' }}</span></td>
                    <td style="font-family:monospace;font-weight:600;">${{ number_format($p->cost_price, 2) }}</td>
                    <td style="font-family:monospace;">${{ number_format($p->platform_price, 2) }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">${{ number_format($p->selling_price, 2) }}</td>
                    <td style="font-family:monospace;">{{ $p->map_price ? '$'.number_format($p->map_price, 2) : '—' }}</td>
                    <td>
                        @php $m = $p->margin_percent; @endphp
                        <span style="font-weight:700;color:{{ $m>=30?'#166534':($m>=15?'#e8a838':'#dc2626') }};">{{ number_format($m, 1) }}%</span>
                        <div style="margin-top:.15rem;background:#e2e8f0;border-radius:3px;height:4px;width:60px;"><div style="height:4px;border-radius:3px;width:{{ min($m,100) }}%;background:{{ $m>=30?'#16a34a':($m>=15?'#e8a838':'#dc2626') }};"></div></div>
                        @if($m < 15)<div style="font-size:.6rem;color:#dc2626;font-weight:600;margin-top:.1rem;"><i class="fas fa-exclamation-triangle"></i> Low</div>@endif
                    </td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $p->preparer->name ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach

@if($pricings->count() === 0)
<div class="card"><div class="card-body" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-check-circle" style="font-size:2.5rem;color:#16a34a;display:block;margin-bottom:.5rem;"></i><div style="font-size:.95rem;font-weight:600;">All clear!</div><div style="font-size:.85rem;">No pricing pending finance review.</div></div></div>
@endif

@if($pricings->hasPages())<div style="margin-top:1rem;">{{ $pricings->links('pagination::tailwind') }}</div>@endif
@endsection
