@extends('layouts.app')
@section('title', 'Offer Sheet: ' . $offerSheet->offer_sheet_number)
@section('page-title', 'Offer Sheet — ' . $offerSheet->offer_sheet_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('vendor.offer-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Sheets</a>
    <a href="{{ route('vendor.offer-sheets.download', $offerSheet) }}" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Download CSV</a>
</div>

{{-- Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:1.1rem;font-weight:800;color:#0d1b2a;font-family:monospace;">{{ $offerSheet->offer_sheet_number }}</div>
                <div style="font-size:.82rem;color:#64748b;">Company: {{ $offerSheet->company_code }} · {{ $offerSheet->total_products }} products · {{ $offerSheet->selected_products }} selected</div>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;">
                @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-warning','under_review'=>'badge-info','selection_done'=>'badge-success','live_sheet_created'=>'badge-info','converted'=>'badge-success']; @endphp
                <span class="badge {{ $sc[$offerSheet->status] ?? 'badge-gray' }}" style="font-size:.85rem;padding:.3rem .8rem;">{{ ucfirst(str_replace('_',' ',$offerSheet->status)) }}</span>
            </div>
        </div>

        @if($offerSheet->selected_products > 0)
        <div style="margin-top:.75rem;">
            <div style="display:flex;justify-content:space-between;margin-bottom:.25rem;">
                <span style="font-size:.72rem;font-weight:600;color:#64748b;">Selection Progress</span>
                <span style="font-size:.72rem;font-weight:700;color:#166534;">{{ $offerSheet->selected_products }} / {{ $offerSheet->total_products }}</span>
            </div>
            <div style="height:8px;background:#e2e8f0;border-radius:4px;">
                @php $pct = $offerSheet->total_products > 0 ? round(($offerSheet->selected_products / $offerSheet->total_products) * 100) : 0; @endphp
                <div style="height:100%;width:{{ $pct }}%;background:#16a34a;border-radius:4px;"></div>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Products Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-table" style="margin-right:.5rem;color:#1e3a5f;"></i> Products ({{ $offerSheet->items->count() }})</h3>
        <span style="font-size:.72rem;color:#64748b;">Scroll right to see all columns →</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="min-width:1400px;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="width:40px;text-align:center;position:sticky;left:0;background:#f0f4f8;z-index:2;">
                        <i class="fas fa-check-square" style="color:#16a34a;"></i>
                    </th>
                    <th style="width:40px;">S.no</th>
                    <th style="min-width:100px;">Vendor SKU</th>
                    <th style="min-width:180px;position:sticky;left:40px;background:#f0f4f8;z-index:2;">Product Name</th>
                    <th style="width:70px;">Image</th>
                    <th>Length (in)</th>
                    <th>Width (in)</th>
                    <th>Height (in)</th>
                    <th>Weight (g)</th>
                    <th style="min-width:100px;">Material</th>
                    <th>Color</th>
                    <th>Finish</th>
                    <th>Category</th>
                    <th>Sub Category</th>
                    <th>FOB ($)</th>
                    <th style="min-width:120px;">Comments</th>
                </tr>
            </thead>
            <tbody>
                @foreach($offerSheet->items as $item)
                @php $d = $item->product_details ?? []; @endphp
                <tr style="{{ $item->is_selected ? 'background:#f0fdf4;border-left:3px solid #16a34a;' : '' }}">
                    <td style="text-align:center;position:sticky;left:0;background:{{ $item->is_selected?'#f0fdf4':'#fff' }};z-index:1;">
                        @if($item->is_selected)
                            <i class="fas fa-check-circle" style="color:#16a34a;font-size:1rem;"></i>
                        @else
                            <i class="far fa-circle" style="color:#d1d5db;font-size:1rem;"></i>
                        @endif
                    </td>
                    <td style="text-align:center;color:#94a3b8;font-weight:600;">{{ $d['sno'] ?? $loop->iteration }}</td>
                    <td style="font-family:monospace;font-weight:600;font-size:.82rem;">{{ $item->product_sku }}</td>
                    <td style="font-weight:600;font-size:.82rem;position:sticky;left:40px;background:{{ $item->is_selected?'#f0fdf4':'#fff' }};z-index:1;">{{ $item->product_name }}</td>
                    <td style="text-align:center;">
                        @php
                            $imgUrl = null;
                            if ($item->thumbnail) {
                                // Handle different path formats
                                if (str_starts_with($item->thumbnail, 'http')) {
                                    $imgUrl = $item->thumbnail;
                                } elseif (str_starts_with($item->thumbnail, 'offer-thumbnails/') || str_starts_with($item->thumbnail, 'offer-')) {
                                    $imgUrl = asset('storage/' . $item->thumbnail);
                                } elseif (str_starts_with($item->thumbnail, 'storage/')) {
                                    $imgUrl = asset($item->thumbnail);
                                } else {
                                    $imgUrl = asset('storage/' . $item->thumbnail);
                                }
                            }
                        @endphp
                        @if($imgUrl)
                            <a href="{{ $imgUrl }}" target="_blank" title="Click to enlarge">
                                <img src="{{ $imgUrl }}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;cursor:zoom-in;" onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
                                <i class="fas fa-image" style="color:#fca5a5;display:none;" title="Image not found"></i>
                            </a>
                        @else
                            <i class="fas fa-image" style="color:#d1d5db;"></i>
                        @endif

                        <button type="button" 
                id="upload-btn-{{ $item->id }}"
                class="btn btn-sm btn-light" 
                style="bottom:-2px; right:-2px; padding:2px 5px; font-size:0.7rem; border-radius:50%;"
                title="Upload / Replace Image"
                onclick="uploadProductImage({{ $item->id }})">
            <i class="fas fa-upload"></i>
        </button><input type="file" 
           id="file-input-{{ $item->id }}" 
           accept="image/*" 
           style="display:none;" 
           onchange="handleImageUpload(event, {{ $item->id }})">
                    </td>
                    <td style="text-align:center;font-family:monospace;font-size:.82rem;">{{ $d['length_inches'] ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-size:.82rem;">{{ $d['width_inches'] ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-size:.82rem;">{{ $d['height_inches'] ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-size:.82rem;">{{ $d['weight_grams'] ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $d['material'] ?? '—' }}</td>
                    <td style="font-size:.82rem;">
                        @if(!empty($d['color']))
                        <span style="display:inline-flex;align-items:center;gap:.2rem;"><span style="width:10px;height:10px;border-radius:50%;background:#e2e8f0;border:1px solid #d1d5db;"></span> {{ $d['color'] }}</span>
                        @else — @endif
                    </td>
                    <td style="font-size:.82rem;">{{ $d['finish'] ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $d['category'] ?? ($item->category->name ?? '—') }}</td>
                    <td style="font-size:.82rem;">{{ $d['sub_category'] ?? '—' }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">${{ number_format($item->vendor_price, 2) }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $d['comments'] ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div style="margin-top:.75rem;display:flex;gap:1.5rem;font-size:.75rem;color:#64748b;">
    <span><i class="fas fa-check-circle" style="color:#16a34a;margin-right:.2rem;"></i> Selected by Sourcing Team</span>
    <span><i class="far fa-circle" style="color:#d1d5db;margin-right:.2rem;"></i> Not selected</span>
</div>

@if($offerSheet->status === 'submitted')
<div style="margin-top:1rem;padding:.75rem 1rem;background:#fef3c7;border-radius:8px;font-size:.82rem;color:#92400e;"><i class="fas fa-clock" style="margin-right:.3rem;"></i> Your offer sheet is under review. The Sourcing Team will select products using the checkbox and notify you once selection is done.</div>
@endif

<script>
function handleImageUpload(event, itemId) {
    const file = event.target.files[0];
    if (!file) return;

    // Basic validation
    if (!file.type.startsWith('image/')) {
        alert('Please select a valid image file.');
        return;
    }
    if (file.size > 5 * 1024 * 1024) { // 5MB limit
        alert('Image size must be less than 5MB.');
        return;
    }

    const formData = new FormData();
    formData.append('image', file);
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('offer_sheet_item_id', itemId);

    // Show loading state
    const uploadBtn = document.getElementById(`upload-btn-${itemId}`);
    const originalIcon = uploadBtn.innerHTML;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    uploadBtn.disabled = true;

    fetch("{{ route('vendor.offer-sheet.upload-image') }}", {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update image preview instantly
            const img = document.getElementById(`img-${itemId}`);
            if (img) {
                img.src = data.image_url;
                img.style.display = 'inline';
            } else {
                // If no img tag existed, reload row or show new image
                location.reload();
            }
            alert('Image uploaded successfully!');
        } else {
            alert(data.message || 'Upload failed.');
        }
    })
    .catch(error => {
        console.error(error);
        alert('An error occurred during upload.');
    })
    .finally(() => {
        uploadBtn.innerHTML = originalIcon;
        uploadBtn.disabled = false;
    });
}

function uploadProductImage(itemId) {
    document.getElementById(`file-input-${itemId}`).click();
}
</script>
@endsection