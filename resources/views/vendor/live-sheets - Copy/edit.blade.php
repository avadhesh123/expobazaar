@extends('layouts.app')
@section('title', 'Fill Live Sheet')
@section('page-title', 'Live Sheet — ' . $liveSheet->live_sheet_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('vendor.consignments') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Consignments</a>
</div>

@if($liveSheet->is_locked)
<div style="padding:.85rem 1.2rem;background:#fee2e2;border-radius:10px;border:1px solid #fecaca;margin-bottom:1.25rem;font-size:.85rem;color:#dc2626;font-weight:600;"><i class="fas fa-lock" style="margin-right:.3rem;"></i> This live sheet is locked. Contact Admin to unlock for changes.</div>
@endif

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.75rem;">
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Live Sheet</div><div style="font-weight:700;font-family:monospace;">{{ $liveSheet->live_sheet_number }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Consignment</div><div style="font-weight:600;">{{ $liveSheet->consignment->consignment_number ?? '—' }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Country</div><div>{{ $liveSheet->consignment->destination_country ?? '—' }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Status</div><div><span class="badge {{ $liveSheet->is_locked?'badge-success':'badge-warning' }}">{{ $liveSheet->is_locked?'Locked':ucfirst($liveSheet->status) }}</span></div></div>
        </div>
    </div>
</div>

<div style="padding:.65rem 1rem;background:#fefce8;border-radius:8px;border:1px solid #fde68a;margin-bottom:1rem;font-size:.78rem;color:#854d0e;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
    <strong>Live Sheet contains:</strong> Detailed product information — quantity, unit price, CBM per unit, weight per unit, and additional product specifications for each selected item. This data is used for logistics planning and container documentation.
</div>

<form method="POST" action="{{ route('vendor.live-sheets.submit', $liveSheet) }}">
    @csrf
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-list" style="margin-right:.5rem;color:#e8a838;"></i> Product Details</h3></div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Product</th><th>SKU</th><th>Quantity *</th><th>Unit Price ($) *</th><th>Total Price</th><th>CBM/Unit *</th><th>Total CBM</th><th>Weight/Unit (kg)</th><th>Total Weight</th></tr></thead>
                <tbody>
                    @foreach($liveSheet->items as $idx => $item)
                    <tr>
                        <td>
                            <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item->product_id }}">
                            <div style="font-weight:600;font-size:.82rem;">{{ $item->product->name ?? '—' }}</div>
                        </td>
                        <td style="font-family:monospace;font-size:.8rem;">{{ $item->product->sku ?? '—' }}</td>
                        <td><input type="number" name="items[{{ $idx }}][quantity]" value="{{ $item->quantity }}" min="1" required onchange="calcRow({{ $idx }})" style="width:70px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;text-align:center;" {{ $liveSheet->is_locked?'disabled':'' }}></td>
                        <td><input type="number" step="0.01" name="items[{{ $idx }}][unit_price]" value="{{ $item->unit_price }}" min="0" required onchange="calcRow({{ $idx }})" style="width:90px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;" {{ $liveSheet->is_locked?'disabled':'' }}></td>
                        <td style="font-family:monospace;font-weight:600;" id="totalPrice{{ $idx }}">${{ number_format($item->total_price, 2) }}</td>
                        <td><input type="number" step="0.001" name="items[{{ $idx }}][cbm_per_unit]" value="{{ $item->cbm_per_unit }}" min="0" required onchange="calcRow({{ $idx }})" style="width:80px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;" {{ $liveSheet->is_locked?'disabled':'' }}></td>
                        <td style="font-family:monospace;font-weight:600;" id="totalCbm{{ $idx }}">{{ number_format($item->total_cbm, 3) }}</td>
                        <td><input type="number" step="0.01" name="items[{{ $idx }}][weight_per_unit]" value="{{ $item->weight_per_unit }}" min="0" onchange="calcRow({{ $idx }})" style="width:80px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;" {{ $liveSheet->is_locked?'disabled':'' }}></td>
                        <td style="font-family:monospace;" id="totalWeight{{ $idx }}">{{ number_format($item->total_weight, 2) }} kg</td>
                    </tr>
                    @endforeach
                    <tr style="background:#f8fafc;font-weight:700;">
                        <td colspan="2" style="text-align:right;">TOTALS</td>
                        <td style="text-align:center;" id="grandQty">{{ $liveSheet->items->sum('quantity') }}</td>
                        <td></td>
                        <td style="font-family:monospace;" id="grandPrice">${{ number_format($liveSheet->items->sum('total_price'), 2) }}</td>
                        <td></td>
                        <td style="font-family:monospace;" id="grandCbm">{{ number_format($liveSheet->items->sum('total_cbm'), 3) }}</td>
                        <td></td>
                        <td style="font-family:monospace;" id="grandWeight">{{ number_format($liveSheet->items->sum('total_weight'), 2) }} kg</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if(!$liveSheet->is_locked)
    <div style="margin-top:1rem;display:flex;gap:.5rem;justify-content:flex-end;">
        <a href="{{ route('vendor.consignments') }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Submit live sheet for Sourcing team approval?\n\nOnce approved, the sheet will be locked.')"><i class="fas fa-paper-plane" style="margin-right:.3rem;"></i> Submit Live Sheet</button>
    </div>
    @endif
</form>

@push('scripts')
<script>
function calcRow(idx) {
    var qty = parseFloat(document.querySelector('[name="items['+idx+'][quantity]"]').value) || 0;
    var price = parseFloat(document.querySelector('[name="items['+idx+'][unit_price]"]').value) || 0;
    var cbm = parseFloat(document.querySelector('[name="items['+idx+'][cbm_per_unit]"]').value) || 0;
    var wt = parseFloat(document.querySelector('[name="items['+idx+'][weight_per_unit]"]').value) || 0;
    document.getElementById('totalPrice'+idx).textContent = '$' + (qty * price).toFixed(2);
    document.getElementById('totalCbm'+idx).textContent = (qty * cbm).toFixed(3);
    document.getElementById('totalWeight'+idx).textContent = (qty * wt).toFixed(2) + ' kg';
    recalcGrand();
}
function recalcGrand() {
    var rows = document.querySelectorAll('[name$="[quantity]"]');
    var tq=0,tp=0,tc=0,tw=0;
    rows.forEach(function(el,i) {
        var q=parseFloat(el.value)||0;
        var p=parseFloat(document.querySelector('[name="items['+i+'][unit_price]"]').value)||0;
        var c=parseFloat(document.querySelector('[name="items['+i+'][cbm_per_unit]"]').value)||0;
        var w=parseFloat(document.querySelector('[name="items['+i+'][weight_per_unit]"]').value)||0;
        tq+=q; tp+=q*p; tc+=q*c; tw+=q*w;
    });
    document.getElementById('grandQty').textContent=tq;
    document.getElementById('grandPrice').textContent='$'+tp.toFixed(2);
    document.getElementById('grandCbm').textContent=tc.toFixed(3);
    document.getElementById('grandWeight').textContent=tw.toFixed(2)+' kg';
}
</script>
@endpush
@endsection
