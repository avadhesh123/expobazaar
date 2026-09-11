@extends('layouts.app')
@section('title', 'GRN: ' . $grn->grn_number)
@section('page-title', 'GRN Details')
@php
    $canAdjust = auth()->user()->isAdmin() || \App\Services\PermissionService::can(auth()->user(), 'logistics.grn.adjust');
    $adjustHistory = $grn->adjustment_history ?? [];
@endphp
@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('logistics.grn') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All GRNs</a>
    <a href="{{ route('logistics.shipments.show', $grn->shipment) }}" class="btn btn-outline btn-sm"><i class="fas fa-ship"></i> View Shipment</a>
    @if($grn ?? null)
    <a href="{{ route('logistics.grn.download', $grn) }}" class="btn btn-outline btn-sm">
        <i class="fas fa-download" style="color:#16a34a;"></i> Download GRN
    </a>
    @endif
</div>

{{-- GRN Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <div>
                <div style="font-size:1.2rem;font-weight:800;color:#0d1b2a;font-family:monospace;">{{ $grn->grn_number }}</div>
                <div style="font-size:.78rem;color:#64748b;">Uploaded by {{ $grn->uploader->name ?? '—' }} on {{ $grn->created_at->format('d M Y H:i') }}</div>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <span class="badge {{ $grn->status==='completed'?'badge-success':($grn->status==='verified'?'badge-info':'badge-warning') }}" style="font-size:.82rem;padding:.3rem .8rem;">{{ ucfirst($grn->status) }}</span>
                <div style="padding:.4rem .8rem;border-radius:8px;font-weight:700;font-size:.9rem;background:{{ $ageingDays>90?'#fee2e2':($ageingDays>60?'#fef3c7':($ageingDays>30?'#fefce8':'#dcfce7')) }};color:{{ $ageingDays>90?'#dc2626':($ageingDays>60?'#e8a838':($ageingDays>30?'#854d0e':'#166534')) }};">
                    {{ $ageingDays }} days ageing
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;">
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.65rem;color:#64748b;text-transform:uppercase;font-weight:600;">Shipment</div>
                <div style="font-weight:700;font-family:monospace;font-size:.85rem;">{{ $grn->shipment->shipment_code ?? '—' }}</div>
            </div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.65rem;color:#64748b;text-transform:uppercase;font-weight:600;">Warehouse</div>
                <div style="font-weight:600;font-size:.85rem;">{{ $grn->warehouse->name ?? '—' }}</div>
            </div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.65rem;color:#64748b;text-transform:uppercase;font-weight:600;">Company</div>
                <div style="font-weight:600;font-size:.85rem;">{{ $grn->company_code }}</div>
            </div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.65rem;color:#64748b;text-transform:uppercase;font-weight:600;">Receipt Date</div>
                <div style="font-weight:600;font-size:.85rem;">{{ $grn->receipt_date->format('d M Y') }}</div>
            </div>
            <div style="padding:.6rem;background:#dcfce7;border-radius:8px;">
                <div style="font-size:.65rem;color:#166534;text-transform:uppercase;font-weight:600;">Expected</div>
                <div style="font-weight:800;font-size:1rem;color:#166534;">{{ $grn->total_items_expected }}</div>
            </div>
            <div style="padding:.6rem;background:#dbeafe;border-radius:8px;">
                <div style="font-size:.65rem;color:#1e40af;text-transform:uppercase;font-weight:600;">Received</div>
                <div style="font-weight:800;font-size:1rem;color:#1e40af;">{{ $grn->total_items_received }}</div>
            </div>
            <div style="padding:.6rem;background:{{ $grn->damaged_items>0?'#fee2e2':'#f8fafc' }};border-radius:8px;">
                <div style="font-size:.65rem;color:{{ $grn->damaged_items>0?'#dc2626':'#64748b' }};text-transform:uppercase;font-weight:600;">Damaged</div>
                <div style="font-weight:800;font-size:1rem;color:{{ $grn->damaged_items>0?'#dc2626':'#94a3b8' }};">{{ $grn->damaged_items }}</div>
            </div>
            <div style="padding:.6rem;background:{{ $grn->missing_items>0?'#fef3c7':'#f8fafc' }};border-radius:8px;">
                <div style="font-size:.65rem;color:{{ $grn->missing_items>0?'#e8a838':'#64748b' }};text-transform:uppercase;font-weight:600;">Missing</div>
                <div style="font-weight:800;font-size:1rem;color:{{ $grn->missing_items>0?'#e8a838':'#94a3b8' }};">{{ $grn->missing_items }}</div>
            </div>
        </div>
        @if($grn->remarks)<div style="margin-top:.75rem;padding:.5rem .75rem;background:#fefce8;border-radius:6px;font-size:.82rem;color:#854d0e;"><i class="fas fa-sticky-note" style="margin-right:.3rem;"></i> {{ $grn->remarks }}</div>@endif
        @if($grn->grn_file)<div style="margin-top:.5rem;"><a href="{{ \App\Helpers\FileStorage::url($grn->grn_file) }}" class="btn btn-outline btn-sm" target="_blank"><i class="fas fa-file-download"></i> Download GRN Document</a></div>@endif
    </div>
</div>

{{-- GRN Items --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-boxes" style="margin-right:.5rem;color:#1e3a5f;"></i> Received Items ({{ $grn->items->count() }})</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>SAP Code</th>
                    <th>Vendor</th>
                    <th>Expected</th>
                    <th>Received</th>
                    <th>Damaged</th>
                    <th>Missing</th>
                    <th>Excess</th>
                    <th>Match</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grn->items as $item)
                @php $match = ($item->expected_quantity-($item->received_quantity+$item->damaged_quantity+$item->missing_quantity-$item->excess_quantity )) === 0; @endphp
                <tr style="{{ !$match?'background:#fef2f2;':'' }}">
                    <td style="font-weight:600;font-size:.82rem;">{{ $item->product->name ?? '—' }}</td>
                    <td style="font-family:monospace;font-size:.8rem;">{{ $item->product->sku ?? '—' }}</td>
                    <td style="font-family:monospace;font-size:.8rem;">{{ $item->product->sap_code ?? '—' }}</td>
                    <td style="font-size:.8rem;">{{ $item->product->vendor->company_name ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;">{{ $item->expected_quantity }}</td>
                    <td style="text-align:center;font-family:monospace;font-weight:700;color:#166534;">{{ $item->received_quantity }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $item->damaged_quantity>0?'#dc2626':'#94a3b8' }};">{{ $item->damaged_quantity }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $item->missing_quantity>0?'#e8a838':'#94a3b8' }};">{{ $item->missing_quantity }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $item->excess_quantity>0?'#16a34a':'#94a3b8' }};">{{ $item->excess_quantity }}</td>
                    <td style="text-align:center;">

                        @if($match)<i class="fas fa-check-circle" style="color:#16a34a;"></i>@else<i class="fas fa-exclamation-circle" style="color:#dc2626;"></i>@endif
                    </td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $item->remarks ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($canAdjust)
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-sliders-h" style="margin-right:.5rem;color:#e8a838;"></i> Adjust GRN Quantities</h3>
        <div style="display:flex;gap:.4rem;">
            <button type="button" class="btn btn-primary btn-sm" id="editGrnBtn"
                onclick="toggleGrnEdit(true)">
                <i class="fas fa-edit" style="margin-right:.2rem;"></i> Edit Quantities
            </button>
            @if(count($adjustHistory) > 0)
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('adjustHistoryModal').style.display='flex'">
                <i class="fas fa-history" style="margin-right:.2rem;"></i> History ({{ count($adjustHistory) }})
            </button>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('logistics.grn.adjust', $grn) }}" id="grnAdjustForm" style="display:none;">
        @csrf
        {{-- Reason --}}
        <div style="padding:.6rem 1.25rem;background:#fefce8;border-bottom:1px solid #fde68a;">
            <div style="display:flex;gap:.6rem;align-items:flex-end;">
                <div style="flex:1;">
                    <label style="font-size:.65rem;font-weight:600;color:#854d0e;display:block;margin-bottom:.2rem;">Adjustment Reason <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="adjustment_reason" required placeholder="e.g. Physical verification count mismatch, damaged items found during inspection..."
                        style="width:100%;padding:.4rem .6rem;border:1.5px solid #fde68a;border-radius:6px;font-size:.82rem;">
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="padding:.45rem .8rem;"
                    onclick="return confirm('Save quantity adjustments? This will update inventory.')">
                    <i class="fas fa-save" style="margin-right:.2rem;"></i> Save Adjustments
                </button>
                <button type="button" class="btn btn-outline btn-sm" onclick="toggleGrnEdit(false)">Cancel</button>
            </div>
        </div>

        {{-- Editable Table --}}
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table" style="font-size:.78rem;margin:0;">
                <thead>
                    <tr style="background:#f0f4f8;">
                        <th style="width:30px;">#</th>
                        <th>SKU</th>
                        <th>Product Name</th>
                        <th style="text-align:center;">Expected</th>
                        <th style="text-align:center;background:#eff6ff;">Received</th>
                        <th style="text-align:center;background:#fef2f2;">Damaged</th>
                        <th style="text-align:center;background:#f0fdf4;">Excess</th>
                        <th style="text-align:center;">Good Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grn->items as $idx => $item)
                    @php $product = $item->product; @endphp
                    <tr>
                        <td style="text-align:center;color:#94a3b8;">{{ $idx + 1 }}</td>
                        <td style="font-family:monospace;font-weight:600;">{{ $product->sku ?? '—' }}</td>
                        <td style="font-size:.72rem;">{{ $product->name ?? '—' }}</td>
                        <td style="text-align:center;font-weight:600;">{{ $item->expected_quantity ?? '—' }}</td>
                        <td style="text-align:center;background:#eff6ff;">
                            <input type="hidden" name="items[{{ $idx }}][grn_item_id]" value="{{ $item->id }}">
                            <input type="number" name="items[{{ $idx }}][received_quantity]" min="0"
                                value="{{ $item->received_quantity }}"
                                data-old="{{ $item->received_quantity }}"
                                onchange="calcGoodQty(this); highlightChange(this)"
                                style="width:60px;padding:.25rem .3rem;border:1.5px solid #bfdbfe;border-radius:4px;font-size:.8rem;font-family:monospace;text-align:center;">
                        </td>
                        <td style="text-align:center;background:#fef2f2;">
                            <input type="number" name="items[{{ $idx }}][damaged_quantity]" min="0"
                                value="{{ $item->damaged_quantity ?? 0 }}"
                                data-old="{{ $item->damaged_quantity ?? 0 }}"
                                onchange="calcGoodQty(this); highlightChange(this)"
                                style="width:60px;padding:.25rem .3rem;border:1.5px solid #fecaca;border-radius:4px;font-size:.8rem;font-family:monospace;text-align:center;">
                        </td>
                        <td style="text-align:center;background:#f0fdf4;">
                            <input type="number" name="items[{{ $idx }}][excess_quantity]" min="0"
                                value="{{ $item->excess_quantity ?? 0 }}"
                                data-old="{{ $item->excess_quantity ?? 0 }}"
                                onchange="highlightChange(this)"
                                style="width:60px;padding:.25rem .3rem;border:1.5px solid #bbf7d0;border-radius:4px;font-size:.8rem;font-family:monospace;text-align:center;">
                        </td>
                        <td style="text-align:center;">
                            <span class="good-qty" style="font-weight:700;font-family:monospace;">{{ intval($item->received_quantity) - intval($item->damaged_quantity ?? 0) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </form>

    {{-- Read-only table (shown when not editing) --}}
    <div class="card-body" style="padding:0;overflow-x:auto;" id="grnReadOnly">
        <table class="data-table" style="font-size:.78rem;margin:0;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="width:30px;">#</th>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th style="text-align:center;">Expected</th>
                    <th style="text-align:center;">Received</th>
                    <th style="text-align:center;">Damaged</th>
                    <th style="text-align:center;">Excess</th>
                    <th style="text-align:center;">Good Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grn->items as $idx => $item)
                @php
                    $goodQty = intval($item->received_quantity) - intval($item->damaged_quantity ?? 0);
                @endphp
                <tr>
                    <td style="text-align:center;color:#94a3b8;">{{ $idx + 1 }}</td>
                    <td style="font-family:monospace;font-weight:600;">{{ $item->product->sku ?? '—' }}</td>
                    <td style="font-size:.72rem;">{{ $item->product->name ?? '—' }}</td>
                    <td style="text-align:center;">{{ $item->expected_quantity ?? '—' }}</td>
                    <td style="text-align:center;font-weight:600;color:#1e40af;">{{ $item->received_quantity }}</td>
                    <td style="text-align:center;font-weight:600;color:{{ ($item->damaged_quantity ?? 0) > 0 ? '#dc2626' : '#94a3b8' }};">{{ $item->damaged_quantity ?? 0 }}</td>
                    <td style="text-align:center;font-weight:600;color:{{ ($item->excess_quantity ?? 0) > 0 ? '#16a34a' : '#94a3b8' }};">{{ $item->excess_quantity ?? 0 }}</td>
                    <td style="text-align:center;font-weight:700;">{{ $goodQty }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Adjustment History Modal --}}
@if(count($adjustHistory) > 0)
<div id="adjustHistoryModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;width:700px;max-width:94%;max-height:80vh;overflow-y:auto;box-shadow:0 12px 48px rgba(0,0,0,.2);">
        <div style="padding:.75rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:#fff;border-radius:14px 14px 0 0;">
            <h3 style="font-size:.95rem;font-weight:700;color:#0d1b2a;margin:0;">
                <i class="fas fa-history" style="color:#e8a838;margin-right:.3rem;"></i> Adjustment History — {{ $grn->grn_number }}
            </h3>
            <button onclick="document.getElementById('adjustHistoryModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:1.3rem;color:#94a3b8;">&times;</button>
        </div>
        <div style="padding:1rem 1.25rem;">
            @foreach(array_reverse($adjustHistory) as $hIdx => $entry)
            <div style="margin-bottom:1rem;padding:.75rem;background:#f8fafc;border-radius:8px;border-left:3px solid #e8a838;">
                <div style="display:flex;justify-content:space-between;margin-bottom:.4rem;">
                    <span style="font-size:.78rem;font-weight:700;color:#0d1b2a;">
                        Adjustment #{{ count($adjustHistory) - $hIdx }}
                    </span>
                    <span style="font-size:.68rem;color:#94a3b8;">
                        {{ \Carbon\Carbon::parse($entry['adjusted_at'])->format('d M Y H:i') }} by {{ $entry['adjusted_by'] ?? '—' }}
                    </span>
                </div>
                <div style="font-size:.75rem;color:#64748b;margin-bottom:.4rem;">
                    <strong>Reason:</strong> {{ $entry['reason'] ?? '—' }}
                </div>
                <table style="width:100%;font-size:.72rem;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#e2e8f0;">
                            <th style="padding:.2rem .4rem;text-align:left;">SKU</th>
                            <th style="padding:.2rem .4rem;text-align:center;">Old Received</th>
                            <th style="padding:.2rem .4rem;text-align:center;">New Received</th>
                            <th style="padding:.2rem .4rem;text-align:center;">Old Damaged</th>
                            <th style="padding:.2rem .4rem;text-align:center;">New Damaged</th>
                            <th style="padding:.2rem .4rem;text-align:center;">Old Excess</th>
                            <th style="padding:.2rem .4rem;text-align:center;">New Excess</th>
                            <th style="padding:.2rem .4rem;text-align:center;">Inv. Impact</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entry['items'] ?? [] as $change)
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:.2rem .4rem;font-family:monospace;font-weight:600;">{{ $change['sku'] ?? '—' }}</td>
                            <td style="padding:.2rem .4rem;text-align:center;">{{ $change['old']['received'] ?? 0 }}</td>
                            <td style="padding:.2rem .4rem;text-align:center;font-weight:600;color:{{ ($change['old']['received'] ?? 0) !== ($change['new']['received'] ?? 0) ? '#1e40af' : 'inherit' }};">{{ $change['new']['received'] ?? 0 }}</td>
                            <td style="padding:.2rem .4rem;text-align:center;">{{ $change['old']['damaged'] ?? 0 }}</td>
                            <td style="padding:.2rem .4rem;text-align:center;font-weight:600;color:{{ ($change['old']['damaged'] ?? 0) !== ($change['new']['damaged'] ?? 0) ? '#dc2626' : 'inherit' }};">{{ $change['new']['damaged'] ?? 0 }}</td>
                            <td style="padding:.2rem .4rem;text-align:center;">{{ $change['old']['excess'] ?? 0 }}</td>
                            <td style="padding:.2rem .4rem;text-align:center;font-weight:600;color:{{ ($change['old']['excess'] ?? 0) !== ($change['new']['excess'] ?? 0) ? '#16a34a' : 'inherit' }};">{{ $change['new']['excess'] ?? 0 }}</td>
                            <td style="padding:.2rem .4rem;text-align:center;font-weight:700;color:{{ ($change['inventory_impact'] ?? 0) > 0 ? '#16a34a' : (($change['inventory_impact'] ?? 0) < 0 ? '#dc2626' : '#94a3b8') }};">
                                {{ ($change['inventory_impact'] ?? 0) > 0 ? '+' : '' }}{{ $change['inventory_impact'] ?? 0 }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<script>
function toggleGrnEdit(show) {
    document.getElementById('grnAdjustForm').style.display = show ? 'block' : 'none';
    document.getElementById('grnReadOnly').style.display = show ? 'none' : 'block';
    document.getElementById('editGrnBtn').style.display = show ? 'none' : 'inline-flex';
}

function calcGoodQty(input) {
    var row = input.closest('tr');
    var received = parseInt(row.querySelector('[name$="[received_quantity]"]').value) || 0;
    var damaged = parseInt(row.querySelector('[name$="[damaged_quantity]"]').value) || 0;
    row.querySelector('.good-qty').textContent = Math.max(0, received - damaged);
}

function highlightChange(input) {
    var old = parseInt(input.getAttribute('data-old')) || 0;
    var now = parseInt(input.value) || 0;
    input.style.background = (old !== now) ? '#fefce8' : '';
    input.style.fontWeight = (old !== now) ? '700' : '';
}

// Close history modal on backdrop
document.getElementById('adjustHistoryModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
@endif

@endsection