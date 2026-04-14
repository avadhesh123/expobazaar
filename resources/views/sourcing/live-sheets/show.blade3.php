@extends('layouts.app')
@section('title', 'Live Sheet: ' . $liveSheet->live_sheet_number)
@section('page-title', 'Live Sheet — ' . $liveSheet->live_sheet_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('sourcing.live-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Live Sheets</a>
    @if($liveSheet->status === 'submitted' && !$liveSheet->is_locked)
        <form method="POST" action="{{ route('sourcing.live-sheets.approve', $liveSheet) }}" style="display:inline;" onsubmit="return confirm('Approve and lock this live sheet?')">@csrf<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-lock"></i> Approve & Lock</button></form>
    @endif
    @if($liveSheet->is_locked && !$liveSheet->consignment)
        <form method="POST" action="{{ route('sourcing.live-sheets.create-consignment', $liveSheet) }}" style="display:inline;" onsubmit="return confirm('Create consignment from this live sheet?')">@csrf<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-box"></i> Create Consignment</button></form>
    @endif
</div>

{{-- Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem;">
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Live Sheet</div><div style="font-weight:700;font-family:monospace;">{{ $liveSheet->live_sheet_number }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Vendor</div><div style="font-weight:600;">{{ $liveSheet->vendor->company_name ?? '—' }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Offer Sheet</div><div>{{ $liveSheet->offerSheet->offer_sheet_number ?? '—' }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Company</div><div>{{ $liveSheet->company_code }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Total CBM</div><div style="font-weight:700;font-family:monospace;">{{ number_format($liveSheet->total_cbm, 3) }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Status</div><div>
                <span class="badge {{ ['draft'=>'badge-gray','submitted'=>'badge-warning','locked'=>'badge-success'][$liveSheet->status] ?? 'badge-gray' }}">{{ $liveSheet->is_locked ? 'Locked' : ucfirst($liveSheet->status) }}</span>
            </div></div>
            @if($liveSheet->consignment)
            <div style="padding:.5rem;background:#f0fdf4;border-radius:8px;"><div style="font-size:.62rem;color:#166534;text-transform:uppercase;font-weight:600;">Consignment</div><div style="font-weight:700;font-family:monospace;color:#166534;">{{ $liveSheet->consignment->consignment_number }}</div></div>
            @endif
        </div>
    </div>
</div>

{{-- Sourcing Editable Fields Form --}}
<form method="POST" action="{{ route('sourcing.live-sheets.update-sourcing', $liveSheet) }}" id="sourcingForm">
    @csrf
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-table" style="margin-right:.5rem;color:#1e3a5f;"></i> Product Details — All Columns ({{ $liveSheet->items->count() }})</h3>
            <span style="font-size:.72rem;color:#64748b;">Scroll right → Blue columns are editable by Sourcing</span>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table" style="min-width:3400px;font-size:.78rem;">
                <thead>
                    <tr style="background:#f0f4f8;">
                        <th style="min-width:30px;position:sticky;left:0;background:#f0f4f8;z-index:2;">S.no</th>
                        <th style="min-width:100px;position:sticky;left:30px;background:#f0f4f8;z-index:2;">Vendor SKU</th>
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
                    <tr>
                        <td style="text-align:center;position:sticky;left:0;background:#fff;z-index:1;">{{ $d['sno'] ?? $loop->iteration }}</td>
                        <td style="font-family:monospace;font-weight:600;position:sticky;left:30px;background:#fff;z-index:1;">
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
                        <td style="background:#eff6ff;"><input type="number" step="0.01" name="items[{{ $idx }}][target_fob]" value="{{ $d['target_fob'] ?? '' }}" placeholder="0.00" style="width:70px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;"></td>
                        {{-- EDITABLE: Final Qty --}}
                        <td style="background:#eff6ff;"><input type="number" name="items[{{ $idx }}][final_qty]" value="{{ $d['final_qty'] ?? $item->quantity }}" min="1" style="width:60px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;text-align:center;background:#fff;"></td>
                        <td style="text-align:center;">{{ $totalCartons ?: '—' }}</td>
                        <td style="font-family:monospace;">{{ $masterCbm > 0 ? number_format($masterCbm, 4) : '—' }}</td>
                        <td style="font-family:monospace;font-weight:600;color:#1e40af;">{{ $cbmShipment > 0 ? number_format($cbmShipment, 4) : number_format($item->total_cbm, 4) }}</td>
                        {{-- EDITABLE: Final FOB --}}
                        <td style="background:#eff6ff;"><input type="number" step="0.01" name="items[{{ $idx }}][final_fob]" value="{{ $d['final_fob'] ?? '' }}" placeholder="0.00" style="width:70px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;"></td>
                        <td style="font-family:monospace;">{{ $dutyAmt > 0 ? '$'.number_format($dutyAmt, 2) : '—' }}</td>
                        {{-- EDITABLE: Freight Factor --}}
                        <td style="background:#eff6ff;"><input type="number" step="0.01" name="items[{{ $idx }}][freight_factor]" value="{{ $d['freight_factor'] ?? '' }}" placeholder="0.00" style="width:60px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;"></td>
                        <td style="font-family:monospace;">{{ $freightAmt > 0 ? '$'.number_format($freightAmt, 2) : '—' }}</td>
                        <td style="font-family:monospace;font-weight:600;">{{ $landedCost > 0 ? '$'.number_format($landedCost, 2) : '—' }}</td>
                        {{-- EDITABLE: WSP Factor --}}
                        <td style="background:#eff6ff;"><input type="number" step="0.01" name="items[{{ $idx }}][wsp_factor]" value="{{ $d['wsp_factor'] ?? '' }}" placeholder="0.00" style="width:60px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;"></td>
                        <td style="font-family:monospace;font-weight:700;color:#166534;">{{ $wsp > 0 ? '$'.number_format($wsp, 2) : '—' }}</td>
                        {{-- EDITABLE: Comments --}}
                        <td style="background:#eff6ff;"><input type="text" name="items[{{ $idx }}][comments]" value="{{ $d['comments'] ?? '' }}" placeholder="..." style="width:110px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;background:#fff;"></td>
                    </tr>
                    @endforeach
                    {{-- Totals --}}
                    <tr style="background:#f8fafc;font-weight:700;">
                        <td colspan="27" style="text-align:right;position:sticky;left:0;background:#f8fafc;">TOTALS</td>
                        <td style="text-align:center;">{{ $liveSheet->items->sum('quantity') }}</td>
                        <td></td><td colspan="2"></td>
                        <td></td><td></td>
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
