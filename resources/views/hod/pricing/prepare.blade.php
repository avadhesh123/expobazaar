@extends('layouts.app')
@section('title', 'Prepare Pricing')
@section('page-title', 'Pricing — ' . $asn->asn_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <a href="{{ route('hod.asn-list') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> ASN List</a>
    <a href="{{ route('hod.pricing.download', $asn) }}" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Download Pricing CSV</a>
    <a href="{{ route('hod.pricing.last-mile-template', $asn) }}" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Last Mile Template</a>
    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('lmUploadPanel').style.display=document.getElementById('lmUploadPanel').style.display==='none'?'block':'none'"><i class="fas fa-upload"></i> Upload Last Mile CSV</button>
    <a href="{{ route('hod.pricing.status', $asn) }}" class="btn btn-outline btn-sm" style="margin-left:auto;"><i class="fas fa-chart-bar"></i> Pricing Status</a>
</div>

{{-- Last Mile CSV Upload Panel --}}
<div id="lmUploadPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#e8a838;">
        <div class="card-body" style="padding:.85rem 1.4rem;">
            <div style="font-size:.88rem;font-weight:700;color:#854d0e;margin-bottom:.5rem;"><i class="fas fa-upload"></i> Bulk Update Last Mile from CSV</div>
            <div style="font-size:.72rem;color:#64748b;margin-bottom:.5rem;">
                <strong>Steps:</strong> 1) Download the <a href="{{ route('hod.pricing.last-mile-template', $asn) }}" style="color:#1e40af;">Last Mile Template</a> →
                2) Fill the "Last Mile" column for each SKU → 3) Upload the file below.
                <br>CSV must have columns: <strong>SKU</strong> and <strong>Last Mile</strong>. SKUs are matched case-insensitively.
            </div>
            <form method="POST" action="{{ route('hod.pricing.last-mile-upload', $asn) }}" enctype="multipart/form-data" style="display:flex;gap:.6rem;align-items:flex-end;">
                @csrf
                <div><label style="font-size:.68rem;font-weight:600;color:#854d0e;">CSV / XLSX File *</label><input type="file" name="last_mile_file" required accept=".csv,.xlsx,.txt" style="font-size:.78rem;"></div>
                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Upload and update Last Mile values for all matching SKUs?')"><i class="fas fa-upload"></i> Upload & Update</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('lmUploadPanel').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>
</div>

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
                    <tr style="background:#f0f4f8;">
                        <th style="min-width:110px;">SKU</th>
                        <th style="min-width:80px;">SAP</th>
                        <th style="min-width:130px;">Vendor Name</th>
                        <th style="min-width:50px;text-align:center;">Qty</th>
                        <th style="min-width:70px;text-align:center;">FOB</th>
                        <th style="min-width:70px;text-align:center;">WSP</th>
                        <th style="min-width:80px;text-align:center;background:#fff7ed;">Last Mile</th>
                        <th style="min-width:90px;text-align:center;background:#f0fdf4;border-right:2px solid #bbf7d0;">Retail Price</th>
                        @foreach($channels as $ch)
                        @php $factor = floatval($channelFactors[$ch->id]['factor'] ?? 1.0); @endphp
                        <th style="min-width:100px;text-align:center;font-size:.68rem;padding:.4rem .3rem;">
                            <div style="font-size:.65rem;color:#64748b;margin-bottom:.15rem;">{{ $ch->name }}</div>
                            <input type="number" step="0.01" min="0.01" max="10"
                                value="{{ $factor }}"
                                class="factor-input"
                                data-channel-id="{{ $ch->id }}"
                                style="width:60px;padding:.15rem .25rem;border:1px solid #d1d5db;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:center;font-weight:700;color:#854d0e;background:#fefce8;"
                                title="Pricing factor for {{ $ch->name }} — editable, auto-saves">
                            <div class="factor-status-{{ $ch->id }}" style="font-size:.5rem;margin-top:.1rem;height:.7rem;color:#94a3b8;"></div>
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
                                data-idx="{{ $idx }}"
                                data-wsp="{{ $wsp }}"
                                onchange="updateRow({{ $idx }}, {{ $wsp }})"
                                oninput="updateRow({{ $idx }}, {{ $wsp }})"
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
                            <input type="hidden" name="pricing[{{ $idx }}][channels][{{ $ch->id }}][pricing_factor]" id="factor-hidden-{{ $idx }}-{{ $ch->id }}" value="{{ $factor }}">
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

<div style="margin-top:.75rem;display:flex;gap:1.5rem;font-size:.72rem;color:#64748b;">
    <span style="padding:.2rem .5rem;background:#fff7ed;border-radius:4px;">🟠 Last Mile = manual entry</span>
    <span style="padding:.2rem .5rem;background:#f0fdf4;border-radius:4px;">🟢 Retail Price = WSP + Last Mile</span>
    <span style="padding:.2rem .5rem;background:#fefce8;border-radius:4px;">🟡 Channel Price = WSP × Factor (editable in header, auto-saved)</span>
</div>

<script>
   
const channelFactors = @json(
    collect($channelFactors ?? [])
            ->mapWithKeys(function ($item, $key) {
                return [$key => $item['factor'] ?? $item['pricing_factor'] ?? 1.0];
            })
);

//console.log('Raw $channelFactors from PHP:', @json($channelFactors));

//console.log('Channel Factors (Fixed):', channelFactors);

    // Total Rows Count
    var totalRows = {{ $items->count() }};
    var csrfToken = '{{ csrf_token() }}';
    var factorSaveUrl = "{{ route('hod.pricing.update-channel-factor', $asn) }}";

    function updateRow(idx, wsp) {
        var lastMile = parseFloat(document.querySelector('[name="pricing[' + idx + '][last_mile]"]').value) || 0;
        var retail = (parseFloat(wsp) + parseFloat(lastMile)).toFixed(2);
        document.getElementById('retail-' + idx).innerHTML =
            '$' + retail + '<input type="hidden" name="pricing[' + idx + '][retail_price]" id="retail-val-' + idx + '" value="' + retail + '">';
        Object.keys(channelFactors).forEach(function(chId) {
            rebuildChannelCell(idx, chId, wsp);
        });
    }

    function rebuildChannelCell(idx, chId, wsp) {
        var lastMile = parseFloat(document.querySelector('[name="pricing[' + idx + '][last_mile]"]').value) || 0;
        var factor = channelFactors[chId];
        var chPrice = ((wsp * factor)+lastMile).toFixed(2);
        var el = document.getElementById('ch-' + idx + '-' + chId);
        if (el) {

            console.log('Updating row', idx, 'WSP:', wsp, 'factor:', factor, 'Channel Price:', parseFloat(chPrice).toFixed(2));

            el.innerHTML = '$' + chPrice +
                '<input type="hidden" name="pricing[' + idx + '][channels][' + chId + '][sales_channel_id]" value="' + chId + '">' +
                '<input type="hidden" name="pricing[' + idx + '][channels][' + chId + '][pricing_factor]" id="factor-hidden-' + idx + '-' + chId + '" value="' + factor + '">' +
                '<input type="hidden" name="pricing[' + idx + '][channels][' + chId + '][channel_price]" id="ch-val-' + idx + '-' + chId + '" value="' + chPrice + '">';
        }
    }

    function recalcChannel(chId, newFactor) {
        channelFactors[chId] = newFactor;
        for (var idx = 0; idx < totalRows; idx++) {
            var wspInput = document.querySelector('[name="pricing[' + idx + '][wsp]"]');
            if (!wspInput) continue;
            rebuildChannelCell(idx, chId, parseFloat(wspInput.value) || 0);
        }
    }

    // ── Debounced AJAX auto-save for factor inputs ──
    var factorTimers = {};

    document.querySelectorAll('.factor-input').forEach(function(input) {
        input.addEventListener('input', function() {
            var chId = this.getAttribute('data-channel-id');
            var newFactor = parseFloat(this.value);
            if (!newFactor || newFactor <= 0) return;

            // Instantly recalculate all rows
            recalcChannel(chId, newFactor);

            var statusEl = document.querySelector('.factor-status-' + chId);
            if (statusEl) {
                statusEl.textContent = 'saving...';
                statusEl.style.color = '#e8a838';
            }

            // Debounce AJAX save (500ms after last keystroke)
            clearTimeout(factorTimers[chId]);
            factorTimers[chId] = setTimeout(function() {
                fetch(factorSaveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            channel_id: chId,
                            factor: newFactor
                        })
                    })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(data) {
                        if (statusEl) {
                            if (data.success) {
                                statusEl.textContent = '✓ saved';
                                statusEl.style.color = '#16a34a';
                                input.style.borderColor = '#86efac';
                                input.style.background = '#f0fdf4';
                                setTimeout(function() {
                                    statusEl.textContent = '';
                                    input.style.borderColor = '#d1d5db';
                                    input.style.background = '#fefce8';
                                }, 2000);
                            } else {
                                statusEl.textContent = '✗ failed';
                                statusEl.style.color = '#dc2626';
                            }
                        }
                    })
                    .catch(function() {
                        if (statusEl) {
                            statusEl.textContent = '✗ error';
                            statusEl.style.color = '#dc2626';
                        }
                    });
            }, 500);
        });
    });
</script>
@endsection