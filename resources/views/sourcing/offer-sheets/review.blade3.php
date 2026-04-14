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

{{-- Products Table/Tile Toggle --}}
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;">
        <h3 style="margin:0;"><i class="fas fa-table" style="margin-right:.5rem;color:#1e3a5f;"></i> Products ({{ $offerSheet->items->count() }})</h3>
        <div style="display:flex;gap:.5rem;align-items:center;">
            @if(in_array($offerSheet->status, ['submitted', 'under_review']))
            <button type="button" class="btn btn-outline btn-sm" onclick="toggleAll(true)"><i class="fas fa-check-double"></i> Select All</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="toggleAll(false)"><i class="fas fa-times"></i> Clear All</button>
            @endif

            {{-- View Toggle --}}
            <div style="display:flex;border:1px solid #d1d5db;border-radius:8px;overflow:hidden;">
                <button type="button" id="btnTile" onclick="switchView('tile')"
                    style="padding:.38rem .7rem;border:none;cursor:pointer;font-size:.8rem;font-weight:600;background:#1e3a5f;color:#fff;transition:background .2s;"
                    title="Tile View">
                    <i class="fas fa-th"></i>
                </button>
                <button type="button" id="btnTable" onclick="switchView('table')"
                    style="padding:.38rem .7rem;border:none;cursor:pointer;font-size:.8rem;font-weight:600;background:#f8fafc;color:#64748b;border-left:1px solid #d1d5db;transition:background .2s;"
                    title="Table View">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════ TILE VIEW ══════════════ --}}
    <div id="viewTile" class="card-body" style="padding:1rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:1rem;">
            @foreach($offerSheet->items as $item)
            @php $d = $item->product_details ?? []; @endphp
            <div class="tile-card" id="tile-{{ $item->id }}"
                style="border:2px solid {{ $item->is_selected ? '#16a34a' : '#e2e8f0' }};border-radius:12px;overflow:hidden;background:#fff;transition:border-color .2s,box-shadow .2s;position:relative;">

                {{-- Checkbox overlay --}}
                @if(in_array($offerSheet->status, ['submitted', 'under_review']))
                <label style="position:absolute;top:8px;left:8px;z-index:2;cursor:pointer;">
                    <input type="checkbox" name="selected_items[]" value="{{ $item->id }}"
                        class="item-check tile-check" data-tile-id="tile-{{ $item->id }}"
                        {{ $item->is_selected ? 'checked' : '' }}
                        onchange="updateCount(); syncTableCheck(this); highlightTile(this)"
                        style="width:18px;height:18px;accent-color:#16a34a;cursor:pointer;">
                </label>
                @else
                <div style="position:absolute;top:8px;left:8px;z-index:2;">
                    @if($item->is_selected)
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:#16a34a;border-radius:50%;"><i class="fas fa-check" style="color:#fff;font-size:.6rem;"></i></span>
                    @endif
                </div>
                @endif

                {{-- Image --}}
                <div style="height:130px;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;">
                    @if($item->thumbnail)
                        @php $imgUrl = str_starts_with($item->thumbnail, 'http') ? $item->thumbnail : asset('storage/app/public/' . $item->thumbnail); @endphp
                        <a href="{{ $imgUrl }}" target="_blank">
                            <img src="{{ $imgUrl }}" style="width:100%;height:130px;object-fit:cover;" onerror="this.parentElement.parentElement.innerHTML='<i class=\'fas fa-image\' style=\'color:#d1d5db;font-size:2rem;\'></i>'">
                        </a>
                    @else
                        <i class="fas fa-image" style="color:#d1d5db;font-size:2rem;"></i>
                    @endif
                </div>

                {{-- Info --}}
                <div style="padding:.65rem .75rem;">
                    <div style="font-size:.68rem;font-family:monospace;color:#94a3b8;margin-bottom:.15rem;">{{ $item->product_sku }}</div>
                    <div style="font-size:.82rem;font-weight:700;color:#0d1b2a;line-height:1.3;margin-bottom:.4rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $item->product_name }}">{{ $item->product_name }}</div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.35rem;">
                        <span style="font-size:.72rem;color:#64748b;">{{ $d['category'] ?? ($item->category->name ?? '—') }}</span>
                        <span style="font-size:.85rem;font-weight:800;color:#166534;font-family:monospace;">${{ number_format($item->vendor_price, 2) }}</span>
                    </div>
                    <div style="display:flex;gap:.3rem;font-size:.68rem;color:#94a3b8;flex-wrap:wrap;">
                        @if(!empty($d['length_inches'])) <span style="background:#f1f5f9;padding:.1rem .35rem;border-radius:4px;">{{ $d['length_inches'] }}"L</span> @endif
                        @if(!empty($d['width_inches']))  <span style="background:#f1f5f9;padding:.1rem .35rem;border-radius:4px;">{{ $d['width_inches'] }}"W</span>  @endif
                        @if(!empty($d['height_inches'])) <span style="background:#f1f5f9;padding:.1rem .35rem;border-radius:4px;">{{ $d['height_inches'] }}"H</span> @endif
                        @if(!empty($d['color']))         <span style="background:#f1f5f9;padding:.1rem .35rem;border-radius:4px;">{{ $d['color'] }}</span>              @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ══════════════ TABLE VIEW ══════════════ --}}
    <div id="viewTable" class="card-body" style="padding:0;overflow-x:auto;display:none;">
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
                <tr id="tablerow-{{ $item->id }}" style="{{ $item->is_selected ? 'background:#f0fdf4;border-left:3px solid #16a34a;' : '' }}">
                    @if(in_array($offerSheet->status, ['submitted', 'under_review']))
                    <td style="text-align:center;">
                        <input type="checkbox" class="item-check table-check" data-item-id="{{ $item->id }}" value="{{ $item->id }}"
                            {{ $item->is_selected ? 'checked' : '' }}
                            onchange="updateCount(); syncTileCheck(this); highlightTableRow(this)"
                            style="width:16px;height:16px;accent-color:#16a34a;">
                        {{-- Note: actual form values submitted via tile checkboxes (name="selected_items[]") --}}
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
                            @php $imgUrl = str_starts_with($item->thumbnail, 'http') ? $item->thumbnail : asset('storage/app/public/' . $item->thumbnail); @endphp
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
// ── View toggle ──────────────────────────────────────────────────────────────
var currentView = localStorage.getItem('offerSheetView') || 'tile';

function switchView(view) {
    currentView = view;
    localStorage.setItem('offerSheetView', view);

    var tileEl   = document.getElementById('viewTile');
    var tableEl  = document.getElementById('viewTable');
    var btnTile  = document.getElementById('btnTile');
    var btnTable = document.getElementById('btnTable');

    if (view === 'tile') {
        tileEl.style.display  = 'block';
        tableEl.style.display = 'none';
        btnTile.style.background  = '#1e3a5f';
        btnTile.style.color       = '#fff';
        btnTable.style.background = '#f8fafc';
        btnTable.style.color      = '#64748b';
    } else {
        tileEl.style.display  = 'none';
        tableEl.style.display = 'block';
        btnTable.style.background = '#1e3a5f';
        btnTable.style.color      = '#fff';
        btnTile.style.background  = '#f8fafc';
        btnTile.style.color       = '#64748b';
    }
}

// ── Select all / clear ───────────────────────────────────────────────────────
function toggleAll(checked) {
    // Toggle all checkboxes in BOTH views
    document.querySelectorAll('.item-check').forEach(function(cb) {
        cb.checked = checked;
        if (cb.classList.contains('tile-check')) {
            highlightTile(cb);
        } else {
            highlightTableRow(cb);
        }
    });
    var master = document.getElementById('masterCheck');
    if (master) master.checked = checked;
    updateCount();
}

// ── Sync tile → table ────────────────────────────────────────────────────────
function syncTableCheck(tileCb) {
    var itemId = tileCb.value;
    var tableCb = document.querySelector('.table-check[data-item-id="' + itemId + '"]');
    if (tableCb) {
        tableCb.checked = tileCb.checked;
        highlightTableRow(tableCb);
    }
}

// ── Sync table → tile ────────────────────────────────────────────────────────
function syncTileCheck(tableCb) {
    var itemId = tableCb.getAttribute('data-item-id');
    var tileCb = document.querySelector('.tile-check[value="' + itemId + '"]');
    if (tileCb) {
        tileCb.checked = tableCb.checked;
        highlightTile(tileCb);
    }
}

// ── Visual feedback — tile ───────────────────────────────────────────────────
function highlightTile(cb) {
    var tileCard = document.getElementById(cb.getAttribute('data-tile-id'));
    if (!tileCard) return;
    tileCard.style.borderColor = cb.checked ? '#16a34a' : '#e2e8f0';
    tileCard.style.boxShadow   = cb.checked ? '0 0 0 3px rgba(22,163,74,.15)' : '';
}

// ── Visual feedback — table row ──────────────────────────────────────────────
function highlightTableRow(cb) {
    var row = document.getElementById('tablerow-' + cb.getAttribute('data-item-id'));
    if (!row) return;
    row.style.background   = cb.checked ? '#f0fdf4' : '';
    row.style.borderLeft   = cb.checked ? '3px solid #16a34a' : '';
}

// ── Selected count ───────────────────────────────────────────────────────────
function updateCount() {
    // Count only tile checkboxes (authoritative — they hold name="selected_items[]")
    var count = document.querySelectorAll('.tile-check:checked').length;
    var el = document.getElementById('selectedCount');
    if (el) el.textContent = count;

    // Sync master checkbox state
    var total  = document.querySelectorAll('.tile-check').length;
    var master = document.getElementById('masterCheck');
    if (master) {
        master.indeterminate = count > 0 && count < total;
        master.checked = count === total && total > 0;
    }
}

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    switchView(currentView);
    updateCount();
});
</script>
@endpush
@endsection