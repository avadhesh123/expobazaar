@extends('layouts.app')
@section('title', 'Prepare Pricing')
@section('page-title', 'Pricing — ' . $asn->asn_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('hod.asn-list') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> ASN List</a>
    <a href="{{ route('hod.pricing.download', $asn) }}" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Download Pricing CSV</a>
    <a href="{{ route('hod.pricing.status', $asn) }}" class="btn btn-outline btn-sm"><i class="fas fa-chart-bar"></i> Pricing Status</a>
</div>

<!-- <div style="padding:.6rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
    SKU, SAP, Vendor, Qty, FOB, WSP come from consignments. Enter <strong>Last Mile</strong> manually. <strong>Retail Price</strong> = WSP + Last Mile. Channel prices = WSP × Pricing Factor (auto-calculated). Click <strong>Save Pricing</strong> to submit.
</div> -->

<form method="POST" action="{{ route('hod.pricing.store', $asn) }}">
    @csrf
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-dollar-sign" style="margin-right:.5rem;color:#e8a838;"></i> {{ $asn->asn_number }} — Pricing Sheet</h3>
            <div style="display:flex;gap:.4rem;">
                <span style="font-size:.78rem;color:#64748b;">{{ $items->count() }} items · {{ $channels->count() }} channels</span>
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Submit pricing for Finance review?')"><i class="fas fa-save"></i> Save Pricing</button>
            </div>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table" style="margin:0;font-size:.75rem;">
                <thead>
                   
                    {{-- Row 1: Column headers --}}
                    <tr style="background:#f0f4f8;">
                        <th style="min-width:110px;">SKU</th>
                        <th style="min-width:80px;">SAP</th>
                        <th style="min-width:130px;">Vendor Name</th>
                        <th style="min-width:50px;text-align:center;">Qty</th>
                        <th style="min-width:70px;text-align:right;">FOB</th>
                        <th style="min-width:70px;text-align:right;">WSP</th>
                        <th style="min-width:80px;text-align:right;background:#fff7ed;">Last Mile</th>
                        <th style="min-width:90px;text-align:right;background:#f0fdf4;border-right:2px solid #bbf7d0;">Retail Price</th>
                        @foreach($channels as $ch)
                        <th style="min-width:90px;text-align:center;font-size:.68rem;">
                            <i class="fas fa-store" style="color:#e8a838;font-size:.6rem;display:block;margin-bottom:.1rem;"></i>
                            {{ $ch->name }}
                            ({{ $channelFactors[$ch->id]['factor'] ?? 1.0 }})
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $idx => $item)
                    @php
                        $ex = $item['existing'];
                        $wsp = floatval($item['wsp']);
                        $lastMile = $ex ? floatval($ex->last_mile ?? 0) : 0;
                        $retailPrice = $wsp + $lastMile;
                    @endphp
                    <tr>
                        <td style="font-family:monospace;font-weight:600;font-size:.78rem;">
                            {{ $item['sku'] }}
                            <input type="hidden" name="pricing[{{ $idx }}][product_id]" value="{{ $item['product_id'] }}">
                            <input type="hidden" name="pricing[{{ $idx }}][fob]" value="{{ $item['fob'] }}">
                            <input type="hidden" name="pricing[{{ $idx }}][wsp]" value="{{ $wsp }}">
                        </td>
                        <td style="font-family:monospace;font-size:.72rem;color:#64748b;">{{ $item['sap_code'] ?: '—' }}</td>
                        <td style="font-size:.75rem;">{{ $item['vendor_name'] }}</td>
                        <td style="text-align:center;font-weight:600;">{{ $item['quantity'] }}</td>
                        <td style="text-align:right;font-family:monospace;">${{ number_format($item['fob'], 2) }}</td>
                        <td style="text-align:right;font-family:monospace;font-weight:600;">${{ number_format($wsp, 2) }}</td>
                        <td style="text-align:right;background:#fff7ed;">
                            <input type="number" step="0.01" min="0"
                                name="pricing[{{ $idx }}][last_mile]"
                                value="{{ $lastMile ?: '' }}"
                                placeholder="0.00"
                                class="last-mile-input"
                                data-idx="{{ $idx }}"
                                data-wsp="{{ $wsp }}"
                                onchange="updateRow({{ $idx }}, {{ $wsp }})"
                                style="width:70px;padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;">
                        </td>
                        <td style="text-align:right;font-family:monospace;font-weight:700;color:#166534;background:#f0fdf4;border-right:2px solid #bbf7d0;" id="retail-{{ $idx }}">
                            ${{ number_format($retailPrice, 2) }}
                            <input type="hidden" name="pricing[{{ $idx }}][retail_price]" id="retail-val-{{ $idx }}" value="{{ $retailPrice }}">
                        </td>
                        @foreach($channels as $ch)
                        @php
                            $factor = floatval($channelFactors[$ch->id]['factor'] ?? 1.0);
                            $channelPrice = round($wsp * $factor, 2);
                        @endphp
                        <td style="text-align:right;font-family:monospace;font-size:.78rem;" id="ch-{{ $idx }}-{{ $ch->id }}">
                            ${{ number_format($channelPrice, 2) }}
                            <input type="hidden" name="pricing[{{ $idx }}][channels][{{ $ch->id }}][sales_channel_id]" value="{{ $ch->id }}">
                            <input type="hidden" name="pricing[{{ $idx }}][channels][{{ $ch->id }}][pricing_factor]" value="{{ $factor }}">
                            <input type="hidden" name="pricing[{{ $idx }}][channels][{{ $ch->id }}][channel_price]" id="ch-val-{{ $idx }}-{{ $ch->id }}" value="{{ $channelPrice }}">
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:.72rem;color:#64748b;">{{ $items->count() }} products × {{ $channels->count() }} channels = {{ $items->count() * $channels->count() }} price points</span>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Submit pricing for Finance review?')"><i class="fas fa-save" style="margin-right:.3rem;"></i> Save Pricing</button>
        </div>
    </div>
</form>

{{-- Legend --}}
<div style="margin-top:.75rem;display:flex;gap:1.5rem;font-size:.72rem;color:#64748b;">
    <span style="padding:.2rem .5rem;background:#fff7ed;border-radius:4px;">🟠 Last Mile = manual entry</span>
    <span style="padding:.2rem .5rem;background:#f0fdf4;border-radius:4px;">🟢 Retail Price = WSP + Last Mile</span>
    <span style="padding:.2rem .5rem;background:#fefce8;border-radius:4px;">🟡 Channel Price = WSP × Pricing Factor</span>
</div>

@push('scripts')
<script>
// Channel factors keyed by channel id
var channelFactors = @json(collect($channelFactors)->mapWithKeys(fn($v, $k) => [$k => $v['factor']]));

function updateRow(idx, wsp) {
    var lastMile = parseFloat(document.querySelector('[name="pricing[' + idx + '][last_mile]"]').value) || 0;
    var retail = (wsp + lastMile).toFixed(2);

    // Update retail price display
    document.getElementById('retail-' + idx).innerHTML = '$' + retail + '<input type="hidden" name="pricing[' + idx + '][retail_price]" id="retail-val-' + idx + '" value="' + retail + '">';

    // Channel prices are WSP * factor (not affected by last mile)
    // But recalc in case we want to show updated values
    Object.keys(channelFactors).forEach(function(chId) {
        var factor = channelFactors[chId];
        var chPrice = (wsp * factor).toFixed(2);
        var el = document.getElementById('ch-' + idx + '-' + chId);
        if (el) {
            el.innerHTML = '$' + chPrice +
                '<input type="hidden" name="pricing[' + idx + '][channels][' + chId + '][sales_channel_id]" value="' + chId + '">' +
                '<input type="hidden" name="pricing[' + idx + '][channels][' + chId + '][pricing_factor]" value="' + factor + '">' +
                '<input type="hidden" name="pricing[' + idx + '][channels][' + chId + '][channel_price]" id="ch-val-' + idx + '-' + chId + '" value="' + chPrice + '">';
        }
    });
}
</script>
@endpush
@endsection
