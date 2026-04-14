@extends('layouts.app')
@section('title', 'Review Offer Sheet')
@section('page-title', 'Review Offer Sheet: ' . $offerSheet->offer_sheet_number)

@section('content')
{{-- Header --}}
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <a href="{{ route('sourcing.offer-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    <div style="margin-left:auto;display:flex;gap:.75rem;align-items:center;">
        <span style="font-size:.82rem;color:#64748b;"><strong>Vendor:</strong> {{ $offerSheet->vendor->company_name ?? '—' }}</span>
        <span style="font-size:.82rem;color:#64748b;"><strong>Company:</strong> {{ $offerSheet->company_code }}</span>
        <span style="font-size:.82rem;color:#64748b;"><strong>Products:</strong> {{ $offerSheet->total_products }}</span>
        <span class="badge badge-warning">{{ ucfirst(str_replace('_',' ',$offerSheet->status)) }}</span>
    </div>
</div>

{{-- Selection Form --}}
<form method="POST" action="{{ route('sourcing.offer-sheets.select', $offerSheet) }}" id="selectionForm">
    @csrf

    {{-- Selection bar --}}
    <div class="card" style="margin-bottom:1rem;border-color:#e8a838;">
        <div class="card-body" style="padding:.75rem 1.4rem;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <label style="display:flex;align-items:center;gap:.3rem;font-size:.82rem;cursor:pointer;font-weight:600;">
                    <input type="checkbox" id="selectAll" onclick="toggleAll(this)" style="width:16px;height:16px;accent-color:#e8a838;">
                    Select All
                </label>
                <span style="font-size:.82rem;color:#64748b;"><span id="selectedCount">0</span> of {{ $offerSheet->items->count() }} selected</span>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return validateSelection()">
                <i class="fas fa-check-square" style="margin-right:.3rem;"></i> Submit Selection to Vendor
            </button>
        </div>
    </div>

    {{-- Products Grid --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;">
        @forelse($offerSheet->items as $item)
        <div class="card" id="itemCard{{ $item->id }}" style="transition:border-color .2s;{{ $item->is_selected ? 'border-color:#16a34a;background:#f0fdf4;' : '' }}">
            <div class="card-body" style="padding:1rem;">
                <div style="display:flex;gap:.75rem;">
                    {{-- Checkbox --}}
                    <div style="padding-top:.2rem;">
                        <input type="checkbox" name="selected_items[]" value="{{ $item->id }}" class="item-checkbox"
                            {{ $item->is_selected ? 'checked' : '' }}
                            onchange="updateCount();toggleCardStyle(this,{{ $item->id }})"
                            style="width:18px;height:18px;accent-color:#16a34a;cursor:pointer;">
                    </div>

                    {{-- Thumbnail --}}
                    <div style="width:60px;height:60px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
                        @if($item->thumbnail)
                            <img src="{{ asset('storage/' . $item->thumbnail) }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            <i class="fas fa-image" style="color:#d1d5db;font-size:1.2rem;"></i>
                        @endif
                    </div>

                    {{-- Product Info --}}
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;color:#0d1b2a;font-size:.88rem;margin-bottom:.2rem;">{{ $item->product_name }}</div>
                        @if($item->product_sku)<div style="font-size:.7rem;color:#94a3b8;font-family:monospace;">SKU: {{ $item->product_sku }}</div>@endif
                        @if($item->category)<div style="font-size:.7rem;color:#64748b;">{{ $item->category->name }}</div>@endif

                        <div style="display:flex;gap:.75rem;margin-top:.5rem;">
                            <div>
                                <div style="font-size:.62rem;color:#94a3b8;text-transform:uppercase;font-weight:600;">Price</div>
                                <div style="font-size:.9rem;font-weight:700;color:#166534;">{{ $item->currency }} {{ number_format($item->vendor_price, 2) }}</div>
                            </div>
                        </div>

                        @if($item->product_details)
                            <div style="margin-top:.4rem;font-size:.72rem;color:#64748b;">
                                @foreach(array_slice((array)$item->product_details, 0, 3) as $key => $val)
                                    <span style="display:inline-block;padding:.1rem .3rem;background:#f1f5f9;border-radius:3px;margin:.1rem;">{{ $key }}: {{ $val }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Selected indicator --}}
                @if($item->is_selected)
                    <div style="margin-top:.5rem;padding:.3rem .5rem;background:#dcfce7;border-radius:6px;font-size:.7rem;color:#166534;font-weight:600;display:flex;align-items:center;gap:.3rem;">
                        <i class="fas fa-check-circle"></i> Previously selected by {{ $item->selector->name ?? 'team' }}
                    </div>
                @endif
            </div>
        </div>
        @empty
        <div style="grid-column:1/-1;text-align:center;padding:3rem;color:#94a3b8;">
            <i class="fas fa-box-open" style="font-size:2.5rem;display:block;margin-bottom:.5rem;"></i>
            No products in this offer sheet.
        </div>
        @endforelse
    </div>
</form>

@push('scripts')
<script>
function toggleAll(el) {
    document.querySelectorAll('.item-checkbox').forEach(function(cb) {
        cb.checked = el.checked;
        toggleCardStyle(cb, cb.value);
    });
    updateCount();
}

function updateCount() {
    var count = document.querySelectorAll('.item-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
}

function toggleCardStyle(cb, itemId) {
    var card = document.getElementById('itemCard' + itemId);
    if (cb.checked) {
        card.style.borderColor = '#16a34a';
        card.style.background = '#f0fdf4';
    } else {
        card.style.borderColor = '#e8ecf1';
        card.style.background = '#fff';
    }
}

function validateSelection() {
    var count = document.querySelectorAll('.item-checkbox:checked').length;
    if (count === 0) {
        alert('Please select at least one product.');
        return false;
    }
    return confirm('Submit ' + count + ' selected product(s) to vendor?');
}

// Init count
document.addEventListener('DOMContentLoaded', updateCount);
</script>
@endpush
@endsection
