@extends('layouts.app')
@section('title', 'Review Offer Sheet: ' . $offerSheet->offer_sheet_number)
@section('page-title', 'Review Offer Sheet — ' . $offerSheet->offer_sheet_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('sourcing.offer-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Offer Sheets</a>
</div>

{{-- Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:1.1rem;font-weight:800;color:#0d1b2a;font-family:monospace;">{{ $offerSheet->offer_sheet_number }}</div>
                <div style="font-size:.82rem;color:#64748b;">
                    Vendor: <strong>{{ $offerSheet->vendor->company_name ?? '—' }}</strong> ·
                    Company: {{ $offerSheet->company_code }} ·
                    {{ $offerSheet->total_products }} products ·
                    {{ $offerSheet->selected_products }} selected
                </div>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;">
                @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-warning','under_review'=>'badge-info','selection_done'=>'badge-success','live_sheet_created'=>'badge-info','converted'=>'badge-success']; @endphp
                <span class="badge {{ $sc[$offerSheet->status] ?? 'badge-gray' }}" style="font-size:.85rem;padding:.3rem .8rem;">{{ ucfirst(str_replace('_',' ',$offerSheet->status)) }}</span>
            </div>
        </div>
    </div>
</div>

@if($offerSheet->status === 'selection_done')
<div style="padding:.75rem 1rem;background:#dcfce7;border-radius:8px;border:1px solid #bbf7d0;margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;">
    <span style="font-size:.85rem;color:#166534;font-weight:600;"><i class="fas fa-check-circle" style="margin-right:.3rem;"></i> Product selection complete. {{ $offerSheet->selected_products }} products selected.</span>
    <form method="POST" action="{{ route('sourcing.offer-sheets.create-live-sheet', $offerSheet) }}" onsubmit="return confirm('Create Live Sheet from selected products?')">
        @csrf
        <button type="submit" class="btn btn-success"><i class="fas fa-clipboard-list" style="margin-right:.3rem;"></i> Create Live Sheet</button>
    </form>
</div>
@endif

@if($offerSheet->status === 'live_sheet_created')
<div style="padding:.75rem 1rem;background:#dbeafe;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.85rem;color:#1e40af;font-weight:600;">
    <i class="fas fa-clipboard-list" style="margin-right:.3rem;"></i> Live Sheet already created from this offer sheet.
</div>
@endif

{{-- Selection Form --}}
@if(in_array($offerSheet->status, ['submitted', 'under_review']))
<form method="POST" action="{{ route('sourcing.offer-sheets.select', $offerSheet) }}" id="selectionForm">
    @csrf
    <div style="padding:.65rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1rem;font-size:.78rem;color:#1e40af;display:flex;justify-content:space-between;align-items:center;">
        <span><i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Select products using checkboxes, then click "Confirm Selection".</span>
        <div style="display:flex;gap:.5rem;align-items:center;">
            <span id="selectedCount" style="font-weight:700;">0</span> selected
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Confirm product selection?')"><i class="fas fa-check"></i> Confirm Selection</button>
        </div>
    </div>
@endif

{{-- Products Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-table" style="margin-right:.5rem;color:#1e3a5f;"></i> Products ({{ $offerSheet->items->count() }})</h3>
        @if(in_array($offerSheet->status, ['submitted', 'under_review']))
        <div style="display:flex;gap:.5rem;align-items:center;">
            <button type="button" class="btn btn-outline btn-sm" onclick="toggleAll(true)"><i class="fas fa-check-double"></i> Select All</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="toggleAll(false)"><i class="fas fa-times"></i> Clear All</button>
        </div>
        @endif
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="min-width:1200px;">
            <thead>
                <tr style="background:#f0f4f8;">
                    @if(in_array($offerSheet->status, ['submitted', 'under_review']))
                    <th style="width:40px;text-align:center;"><input type="checkbox" id="masterCheck" onchange="toggleAll(this.checked)" style="width:16px;height:16px;"></th>
                    @else
                    <th style="width:40px;text-align:center;"><i class="fas fa-check-square" style="color:#16a34a;"></i></th>
                    @endif
                    <th style="width:40px;">S.no</th>
                    <th style="min-width:100px;">SKU</th>
                    <th style="min-width:180px;">Product Name</th>
                    <th style="width:60px;">Image</th>
                    <th>L (in)</th>
                    <th>W (in)</th>
                    <th>H (in)</th>
                    <th>Wt (g)</th>
                    <th>Material</th>
                    <th>Color</th>
                    <th>Category</th>
                    <th>FOB ($)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($offerSheet->items as $item)
                @php $d = $item->product_details ?? []; @endphp
                <tr style="{{ $item->is_selected ? 'background:#f0fdf4;border-left:3px solid #16a34a;' : '' }}">
                    @if(in_array($offerSheet->status, ['submitted', 'under_review']))
                    <td style="text-align:center;">
                        <input type="checkbox" name="selected_items[]" value="{{ $item->id }}" class="item-check" {{ $item->is_selected ? 'checked' : '' }} onchange="updateCount()" style="width:16px;height:16px;accent-color:#16a34a;">
                    </td>
                    @else
                    <td style="text-align:center;">
                        @if($item->is_selected)
                            <i class="fas fa-check-circle" style="color:#16a34a;font-size:1rem;"></i>
                        @else
                            <i class="far fa-circle" style="color:#d1d5db;font-size:1rem;"></i>
                        @endif
                    </td>
                    @endif
                    <td style="text-align:center;color:#94a3b8;font-weight:600;">{{ $d['sno'] ?? $loop->iteration }}</td>
                    <td style="font-family:monospace;font-weight:600;font-size:.82rem;">{{ $item->product_sku }}</td>
                    <td style="font-weight:600;font-size:.82rem;">{{ $item->product_name }}</td>
                    <td style="text-align:center;">
                        @if($item->thumbnail)
                            @php
                                $imgUrl = str_starts_with($item->thumbnail, 'http') ? $item->thumbnail : asset('storage/app/public/' . $item->thumbnail);
                            @endphp
                            <a href="{{ $imgUrl }}" target="_blank"><img src="{{ $imgUrl }}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;" onerror="this.style.display='none'"></a>
                        @else
                            <i class="fas fa-image" style="color:#d1d5db;"></i>
                        @endif
                    </td>
                    <td style="text-align:center;font-family:monospace;font-size:.82rem;">{{ $d['length_inches'] ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-size:.82rem;">{{ $d['width_inches'] ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-size:.82rem;">{{ $d['height_inches'] ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-size:.82rem;">{{ $d['weight_grams'] ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $d['material'] ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $d['color'] ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $d['category'] ?? ($item->category->name ?? '—') }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">${{ number_format($item->vendor_price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if(in_array($offerSheet->status, ['submitted', 'under_review']))
</form>
@endif

{{-- Legend --}}
<div style="margin-top:.75rem;display:flex;gap:1.5rem;font-size:.75rem;color:#64748b;">
    <span><i class="fas fa-check-circle" style="color:#16a34a;margin-right:.2rem;"></i> Selected for Live Sheet</span>
    <span><i class="far fa-circle" style="color:#d1d5db;margin-right:.2rem;"></i> Not selected</span>
</div>

@push('scripts')
<script>
function toggleAll(checked) {
    document.querySelectorAll('.item-check').forEach(function(cb) { cb.checked = checked; });
    var master = document.getElementById('masterCheck');
    if (master) master.checked = checked;
    updateCount();
}
function updateCount() {
    var count = document.querySelectorAll('.item-check:checked').length;
    var el = document.getElementById('selectedCount');
    if (el) el.textContent = count;
}
document.addEventListener('DOMContentLoaded', updateCount);
</script>
@endpush
@endsection