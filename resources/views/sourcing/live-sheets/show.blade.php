@extends('layouts.app')
@section('title', 'Live Sheet: ' . $liveSheet->live_sheet_number)
@section('page-title', 'Live Sheet — ' . $liveSheet->live_sheet_number)

@section('content')

@php
$disabled = $liveSheet->is_locked ? 'disabled' : '';
@endphp
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('sourcing.live-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Live Sheets</a>
    @if($liveSheet->status === 'submitted' || $liveSheet->status === 'draft' && !$liveSheet->is_locked)
    @if($liveSheet->canBeLocked())
    <form method="POST" action="{{ route('sourcing.live-sheets.approve', $liveSheet) }}" style="display:inline;" onsubmit="return confirm('Approve and lock this live sheet?')">
        @csrf<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-lock"></i> Approve & Lock</button>
    </form>
    @else
    <button class="btn btn-secondary" disabled>Approve & Lock (Waiting for SAP codes)</button>
    @endif
    @endif
    @if($liveSheet->is_locked && !$liveSheet->consignment)
    <form method="POST" action="{{ route('sourcing.live-sheets.create-consignment', $liveSheet) }}" style="display:inline;" onsubmit="return confirm('Create consignment from this live sheet?')">@csrf<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-box"></i> Create Consignment</button></form>
    @endif
</div>

{{-- Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem;">
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Live Sheet</div>
                <div style="font-weight:700;font-family:monospace;">{{ $liveSheet->live_sheet_number }}</div>
            </div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Vendor</div>
                <div style="font-weight:600;">{{ $liveSheet->vendor->company_name ?? '—' }}</div>
            </div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Offer Sheet</div>
                <div>{{ $liveSheet->offerSheet->offer_sheet_number ?? '—' }}</div>
            </div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Company</div>
                <div>{{ $liveSheet->company_code }}</div>
            </div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Total CBM</div>
                <div style="font-weight:700;font-family:monospace;">{{ number_format($liveSheet->total_cbm, 3) }}</div>
            </div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Status</div>
                <div>
                    <span class="badge {{ ['draft'=>'badge-gray','submitted'=>'badge-warning','locked'=>'badge-success'][$liveSheet->status] ?? 'badge-gray' }}">{{ $liveSheet->is_locked ? 'Locked' : ucfirst($liveSheet->status) }}</span>
                </div>
            </div>
            @if($liveSheet->consignment)
            <div style="padding:.5rem;background:#f0fdf4;border-radius:8px;">
                <div style="font-size:.62rem;color:#166534;text-transform:uppercase;font-weight:600;">Consignment</div>
                <div style="font-weight:700;font-family:monospace;color:#166534;">{{ $liveSheet->consignment->consignment_number }}</div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Selection info bar --}}
<div style="padding:.65rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1rem;font-size:.82rem;color:#1e40af;display:flex;justify-content:space-between;align-items:center;">
    <span><i class="fas fa-info-circle" style="margin-right:.3rem;"></i> <strong><span id="lsSelectedCount">0</span></strong> of {{ $liveSheet->items->count() }} items selected. Only selected items will be moved to the consignment.</span>
    <span style="font-size:.72rem;color:#64748b;">Unchecked rows will be excluded from consignment creation.</span>
</div>

{{-- Bulk fill panel --}}
@if(!$liveSheet->is_locked)
<div class="card" style="margin-bottom:1rem;border-color:#fcd34d;">
    <div class="card-header" style="background:#fffbeb;padding:.65rem 1rem;">
        <h3 style="font-size:.82rem;color:#92400e;"><i class="fas fa-fill-drip" style="margin-right:.4rem;"></i> Bulk Fill — Apply same value to all rows</h3>
    </div>
    <div class="card-body" style="padding:.75rem 1rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div>
            <label style="font-size:.68rem;font-weight:700;color:#64748b;text-transform:uppercase;display:block;margin-bottom:.2rem;">Freight Factor</label>
            <div style="display:flex;gap:.3rem;">
                <input type="number" id="bulkFreightFactor" step="0.01" min="0" placeholder="0.00" style="width:90px;padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;">
                <button type="button" onclick="bulkFill('freight_factor','bulkFreightFactor')" class="btn btn-outline btn-sm"><i class="fas fa-arrow-right"></i> Apply to All</button>
            </div>
        </div>
        <div>
            <label style="font-size:.68rem;font-weight:700;color:#64748b;text-transform:uppercase;display:block;margin-bottom:.2rem;">WSP Factor</label>
            <div style="display:flex;gap:.3rem;">
                <input type="number" id="bulkWspFactor" step="0.01" min="0" placeholder="0.00" style="width:90px;padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;">
                <button type="button" onclick="bulkFill('wsp_factor','bulkWspFactor')" class="btn btn-outline btn-sm"><i class="fas fa-arrow-right"></i> Apply to All</button>
            </div>
        </div>
        <div>
            <label style="font-size:.68rem;font-weight:700;color:#64748b;text-transform:uppercase;display:block;margin-bottom:.2rem;">Target FOB ($)</label>
            <div style="display:flex;gap:.3rem;">
                <input type="number" id="bulkTargetFob" step="0.01" min="0" placeholder="0.00" style="width:90px;padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;">
                <button type="button" onclick="bulkFill('target_fob','bulkTargetFob')" class="btn btn-outline btn-sm"><i class="fas fa-arrow-right"></i> Apply to All</button>
            </div>
        </div>
        <div style="margin-left:auto;align-self:center;font-size:.7rem;color:#64748b;"><i class="fas fa-info-circle"></i> Only applies to selected (checked) rows.</div>
    </div>
</div>

<script>
    function bulkFill(fieldName, sourceId) {
        var val = document.getElementById(sourceId).value;
        if (val === '' || val === null) {
            alert('Please enter a value to apply.');
            return;
        }
        var applied = 0;
        // Loop through all rows — only apply to rows where the selection checkbox is checked
        document.querySelectorAll('.ls-select').forEach(function(cb, idx) {
            if (!cb.checked) return;
            var input = document.querySelector('input[name="items[' + idx + '][' + fieldName + ']"]');
            if (input) {
                input.value = val;
                input.style.background = '#fef9c3';
                setTimeout(function() {
                    input.style.background = '#fff';
                }, 800);
                applied++;
            }
        });
        if (applied === 0) {
            alert('No selected rows found. Please select rows first.');
        }
    }
</script>
@endif

{{-- Sourcing Editable Fields Form --}}
<form method="POST" action="{{ route('sourcing.live-sheets.update-sourcing', $liveSheet) }}" id="sourcingForm">
    @csrf
    <script>
        function toggleRowSelected(idx, checked) {
            var row = document.getElementById('ls-row-' + idx);
            if (row) {
                row.style.background = checked ? '#f0fdf4' : '#fef2f2';
                row.style.opacity = checked ? '1' : '.7';
                var sticky = row.querySelector('td[style*="sticky"]');
                if (sticky) sticky.style.background = checked ? '#f0fdf4' : '#fef2f2';
            }
            lsUpdateCount();
        }

        function toggleAllItems(checked) {
            document.querySelectorAll('.ls-select').forEach(function(cb, i) {
                cb.checked = checked;
                toggleRowSelected(i, checked);
            });
        }

        function lsUpdateCount() {
            var n = document.querySelectorAll('.ls-select:checked').length;
            var el = document.getElementById('lsSelectedCount');
            if (el) el.textContent = n;
        }
        document.addEventListener('DOMContentLoaded', lsUpdateCount);
    </script>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-table" style="margin-right:.5rem;color:#1e3a5f;"></i> Product Details — All Columns ({{ $liveSheet->items->count() }})</h3>
            <span style="font-size:.72rem;color:#64748b;">Scroll right → Blue columns are editable by Sourcing</span>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table" style="min-width:3400px;font-size:.78rem;">
                <thead>
                    <tr style="background:#f0f4f8;">
                        <th style="min-width:40px;position:sticky;left:0;background:#dbeafe;z-index:2;text-align:center;">
                            <input type="checkbox" id="masterSelect" onchange="toggleAllItems(this.checked)" style="width:16px;height:16px;accent-color:#16a34a;" title="Select All">
                        </th>
                        <th style="min-width:30px;position:sticky;left:40px;background:#f0f4f8;z-index:2;">S.no</th>
                        <th style="min-width:100px;position:sticky;left:70px;background:#f0f4f8;z-index:2;">Vendor SKU</th>
                        <th style="min-width:80px;">SAP Code</th>
                        <th style="min-width:100px;">Barcode</th>
                        <th style="min-width:160px;">Product Name</th>
                        <th style="min-width:120px;">Description</th>
                        <th style="min-width:80px;">HSN/HTS</th>
                        <th style="min-width:55px;">Duty %</th>
                        <th style="min-width:55px;">L (in)</th>
                        <th style="min-width:55px;">W (in)</th>
                        <th style="min-width:55px;">H (in)</th>
                        <th style="min-width:60px;">Wt (g)</th>
                        <th style="min-width:90px;">Material</th>
                        <th style="min-width:80px;">Other Mat.</th>
                        <th style="min-width:60px;">Color</th>
                        <th style="min-width:60px;">Finish</th>
                        <th style="min-width:80px;">Category</th>
                        <th style="min-width:80px;">Sub Cat.</th>
                        <th style="min-width:50px;">Inner Qty</th>
                        <th style="min-width:55px;">Inner L</th>
                        <th style="min-width:55px;">Inner W</th>
                        <th style="min-width:55px;">Inner H</th>
                        <th style="min-width:55px;">Master Qty</th>
                        <th style="min-width:55px;">Master L</th>
                        <th style="min-width:55px;">Master W</th>
                        <th style="min-width:55px;">Master H</th>
                        <th style="min-width:65px;">Master Wt</th>
                        <th style="min-width:60px;">Qty Offered</th>
                        <th style="min-width:70px;">Vendor FOB</th>
                        <th style="min-width:80px;background:#dbeafe;">Target FOB *</th>
                        <th style="min-width:70px;background:#dbeafe;">Final Qty *</th>
                        <th style="min-width:55px;">Cartons</th>
                        <th style="min-width:65px;">Carton CBM</th>
                        <th style="min-width:65px;">CBM Ship</th>
                        <th style="min-width:80px;background:#dbeafe;">Final FOB *</th>
                        <th style="min-width:55px;">Duty</th>
                        <th style="min-width:70px;background:#dbeafe;">Freight F. *</th>
                        <th style="min-width:60px;">Freight</th>
                        <th style="min-width:70px;">Landed Cost</th>
                        <th style="min-width:70px;background:#dbeafe;">WSP F. *</th>
                        <th style="min-width:60px;">WSP ($)</th>
                        <th style="min-width:120px;background:#dbeafe;">Comments *</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($liveSheet->items as $idx => $item)
                    @php
                    $d = $item->product_details ?? [];
                    $masterL = $d['master_length'] ?? 0;
                    $masterW = $d['master_width'] ?? 0;
                    $masterH = $d['master_height'] ?? 0;
                    $masterCbm = ($masterL && $masterW && $masterH) ? ($masterL * $masterW * $masterH) / 61023 : 0;
                    $qtyMaster = $d['qty_master_pack'] ?? 1;
                    $finalQty = $d['final_qty'] ?? $item->quantity;
                    $totalCartons = $qtyMaster > 0 ? ceil($finalQty / $qtyMaster) : 0;
                    $cbmShipment = $totalCartons * $masterCbm;
                    $finalFob = $d['final_fob'] ?? $item->unit_price;
                    $dutyAmt = $finalFob * (($d['duty_percent'] ?? 0) / 100);
                    $freightAmt = ($d['freight_factor'] ?? 0) * $finalFob;
                    $landedCost = $finalFob + $dutyAmt + $freightAmt;
                    $wsp = $landedCost * ($d['wsp_factor'] ?? 0);
                    @endphp
                    <tr id="ls-row-{{ $idx }}" style="{{ ($item->is_selected ?? 1) ? 'background:#f0fdf4;' : 'background:#fef2f2;opacity:.7;' }}">
                        <td style="text-align:center;position:sticky;left:0;background:{{ ($item->is_selected ?? 1) ? '#f0fdf4' : '#fef2f2' }};z-index:1;">
                            <input type="hidden" name="items[{{ $idx }}][is_selected]" value="0">
                            <input type="checkbox" name="items[{{ $idx }}][is_selected]" value="1" {{ ($item->is_selected ?? 1) ? 'checked' : '' }} onchange="toggleRowSelected({{ $idx }}, this.checked)" class="ls-select" style="width:16px;height:16px;accent-color:#16a34a;">
                        </td>
                        <td style="text-align:center;position:sticky;left:40px;background:#fff;z-index:1;">{{ $d['sno'] ?? $loop->iteration }}</td>
                        <td style="font-family:monospace;font-weight:600;position:sticky;left:70px;background:#fff;z-index:1;">
                            <input type="hidden" name="items[{{ $idx }}][item_id]" value="{{ $item->id }}">
                            {{ $item->product->sku ?? '—' }}
                        </td>
                        <td>{{ $d['sap_code'] ?? '—' }}</td>
                        <td>{{ $d['barcode'] ?? '—' }}</td>
                        <td style="font-weight:500;">{{ $item->product->name ?? '—' }}</td>
                        <td style="font-size:.7rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $d['description'] ?? '' }}">{{ Str::limit($d['description'] ?? '—', 40) }}</td>
                        <td>{{ $d['hsn_hts_code'] ?? '—' }}</td>
                        <td style="text-align:center;">{{ $d['duty_percent'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['length_inches'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['width_inches'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['height_inches'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['weight_grams'] ?? '—' }}</td>
                        <td>{{ $d['material'] ?? '—' }}</td>
                        <td>{{ $d['other_material'] ?? '—' }}</td>
                        <td>{{ $d['color'] ?? '—' }}</td>
                        <td>{{ $d['finish'] ?? '—' }}</td>
                        <td>{{ $d['category'] ?? '—' }}</td>
                        <td>{{ $d['sub_category'] ?? '—' }}</td>
                        <td style="text-align:center;">{{ $d['qty_inner_pack'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['inner_length'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['inner_width'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['inner_height'] ?? '—' }}</td>
                        <td style="text-align:center;">{{ $d['qty_master_pack'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $masterL ?: '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $masterW ?: '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $masterH ?: '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['master_weight_kg'] ?? '—' }}</td>
                        <td style="text-align:center;">{{ $item->quantity }}</td>
                        <td style="font-family:monospace;">${{ number_format($item->unit_price, 2) }}</td>
                        {{-- EDITABLE: Target FOB --}}
                        <td style="background:#eff6ff;"><input {{$disabled }} type="number" step="0.01" name="items[{{ $idx }}][target_fob]" value="{{ $d['target_fob'] ?? '' }}" placeholder="0.00" style="width:70px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;"></td>
                        {{-- EDITABLE: Final Qty --}}
                        <td style="background:#eff6ff;"><input {{$disabled }} type="number" name="items[{{ $idx }}][final_qty]" value="{{ $d['final_qty'] ?? $item->quantity }}" min="1" style="width:60px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;text-align:center;background:#fff;"></td>
                        <td style="text-align:center;">{{ $totalCartons ?: '—' }}</td>
                        <td style="font-family:monospace;">{{ $masterCbm > 0 ? number_format($masterCbm, 4) : '—' }}</td>
                        <td style="font-family:monospace;font-weight:600;color:#1e40af;">{{ $cbmShipment > 0 ? number_format($cbmShipment, 4) : number_format($item->total_cbm, 4) }}</td>
                        {{-- EDITABLE: Final FOB --}}
                        <td style="background:#eff6ff;"><input {{$disabled }} type="number" step="0.01" name="items[{{ $idx }}][final_fob]" value="{{ $d['final_fob'] ?? '' }}" placeholder="0.00" style="width:70px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;"></td>
                        <td style="font-family:monospace;">{{ $dutyAmt > 0 ? '$'.number_format($dutyAmt, 2) : '—' }}</td>
                        {{-- EDITABLE: Freight Factor --}}
                        <td style="background:#eff6ff;"><input {{$disabled }} type="number" step="0.01" name="items[{{ $idx }}][freight_factor]" value="{{ $d['freight_factor'] ?? '' }}" placeholder="0.00" style="width:60px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;"></td>
                        <td style="font-family:monospace;">{{ $freightAmt > 0 ? '$'.number_format($freightAmt, 2) : '—' }}</td>
                        <td style="font-family:monospace;font-weight:600;">{{ $landedCost > 0 ? '$'.number_format($landedCost, 2) : '—' }}</td>
                        {{-- EDITABLE: WSP Factor --}}
                        <td style="background:#eff6ff;"><input {{$disabled }} type="number" step="0.01" name="items[{{ $idx }}][wsp_factor]" value="{{ $d['wsp_factor'] ?? '' }}" placeholder="0.00" style="width:60px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;"></td>
                        <td style="font-family:monospace;font-weight:700;color:#166534;">{{ $wsp > 0 ? '$'.number_format($wsp, 2) : '—' }}</td>
                        {{-- EDITABLE: Comments --}}
                        <td style="background:#eff6ff;"><input {{$disabled }} type="text" name="items[{{ $idx }}][comments]" value="{{ $d['comments'] ?? '' }}" placeholder="..." style="width:110px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;background:#fff;"></td>
                    </tr>
                    @endforeach
                    {{-- Totals --}}
                    <tr style="background:#f8fafc;font-weight:700;">
                        <td colspan="27" style="text-align:right;position:sticky;left:0;background:#f8fafc;">TOTALS</td>
                        <td style="text-align:center;">{{ $liveSheet->items->sum('quantity') }}</td>
                        <td></td>
                        <td colspan="2"></td>
                        <td></td>
                        <td></td>
                        <td style="font-family:monospace;color:#1e40af;">{{ number_format($liveSheet->items->sum('total_cbm'), 3) }}</td>
                        <td colspan="8"></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:1rem;display:flex;gap:.5rem;justify-content:space-between;align-items:flex-end;">
        <div style="flex:1;max-width:400px;">
            <label style="font-size:.72rem;font-weight:600;color:#64748b;">Reason for changes (optional)</label>
            <input type="text" name="change_reason" placeholder="e.g. Price negotiation round 2, Qty adjusted per vendor capacity..." style="width:100%;padding:.4rem .65rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
        </div>
        <div style="display:flex;gap:.5rem;">
            <a href="{{ route('sourcing.live-sheets.history', $liveSheet) }}" class="btn btn-outline"><i class="fas fa-history" style="margin-right:.3rem;"></i> View Change History</a>
            <a href="{{ route('sourcing.live-sheets') }}" class="btn btn-outline">Back</a>
            <button type="submit" class="btn btn-primary" onclick="return confirm('Save sourcing updates?')"><i class="fas fa-save" style="margin-right:.3rem;"></i> Save Sourcing Updates</button>
        </div>
    </div>
</form>
@endsection