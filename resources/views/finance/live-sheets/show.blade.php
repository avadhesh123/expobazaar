@extends('layouts.app')
@section('title', 'SAP Codes — ' . $liveSheet->live_sheet_number)
@section('page-title', 'SAP Code Entry — ' . $liveSheet->live_sheet_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('finance.live-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Live Sheets</a>
</div>

{{-- Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;">
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Live Sheet</div><div style="font-weight:700;font-family:monospace;">{{ $liveSheet->live_sheet_number }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Vendor</div><div style="font-weight:600;">{{ $liveSheet->vendor->company_name ?? '—' }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Company</div><div>{{ $liveSheet->company_code }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Items</div><div style="font-weight:700;">{{ $liveSheet->items->count() }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Status</div><div><span class="badge {{ $liveSheet->is_locked?'badge-success':'badge-warning' }}">{{ $liveSheet->is_locked?'Locked':ucfirst($liveSheet->status) }}</span></div></div>
        </div>
    </div>
</div>

{{-- Flash messages --}}
@if(session('success'))
<div style="padding:.75rem 1rem;background:#dcfce7;border:1px solid #86efac;border-radius:8px;margin-bottom:1rem;display:flex;gap:.6rem;align-items:flex-start;">
    <i class="fas fa-check-circle" style="color:#16a34a;margin-top:.1rem;flex-shrink:0;"></i>
    <div>
        <div style="font-size:.85rem;font-weight:700;color:#166534;">{{ session('success') }}</div>
        @if(session('upload_errors'))
            <ul style="margin:.3rem 0 0 0;padding-left:1.1rem;font-size:.78rem;color:#166534;line-height:1.7;">
                @foreach(session('upload_errors') as $err)<li>{!! $err !!}</li>@endforeach
            </ul>
        @endif
    </div>
</div>
@endif

@if(session('warning'))
<div style="padding:.75rem 1rem;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;margin-bottom:1rem;display:flex;gap:.6rem;align-items:flex-start;">
    <i class="fas fa-exclamation-triangle" style="color:#e8a838;margin-top:.1rem;flex-shrink:0;"></i>
    <div>
        <div style="font-size:.85rem;font-weight:700;color:#92400e;">{{ session('warning') }}</div>
        @if(session('upload_errors'))
            <ul style="margin:.3rem 0 0 0;padding-left:1.1rem;font-size:.78rem;color:#92400e;line-height:1.7;">
                @foreach(session('upload_errors') as $err)<li>{!! $err !!}</li>@endforeach
            </ul>
        @endif
    </div>
</div>
@endif

<!-- @if(session('error'))
<div style="padding:.75rem 1rem;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;margin-bottom:1rem;display:flex;gap:.6rem;align-items:center;">
    <i class="fas fa-exclamation-circle" style="color:#dc2626;flex-shrink:0;"></i>
    <span style="font-size:.85rem;font-weight:600;color:#991b1b;">{{ session('error') }}</span>
</div>
@endif -->

{{-- Download / Upload bar --}}
<div class="card" style="margin-bottom:1.25rem;border-color:#ddd6fe;">
    <div class="card-body" style="padding:.85rem 1.25rem;">
        <div style="display:flex;flex-wrap:wrap;gap:1.25rem;align-items:stretch;">

            {{-- Download --}}
            <div style="flex:1;min-width:260px;padding:.75rem 1rem;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:10px;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
                <div>
                    <div style="font-size:.82rem;font-weight:700;color:#4c1d95;margin-bottom:.2rem;">
                        <i class="fas fa-file-excel" style="margin-right:.3rem;color:#16a34a;"></i> Download SAP Template
                    </div>
                    <div style="font-size:.72rem;color:#6d28d9;">
                        Excel with Item ID, Vendor SKU, Product Name &amp; current SAP Code.<br>
                        Fill column D and upload below.
                    </div>
                </div>
                <a href="{{ route('finance.live-sheets.sap-download', $liveSheet) }}"
                    style="display:inline-flex;align-items:center;gap:.35rem;padding:.45rem .9rem;background:#7c3aed;color:#fff;border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none;white-space:nowrap;flex-shrink:0;">
                    <i class="fas fa-download"></i> Download Excel
                </a>
            </div>

            {{-- Upload --}}
            <div style="flex:1;min-width:280px;padding:.75rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;">
                <div style="font-size:.82rem;font-weight:700;color:#1e40af;margin-bottom:.5rem;">
                    <i class="fas fa-upload" style="margin-right:.3rem;color:#1e40af;"></i> Upload Filled Template
                </div>
                <form method="POST" action="{{ route('finance.live-sheets.sap-upload', $liveSheet) }}"
                    enctype="multipart/form-data"
                    style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                    @csrf
                    <input type="file" name="sap_file" accept=".xlsx,.xls" required
                        style="flex:1;min-width:180px;font-size:.78rem;padding:.35rem .5rem;border:1px solid #93c5fd;border-radius:6px;background:#fff;">
                    <button type="submit" onclick="return confirm('Upload and apply SAP codes from this file?')"
                        style="padding:.42rem .85rem;background:#1e40af;color:#fff;border:none;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;">
                        <i class="fas fa-cloud-upload-alt" style="margin-right:.3rem;"></i> Upload & Apply
                    </button>
                </form>
                @error('sap_file')
                    <span style="font-size:.72rem;color:#dc2626;margin-top:.25rem;display:block;">{{ $message }}</span>
                @enderror
                <div style="font-size:.68rem;color:#64748b;margin-top:.35rem;">
                    <i class="fas fa-info-circle" style="margin-right:.2rem;"></i>
                    Only column D (SAP Code) is read. Columns A–C are reference only. Blank SAP cells are skipped.
                </div>
            </div>

        </div>
    </div>
</div>

{{-- SAP Code Form --}}
<form method="POST" action="{{ route('finance.live-sheets.sap', $liveSheet) }}">
    @csrf
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-barcode" style="margin-right:.5rem;color:#e8a838;"></i> Product SAP Codes</h3>
            <div style="display:flex;gap:.5rem;align-items:center;">
                @php
                    $totalItems  = $liveSheet->items->count();
                    $filledItems = $liveSheet->items->filter(fn($i) => !empty(($i->product_details ?? [])['sap_code'] ?? ''))->count();
                @endphp
                <span style="font-size:.75rem;color:#64748b;">
                    <span style="font-weight:700;color:{{ $filledItems === $totalItems ? '#16a34a' : '#e8a838' }};">{{ $filledItems }}</span>
                    / {{ $totalItems }} assigned
                </span>
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Save all SAP codes?')">
                    <i class="fas fa-save" style="margin-right:.3rem;"></i> Save SAP Codes
                </button>
            </div>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr style="background:#f0f4f8;">
                        <th style="width:40px;">S.no</th>
                        <th style="min-width:120px;">Vendor SKU</th>
                        <th style="min-width:200px;">Product Name</th>
                        <th style="min-width:80px;">Category</th>
                        <th style="min-width:70px;">FOB ($)</th>
                        <th style="min-width:50px;">Qty</th>
                        <th style="min-width:100px;">Barcode</th>
                        <th style="min-width:160px;background:#eff6ff;">SAP Code *</th>
                        <th style="width:60px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($liveSheet->items as $idx => $item)
                    @php $d = $item->product_details ?? []; $sapCode = $d['sap_code'] ?? ''; @endphp
                    <tr style="{{ !empty($sapCode) ? 'background:#f0fdf4;' : '' }}">
                        <td style="text-align:center;color:#94a3b8;">{{ $idx + 1 }}</td>
                        <td style="font-family:monospace;font-weight:600;font-size:.82rem;">{{ $item->product->sku ?? '—' }}</td>
                        <td style="font-size:.82rem;font-weight:500;">{{ $item->product->name ?? '—' }}</td>
                        <td style="font-size:.78rem;">{{ $d['category'] ?? '—' }}</td>
                        <td style="font-family:monospace;font-weight:600;">${{ number_format($item->unit_price, 2) }}</td>
                        <td style="text-align:center;">{{ $item->quantity }}</td>
                        <td style="font-family:monospace;font-size:.78rem;">{{ $d['barcode'] ?? '—' }}</td>
                        <td style="background:#eff6ff;">
                            <input type="hidden" name="sap_codes[{{ $idx }}][item_id]" value="{{ $item->id }}">
                            <input type="text" name="sap_codes[{{ $idx }}][sap_code]" value="{{ $sapCode }}" placeholder="Enter SAP code..."
                                style="width:100%;padding:.35rem .5rem;border:1px solid {{ !empty($sapCode) ? '#86efac' : '#93c5fd' }};border-radius:6px;font-size:.82rem;font-family:monospace;background:#fff;">
                        </td>
                        <td style="text-align:center;">
                            @if(!empty($sapCode))
                                <i class="fas fa-check-circle" style="color:#16a34a;" title="SAP code assigned"></i>
                            @else
                                <i class="fas fa-clock" style="color:#e8a838;" title="Pending SAP code"></i>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:1rem;display:flex;gap:.5rem;justify-content:flex-end;">
        <a href="{{ route('finance.live-sheets') }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Save all SAP codes?')">
            <i class="fas fa-save" style="margin-right:.3rem;"></i> Save SAP Codes
        </button>
    </div>
</form>
@endsection