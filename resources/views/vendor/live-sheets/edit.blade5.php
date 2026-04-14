@extends('layouts.app')
@section('title', 'Fill Live Sheet')
@section('page-title', 'Live Sheet — ' . $liveSheet->live_sheet_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('vendor.live-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Live Sheets</a>
    <a href="{{ route('vendor.live-sheets.download', $liveSheet) }}" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Download Pre-filled CSV</a>
    <a href="{{ route('vendor.live-sheets.blank-template') }}" class="btn btn-outline btn-sm"><i class="fas fa-file-excel" style="color:#16a34a;"></i> Blank Template</a>
</div>

@if($liveSheet->is_locked)
<div style="padding:.85rem 1.2rem;background:#fee2e2;border-radius:10px;border:1px solid #fecaca;margin-bottom:1.25rem;font-size:.85rem;color:#dc2626;font-weight:600;"><i class="fas fa-lock" style="margin-right:.3rem;"></i> This live sheet is locked. Contact Admin to unlock for changes.</div>
@endif

@if(session('upload_errors') && count(session('upload_errors')) > 0)
<div class="card" style="margin-bottom:1rem;border-color:#fca5a5;">
    <div class="card-body" style="padding:.65rem 1rem;max-height:150px;overflow-y:auto;">
        @foreach(session('upload_errors') as $err)
        <div style="font-size:.78rem;color:#991b1b;padding:.15rem 0;">{{ $err }}</div>
        @endforeach
    </div>
</div>
@endif

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem;">
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Live Sheet</div><div style="font-weight:700;font-family:monospace;">{{ $liveSheet->live_sheet_number }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Offer Sheet</div><div style="font-weight:600;">{{ $liveSheet->offerSheet->offer_sheet_number ?? '—' }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Company</div><div>{{ $liveSheet->company_code ?? '—' }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Status</div><div><span class="badge {{ $liveSheet->is_locked?'badge-success':'badge-warning' }}">{{ $liveSheet->is_locked?'Locked':ucfirst($liveSheet->status) }}</span></div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Total CBM</div><div style="font-weight:700;font-family:monospace;">{{ number_format($liveSheet->total_cbm, 3) }}</div></div>
        </div>
    </div>
</div>

@if(!$liveSheet->is_locked)
<div class="card" style="margin-bottom:1.25rem;border-color:#16a34a;">
    <div class="card-header" style="background:#f0fdf4;"><h3 style="color:#166534;"><i class="fas fa-file-upload" style="margin-right:.5rem;"></i> Upload Filled Live Sheet</h3></div>
    <div class="card-body">
        <div style="font-size:.78rem;color:#64748b;margin-bottom:.75rem;"><strong>Steps:</strong> 1) Download pre-filled CSV or blank template → 2) Fill all columns → 3) Upload here. Items matched by <strong>Vendor SKU</strong>.</div>
        <form method="POST" action="{{ route('vendor.live-sheets.upload', $liveSheet) }}" enctype="multipart/form-data" style="display:flex;gap:.75rem;align-items:flex-end;">
            @csrf
            <div style="flex:1;"><label style="font-size:.72rem;font-weight:600;color:#166534;">File (Excel/CSV) *</label><input type="file" name="live_sheet_file" required accept=".xlsx,.xls,.csv" style="font-size:.82rem;"></div>
            <button type="submit" class="btn btn-success" onclick="return confirm('Upload and update items by SKU?')"><i class="fas fa-upload" style="margin-right:.3rem;"></i> Upload & Update</button>
        </form>
    </div>
</div>
@endif

<div style="padding:.65rem 1rem;background:#fefce8;border-radius:8px;border:1px solid #fde68a;margin-bottom:1rem;font-size:.78rem;color:#854d0e;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
    <strong>44 columns:</strong> SKU, Barcode, SAP Code, Description, HSN, Duty, Dimensions, Weight, Material, Color, Finish, Inner/Master Pack, Qty, FOB, Freight, Landed Cost, WSP, Comments. Upload via Excel or edit key fields below.
</div>

<form method="POST" action="{{ route('vendor.live-sheets.submit', $liveSheet) }}">
    @csrf
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-list" style="margin-right:.5rem;color:#e8a838;"></i> Product Details ({{ $liveSheet->items->count() }})</h3></div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table">
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
                        <th style="min-width:60px;background:#eff6ff;">Qty *</th>
                        <th style="min-width:70px;background:#eff6ff;">FOB ($) *</th>
                        <th style="min-width:70px;">Target FOB</th>
                        <th style="min-width:60px;">Final Qty</th>
                        <th style="min-width:55px;">Cartons</th>
                        <th style="min-width:65px;">Carton CBM</th>
                        <th style="min-width:65px;">CBM Ship</th>
                        <th style="min-width:70px;">Final FOB</th>
                        <th style="min-width:55px;">Duty</th>
                        <th style="min-width:55px;">Freight F.</th>
                        <th style="min-width:60px;">Freight</th>
                        <th style="min-width:70px;">Landed Cost</th>
                        <th style="min-width:55px;">WSP F.</th>
                        <th style="min-width:60px;">WSP ($)</th>
                        <th style="min-width:100px;">Comments</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($liveSheet->items as $idx => $item)
                    @php $d = $item->product_details ?? []; $dis = $liveSheet->is_locked ? 'disabled' : ''; @endphp
                     <tr>
                        <td style="text-align:center;position:sticky;left:0;background:#fff;z-index:1;">{{ $d['sno'] ?? $loop->iteration }}</td>
                        <td style="font-family:monospace;font-weight:600;position:sticky;left:30px;background:#fff;z-index:1;">
                            <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item->product_id }}">
                            {{ $item->product->sku ?? '—' }}
                        </td>
                        <td>{{ $d['sap_code'] ?? '—' }}</td>
                        <td>{{ $d['barcode'] ?? '—' }}</td>
                        <td style="font-weight:500;">{{ $item->product->name ?? '—' }}</td>
                        <td style="font-size:.7rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $d['description'] ?? '' }}">{{ Str::limit($d['description'] ?? '—', 40) }}</td>
                        <td>{{ $d['hsn_hts_code'] ?? '—' }}</td>
                        <td style="text-align:center;">-</td>
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
                        <td style="text-align:center;font-family:monospace;">{{ $d['master_length'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['master_width'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['master_height'] ?? '—' }}</td>
                        <td style="text-align:center;font-family:monospace;">{{ $d['master_weight_kg'] ?? '—' }}</td>
                        {{-- Editable fields --}}
                        <td style="background:#eff6ff;"><input type="number" name="items[{{ $idx }}][quantity]" value="{{ $item->quantity }}" min="1" required onchange="calcRow({{ $idx }})" style="width:55px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;text-align:center;background:#fff;" {{ $dis }}></td>
                        <td style="background:#eff6ff;"><input type="number" step="0.01" name="items[{{ $idx }}][unit_price]" value="{{ $item->unit_price }}" min="0" required onchange="calcRow({{ $idx }})" style="width:65px;padding:.2rem .3rem;border:1px solid #93c5fd;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;" {{ $dis }}></td>
                        <td style="font-family:monospace;color:#64748b;">{{ $d['target_fob'] ?? '—' }}</td>
                        <td style="font-family:monospace;font-weight:600;">{{ $d['final_qty'] ?? $item->quantity }}</td>
                        <td style="text-align:center;">{{ $d['total_master_cartons'] ?? '—' }}</td>
                        <td style="font-family:monospace;">{{ isset($d['master_cbm']) ? number_format($d['master_cbm'], 4) : '—' }}</td>
                        <td style="font-family:monospace;font-weight:600;color:#1e40af;">{{ isset($d['cbm_shipment']) ? number_format($d['cbm_shipment'], 4) : number_format($item->total_cbm, 4) }}</td>
                        <td style="font-family:monospace;">{{ $d['final_fob'] ?? '—' }}</td>
                        <td style="font-family:monospace;">-</td>
                        <td>-</td>
                        <td style="font-family:monospace;">-</td>
                        <td style="font-family:monospace;font-weight:600;">
                            @php
                                $ff = $d['final_fob'] ?? 0;
                                $duty = $ff * 1 / 100;
                                $freight = 1;
                                $landed = $ff + $duty + $freight;
                            @endphp
                            {{ $landed > 0 ? '$'.number_format($landed, 2) : '—' }}
                        </td>
                        <td>{{ $d['wsp_factor'] ?? '—' }}</td>
                        <td style="font-family:monospace;font-weight:700;color:#166534;">{{ $landed > 0 && ($d['wsp_factor'] ?? 0) > 0 ? '$'.number_format($landed * $d['wsp_factor'], 2) : '—' }}</td>
                        <td style="font-size:.7rem;color:#64748b;">{{ $d['comments'] ?? '—' }}</td>
                    </tr>
                  
                    @endforeach
                    <tr style="background:#f8fafc;font-weight:700;">
                        <td colspan="3" style="text-align:right;">TOTALS</td>
                        <td style="text-align:center;" id="grandQty">{{ $liveSheet->items->sum('quantity') }}</td>
                        <td></td><td style="font-family:monospace;" id="grandPrice">${{ number_format($liveSheet->items->sum('total_price'), 2) }}</td>
                        <td></td><td style="font-family:monospace;" id="grandCbm">{{ number_format($liveSheet->items->sum('total_cbm'), 3) }}</td>
                        <td></td><td style="font-family:monospace;" id="grandWeight">{{ number_format($liveSheet->items->sum('total_weight'), 2) }} kg</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @if(!$liveSheet->is_locked)
    <div style="margin-top:1rem;display:flex;gap:.5rem;justify-content:flex-end;">
        <a href="{{ route('vendor.live-sheets.history', $liveSheet) }}" class="btn btn-outline"><i class="fas fa-history"></i> View History</a>
        <a href="{{ route('vendor.live-sheets') }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Submit live sheet for Sourcing approval?')"><i class="fas fa-paper-plane" style="margin-right:.3rem;"></i> Submit Live Sheet</button>
    </div>
    @endif
</form>

@push('scripts')
<script>
function calcRow(idx){var q=parseFloat(document.querySelector('[name="items['+idx+'][quantity]"]').value)||0,p=parseFloat(document.querySelector('[name="items['+idx+'][unit_price]"]').value)||0,c=parseFloat(document.querySelector('[name="items['+idx+'][cbm_per_unit]"]').value)||0,w=parseFloat(document.querySelector('[name="items['+idx+'][weight_per_unit]"]').value)||0;document.getElementById('totalPrice'+idx).textContent='$'+(q*p).toFixed(2);document.getElementById('totalCbm'+idx).textContent=(q*c).toFixed(3);document.getElementById('totalWeight'+idx).textContent=(q*w).toFixed(2)+' kg';recalcGrand();}
function recalcGrand(){var rows=document.querySelectorAll('[name$="[quantity]"]'),tq=0,tp=0,tc=0,tw=0;rows.forEach(function(el,i){var q=parseFloat(el.value)||0,p=parseFloat(document.querySelector('[name="items['+i+'][unit_price]"]').value)||0,c=parseFloat(document.querySelector('[name="items['+i+'][cbm_per_unit]"]').value)||0,w=parseFloat(document.querySelector('[name="items['+i+'][weight_per_unit]"]').value)||0;tq+=q;tp+=q*p;tc+=q*c;tw+=q*w;});document.getElementById('grandQty').textContent=tq;document.getElementById('grandPrice').textContent='$'+tp.toFixed(2);document.getElementById('grandCbm').textContent=tc.toFixed(3);document.getElementById('grandWeight').textContent=tw.toFixed(2)+' kg';}
</script>
@endpush
@endsection