@extends('layouts.app')
@section('title', 'Prepare Pricing')
@section('page-title', 'Pricing — ' . $asn->asn_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <a href="{{ route('hod.asn-list') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> ASN List</a>
    <!-- <a href="{{ route('hod.pricing.download', $asn) }}" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Download Pricing CSV</a> -->
    <a href="{{ route('hod.pricing.pricing-input-template', $asn) }}" class="btn btn-outline btn-sm"><i class="fas fa-file-csv"></i> Pricing Input Template</a>
    <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('lmUploadPanel').style.display=document.getElementById('lmUploadPanel').style.display==='none'?'block':'none'"><i class="fas fa-upload"></i> Upload Pricing Input CSV</button>
    <a href="{{ route('hod.pricing.status', $asn) }}" class="btn btn-outline btn-sm" style="margin-left:auto;"><i class="fas fa-chart-bar"></i> Pricing Status</a>
</div>

{{-- Last Mile CSV Upload Panel --}}
<div id="lmUploadPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#e8a838;">
        <div class="card-body" style="padding:.85rem 1.4rem;">
            <div style="font-size:.88rem;font-weight:700;color:#854d0e;margin-bottom:.5rem;"><i class="fas fa-upload"></i> Bulk Update Pricing from CSV</div>
            <div style="font-size:.72rem;color:#64748b;margin-bottom:.5rem;">
                <strong>Steps:</strong> 1) Download the <a href="{{ route('hod.pricing.pricing-input-template', $asn) }}" style="color:#1e40af;">Pricing Input Template</a> →
                2) Fill the relevant columns for each SKU → 3) Upload the file below.
                <br>CSV must have columns: >SKU,Inward,Fulfillment and Storage. SKUs are matched case-insensitively.
            </div>
            <form method="POST" action="{{ route('hod.pricing.pricing-input-upload', $asn) }}" enctype="multipart/form-data" style="display:flex;gap:.6rem;align-items:flex-end;">
                @csrf
                <div><label style="font-size:.68rem;font-weight:600;color:#854d0e;">CSV / XLSX File *</label><input type="file" name="pricing_input_file" required accept=".csv,.xlsx,.txt" style="font-size:.78rem;"></div>
                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Upload and update Pricing values for all matching SKUs?')"><i class="fas fa-upload"></i> Upload & Update</button>
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
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Submit pricing for Cataloging ?')"><i class="fas fa-save"></i> Save Pricing</button>
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
                        <th style="min-width:60px;text-align:center;">FOB</th>
                        <th style="min-width:60px;text-align:center;">WSP</th>
                        <th style="min-width:60px;text-align:center;">Inward</th>
                        <th style="min-width:60px;text-align:center;">Fulfillment</th>
                        <th style="min-width:60px;text-align:center;">Storage</th>
                        <th style="min-width:60px;text-align:center;">Ad Budget(%)</th>
                         <th style="min-width:50px;text-align:center;">Final WSP</th>
                         <th style="min-width:70px;text-align:center;background:#fff7ed;">Last Mile</th>
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
                    $inward = $ex ? floatval($ex->inward ?? 0) : 0;
                    $fulfillment =  $ex ? floatval($ex->fulfillment ?? 0) : 0;
                    $storage = $ex ? floatval($ex->storage ?? 0) : 0;
                    $adBudget = $ex ? floatval($ex->ad_budget ?? 0) : 0;

                      
                    $divisor = 1 - ($adBudget / 100);
                    $finalWsp = ($wsp + $inward + $fulfillment + $storage) / ($divisor > 0 ? $divisor : 1);
                    $finalWsp = number_format($finalWsp, 2);
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
                                name="pricing[{{ $idx }}][inward]"
                                value="{{ $inward ?: '' }}"
                                placeholder="0.00"
                                data-idx="{{ $idx }}"
                                data-wsp="{{ $wsp }}"
                                onchange="updateRow({{ $idx }})"
                                oninput="updateRow({{ $idx }})"
                                style="width:70px;padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;">
                        </td>
                         <td style="text-align:right;background:#fff7ed;">
                            <input type="number" step="0.01" min="0"
                                name="pricing[{{ $idx }}][fulfillment]"
                                value="{{ $fulfillment ?: '' }}"
                                placeholder="0.00"
                                data-idx="{{ $idx }}"
                                data-wsp="{{ $wsp }}"
                                onchange="updateRow({{ $idx }})"
                                oninput="updateRow({{ $idx }})"
                                style="width:70px;padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;">
                        </td>
                         <td style="text-align:right;background:#fff7ed;">
                            <input type="number" step="0.01" min="0"
                                name="pricing[{{ $idx }}][storage]"
                                value="{{ $storage ?: '' }}"
                                placeholder="0.00"
                                data-idx="{{ $idx }}"
                                data-wsp="{{ $wsp }}"
                                onchange="updateRow({{ $idx }})"
                                oninput="updateRow({{ $idx }})"
                                style="width:70px;padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;">
                        </td>
                         <td style="text-align:right;background:#fff7ed;">
                            <input type="number" step="0.01" min="0"
                                name="pricing[{{ $idx }}][ad_budget]"
                                value="{{ $adBudget ?: '' }}"
                                placeholder="0.00"
                                data-idx="{{ $idx }}"
                                data-wsp="{{ $wsp }}"
                                onchange="updateRow({{ $idx }})"
                                oninput="updateRow({{ $idx }})"
                                style="width:70px;padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;">
                        </td> 
                        <td><span style="font-weight:700;" id="final-wsp-label-{{ $idx }}">0.00</span></td>
                        <td style="text-align:right;background:#fff7ed;">
                            <input type="number" step="0.01" min="0"
                                name="pricing[{{ $idx }}][last_mile]"
                                value="{{ $lastMile ?: '' }}"
                                placeholder="0.00"
                                data-idx="{{ $idx }}"
                                data-wsp="{{ $wsp }}"
                                onchange="updateRow({{ $idx }})"
                                oninput="updateRow({{ $idx }})"
                                style="width:70px;padding:.2rem .3rem;border:1px solid #fed7aa;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;">
                        </td>
                        
                        <td style="text-align:right;font-family:monospace;font-weight:700;color:#166534;background:#f0fdf4;border-right:2px solid #bbf7d0;" id="retail-{{ $idx }}">
                            ${{ number_format($retailPrice, 2) }}
                            <input type="hidden" name="pricing[{{ $idx }}][final_wsp]" id="final_wsp-val-{{ $idx }}" value="{{ $finalWsp }}">
                            <input type="hidden" name="pricing[{{ $idx }}][retail_price]" id="retail-val-{{ $idx }}" value="{{ $retailPrice }}">
                        </td>
                        @foreach($channels as $ch)
                        @php
                        $factor = floatval($channelFactors[$ch->id]['factor'] ?? 1.0);
                        $channelType[$ch->id] = $ch->type; // Store channel type for later use in JS
                        $channelPrice = round($wsp * $factor, 2);
                        @endphp
                        <td style="text-align:right;font-family:monospace;font-size:.78rem;" id="ch-{{ $idx }}-{{ $ch->id }}">AAAA
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
    // Global Data
    const channelFactors = @json(
        collect($channelFactors ?? [])
            ->mapWithKeys(fn($item, $key) => [$key => ($item['factor'] ?? $item['pricing_factor'] ?? 1.0)])
    );

    const channelTypes = @json(
        collect($channelFactors ?? [])
            ->mapWithKeys(fn($item, $key) => [$key => $item['type'] ?? 'default'])
    );

    const channelCommissions = @json(
        collect($channelFactors ?? [])
            ->mapWithKeys(fn($item, $key) => [$key => ($item['commission'] ?? 0)])
    );

    const totalRows = {{ $items->count() }};
    const csrfToken = '{{ csrf_token() }}';
    const factorSaveUrl = "{{ route('hod.pricing.update-channel-factor', $asn) }}";

    function updateRow(idx) {
        const wsp = parseFloat(document.querySelector(`[name="pricing[${idx}][wsp]"]`)?.value) || 0;
        const lastMile = parseFloat(document.querySelector(`[name="pricing[${idx}][last_mile]"]`)?.value) || 0;
        const inward = parseFloat(document.querySelector(`[name="pricing[${idx}][inward]"]`)?.value) || 0;
        const fulfillment = parseFloat(document.querySelector(`[name="pricing[${idx}][fulfillment]"]`)?.value) || 0;
        const storage = parseFloat(document.querySelector(`[name="pricing[${idx}][storage]"]`)?.value) || 0;
        const adBudget = parseFloat(document.querySelector(`[name="pricing[${idx}][ad_budget]"]`)?.value) || 0;

        // Final WSP Calculation
        const divisor = 1 - (adBudget / 100);
        let finalWsp = (wsp + inward + fulfillment + storage) / (divisor > 0 ? divisor : 1);
        finalWsp = parseFloat(finalWsp.toFixed(2));

        // Update Final WSP
        const finalWspEl = document.getElementById(`final-wsp-label-${idx}`);
        if (finalWspEl) finalWspEl.textContent = finalWsp.toFixed(2);

        // Retail Price
        const retail = (finalWsp + lastMile).toFixed(2);
        const retailEl = document.getElementById(`retail-${idx}`);
        if (retailEl) {
            retailEl.innerHTML = `${retail}<input type="hidden" name="pricing[${idx}][retail_price]" value="${retail}"><input type="hidden" name="pricing[${idx}][final_wsp]" value="${finalWsp}">`;
        }
            console.log(`Row ${idx} updated: Final WSP = ${finalWsp}, Retail = ${retail}`);
        // Update Channel Prices
        Object.keys(channelFactors).forEach(chId => {
            rebuildChannelCell(idx, parseInt(chId), finalWsp, retail);
        });
    }

    function rebuildChannelCell(idx, chId, finalWsp, retail) {
        const commission = channelCommissions[chId] || 0;
        
        const factor = document.querySelector(`[data-channel-id="${chId}"]`)?.value;

        const wsp = parseFloat(document.querySelector(`[name="pricing[${idx}][wsp]"]`)?.value) || 0;

        let price = (channelTypes[chId] === 'b2b') 
            ? finalWsp / (1 - (commission / 100)) 
            : retail / (1 - (commission / 100));

       // chPrice = parseFloat(chPrice.toFixed(2));
        
        let chPrice = (price * factor).toFixed(2);
        const cell = document.getElementById(`ch-${idx}-${chId}`);
        if (cell) {
            console.log(`rebuildChannelCell ${idx}, Channel ${chId}: Price = ${chPrice} factor = ${factor} commission = ${commission} type = ${channelTypes[chId]}`);
            cell.innerHTML = `$${chPrice}` +
                `<input type="hidden" name="pricing[${idx}][channels][${chId}][sales_channel_id]" value="${chId}">` +
                `<input type="hidden" name="pricing[${idx}][channels][${chId}][pricing_factor]" value="${channelFactors[chId]}">` +
                `<input type="hidden" name="pricing[${idx}][channels][${chId}][channel_price]" value="${chPrice}">`;
        }
    }

    function recalcChannel(chId, newFactor) {
        channelFactors[chId] = parseFloat(newFactor);

        for (let idx = 0; idx < totalRows; idx++) {
            updateRow(idx);
        }
    }

    // Factor Input Handler
    let factorTimers = {};

    document.querySelectorAll('.factor-input').forEach(input => {
        input.addEventListener('input', function() {
            const chId = this.getAttribute('data-channel-id');
            const newFactor = parseFloat(this.value);

            if (!newFactor || newFactor <= 0) return;

            recalcChannel(chId, newFactor);

            // Debounced Save
            clearTimeout(factorTimers[chId]);
            factorTimers[chId] = setTimeout(() => {
                fetch(factorSaveUrl, {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken 
                    },
                    body: JSON.stringify({ channel_id: chId, factor: newFactor })
                });
            }, 600);
        });
    });

    // Initialize on load
    for (let i = 0; i < totalRows; i++) {
        updateRow(i);
    }
</script>
@endsection