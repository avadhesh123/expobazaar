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
                <thead><tr><th>Product</th><th>SKU</th><th>Barcode</th><th>Qty *</th><th>Unit Price ($) *</th><th>Total</th><th>CBM/Unit *</th><th>Total CBM</th><th>Wt/Unit (kg)</th><th>Total Wt</th><th>Details</th></tr></thead>
                <tbody>
                    @foreach($liveSheet->items as $idx => $item)
                    @php $d = $item->product_details ?? []; @endphp
                    <tr>
                        <td><input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item->product_id }}"><div style="font-weight:600;font-size:.82rem;">{{ $item->product->name ?? '—' }}</div></td>
                        <td style="font-family:monospace;font-size:.8rem;">{{ $item->product->sku ?? '—' }}</td>
                        <td style="font-size:.75rem;color:#64748b;">{{ $d['barcode'] ?? '—' }}</td>
                        <td><input type="number" name="items[{{ $idx }}][quantity]" value="{{ $item->quantity }}" min="1" required onchange="calcRow({{ $idx }})" style="width:70px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;text-align:center;" {{ $liveSheet->is_locked?'disabled':'' }}></td>
                        <td><input type="number" step="0.01" name="items[{{ $idx }}][unit_price]" value="{{ $item->unit_price }}" min="0" required onchange="calcRow({{ $idx }})" style="width:90px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;" {{ $liveSheet->is_locked?'disabled':'' }}></td>
                        <td style="font-family:monospace;font-weight:600;" id="totalPrice{{ $idx }}">${{ number_format($item->total_price, 2) }}</td>
                        <td><input type="number" step="0.001" name="items[{{ $idx }}][cbm_per_unit]" value="{{ $item->cbm_per_unit }}" min="0" required onchange="calcRow({{ $idx }})" style="width:80px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;" {{ $liveSheet->is_locked?'disabled':'' }}></td>
                        <td style="font-family:monospace;font-weight:600;" id="totalCbm{{ $idx }}">{{ number_format($item->total_cbm, 3) }}</td>
                        <td><input type="number" step="0.01" name="items[{{ $idx }}][weight_per_unit]" value="{{ $item->weight_per_unit }}" min="0" onchange="calcRow({{ $idx }})" style="width:80px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;" {{ $liveSheet->is_locked?'disabled':'' }}></td>
                        <td style="font-family:monospace;" id="totalWeight{{ $idx }}">{{ number_format($item->total_weight, 2) }} kg</td>
                        <td>@php $filled = collect($d)->filter(fn($v) => !empty($v))->count(); @endphp
                            @if($filled > 5)<span style="font-size:.65rem;color:#16a34a;font-weight:600;"><i class="fas fa-check-circle"></i> {{ $filled }}</span>@else<span style="font-size:.65rem;color:#e8a838;"><i class="fas fa-clock"></i> {{ $filled }}</span>@endif
                        </td>
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