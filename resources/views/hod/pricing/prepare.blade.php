@extends('layouts.app')
@section('title', 'Prepare Pricing')
@section('page-title', 'Platform Pricing — ' . $asn->asn_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('hod.asn-list') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> ASN List</a>
    @if($asn->status === 'pricing_done')<a href="{{ route('hod.pricing.status', $asn) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View Status</a>@endif
</div>

{{-- ASN Info --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem;">
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">ASN Number</div><div style="font-weight:800;font-family:monospace;">{{ $asn->asn_number }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Shipment</div><div style="font-weight:600;">{{ $asn->shipment->shipment_code ?? '—' }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Company</div><div style="font-weight:600;">{{ $asn->company_code }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Items</div><div style="font-weight:700;">{{ $asn->total_items }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">CBM</div><div style="font-weight:600;">{{ number_format($asn->total_cbm, 2) }}</div></div>
        </div>
    </div>
</div>

<div style="margin-bottom:.5rem;font-size:.78rem;color:#64748b;"><i class="fas fa-info-circle"></i> Set pricing for each product across each sales channel. Margin calculates automatically.</div>

<form method="POST" action="{{ route('hod.pricing.store', $asn) }}" id="pricingForm">
    @csrf
    @php $pricingIdx = 0; @endphp

    @foreach($channels as $channel)
    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header" style="cursor:pointer;" onclick="document.getElementById('ch{{ $channel->id }}').style.display=document.getElementById('ch{{ $channel->id }}').style.display==='none'?'block':'none'">
            <h3>
                @php $icons = ['Amazon'=>'fab fa-amazon','Wayfair'=>'fas fa-couch','Shopify'=>'fab fa-shopify','Faire'=>'fas fa-store','GIGA'=>'fas fa-globe','TICA'=>'fas fa-store-alt','Coons'=>'fas fa-shopping-bag']; @endphp
                <i class="{{ $icons[$channel->name] ?? 'fas fa-store' }}" style="margin-right:.5rem;color:#e8a838;"></i>
                {{ $channel->name }}
                <span style="font-size:.72rem;font-weight:400;color:#64748b;margin-left:.5rem;">({{ ucfirst($channel->type) }})</span>
            </h3>
            <i class="fas fa-chevron-down" style="color:#94a3b8;"></i>
        </div>
        <div id="ch{{ $channel->id }}">
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>Product</th><th>SKU</th><th>Vendor</th><th>Qty</th><th>Cost ($) *</th><th>Platform ($) *</th><th>Selling ($) *</th><th>MAP ($)</th><th>Margin</th></tr></thead>
                    <tbody>
                        @foreach($asn->shipment->consignments as $con)
                            @if($con->liveSheet)
                                @foreach($con->liveSheet->items as $item)
                                @php
                                    $key = $item->product_id . '-' . $channel->id;
                                    $ep = ($existingPricing[$key] ?? collect())->first();
                                @endphp
                                <tr>
                                    <td>
                                        <input type="hidden" name="pricing[{{ $pricingIdx }}][product_id]" value="{{ $item->product_id }}">
                                        <input type="hidden" name="pricing[{{ $pricingIdx }}][sales_channel_id]" value="{{ $channel->id }}">
                                        <div style="font-weight:600;font-size:.82rem;">{{ $item->product->name ?? '—' }}</div>
                                        @if($item->product->category)<div style="font-size:.65rem;color:#94a3b8;">{{ $item->product->category->name }}</div>@endif
                                    </td>
                                    <td style="font-family:monospace;font-size:.8rem;">{{ $item->product->sku ?? '—' }}</td>
                                    <td style="font-size:.78rem;color:#64748b;">{{ $con->vendor->company_name ?? '—' }}</td>
                                    <td style="text-align:center;font-weight:600;">{{ $item->quantity }}</td>
                                    <td><input type="number" step="0.01" min="0" name="pricing[{{ $pricingIdx }}][cost_price]" value="{{ $ep->cost_price ?? $item->unit_price ?? '' }}" required data-row="{{ $pricingIdx }}" onchange="calcMargin({{ $pricingIdx }})" style="width:90px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;"></td>
                                    <td><input type="number" step="0.01" min="0" name="pricing[{{ $pricingIdx }}][platform_price]" value="{{ $ep->platform_price ?? '' }}" required style="width:90px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;"></td>
                                    <td><input type="number" step="0.01" min="0" name="pricing[{{ $pricingIdx }}][selling_price]" value="{{ $ep->selling_price ?? '' }}" required onchange="calcMargin({{ $pricingIdx }})" style="width:90px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;background:#eff6ff;"></td>
                                    <td><input type="number" step="0.01" min="0" name="pricing[{{ $pricingIdx }}][map_price]" value="{{ $ep->map_price ?? '' }}" style="width:80px;padding:.3rem .4rem;border:1px solid #e2e8f0;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;"></td>
                                    <td style="text-align:center;"><span id="margin{{ $pricingIdx }}" style="font-weight:700;font-size:.88rem;">{{ $ep ? number_format($ep->margin_percent, 1) . '%' : '—' }}</span></td>
                                </tr>
                                @php $pricingIdx++; @endphp
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endforeach

    <div style="display:flex;gap:.5rem;justify-content:flex-end;padding:1rem;background:#fff;border-radius:14px;border:1px solid #e8ecf1;">
        <a href="{{ route('hod.asn-list') }}" class="btn btn-outline">Cancel</a>
        @php $itemCount = $pricingIdx; @endphp
        <button type="submit" class="btn btn-primary" onclick="return confirm('Submit pricing to Finance for review?\n\nTotal price items: {{ $itemCount }}')">
            <i class="fas fa-paper-plane" style="margin-right:.3rem;"></i> Submit to Finance Review
        </button>
    </div>
</form>

@push('scripts')
<script>
function calcMargin(idx) {
    var cost = parseFloat(document.querySelector('[name="pricing['+idx+'][cost_price]"]').value) || 0;
    var sell = parseFloat(document.querySelector('[name="pricing['+idx+'][selling_price]"]').value) || 0;
    var el = document.getElementById('margin'+idx);
    if (sell > 0 && cost > 0) {
        var m = ((sell - cost) / sell) * 100;
        el.textContent = m.toFixed(1) + '%';
        el.style.color = m >= 30 ? '#16a34a' : (m >= 15 ? '#e8a838' : '#dc2626');
    } else { el.textContent = '—'; el.style.color = '#94a3b8'; }
}
</script>
@endpush
@endsection
