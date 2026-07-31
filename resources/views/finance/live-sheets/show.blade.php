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
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Live Sheet</div>
                <div style="font-weight:700;font-family:monospace;">{{ $liveSheet->live_sheet_number }}</div>
            </div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Vendor</div>
                <div style="font-weight:600;">{{ $liveSheet->vendor->company_name ?? '—' }}</div>
            </div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Company</div>
                <div>{{ $liveSheet->company_code }}</div>
            </div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Items</div>
                <div style="font-weight:700;">{{ $liveSheet->items->count() }}</div>
            </div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Status</div>
                <div><span class="badge {{ $liveSheet->is_locked?'badge-success':'badge-warning' }}">{{ $liveSheet->is_locked?'Locked':ucfirst($liveSheet->status) }}</span></div>
            </div>
             
        </div>
    </div>

    {{-- Flash messages --}}
    <!-- @if(session('success'))
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
    @endif -->

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
                            <i class="fas fa-file-excel" style="margin-right:.3rem;color:#16a34a;"></i> Download Template
                        </div>
                        <div style="font-size:.72rem;color:#6d28d9;">
                            Excel with Item ID, Vendor SKU, Product Name &amp; current SAP Code, current Vendor WSP.<br>
                            Fill columns E and G and upload below.
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
                        <input type="file" name="sap_file" required
                            style="flex:1;min-width:180px;font-size:.78rem;padding:.35rem .5rem;border:1px solid #93c5fd;border-radius:6px;background:#fff;">
                        <button type="submit" onclick="return confirm('Upload and apply SAP codes and Vendor WSPs from this file?')"
                            style="padding:.42rem .85rem;background:#1e40af;color:#fff;border:none;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;">
                            <i class="fas fa-cloud-upload-alt" style="margin-right:.3rem;"></i> Upload & Apply
                        </button>
                    </form>
                    @error('sap_file')
                    <span style="font-size:.72rem;color:#dc2626;margin-top:.25rem;display:block;">{{ $message }}</span>
                    @enderror
                    <div style="font-size:.68rem;color:#64748b;margin-top:.35rem;">
                        <i class="fas fa-info-circle" style="margin-right:.2rem;"></i>
                        Only column E (SAP Code) and column G (Vendor WSP) are read. Columns A - C are reference only. Blank cells are skipped.
                    </div>
                </div>

            </div>
        </div>
    </div>

{{-- Commission Revisions — add this to finance/live-sheets/show.blade.php --}}

@php
    $revisions = \App\Models\CommissionRevision::where('live_sheet_id', $liveSheet->id)
        ->orderByDesc('effective_from')
        ->with('creator')
        ->get();
    $activeRevision = $revisions->first(function($r) {
        return $r->effective_from <= now() && ($r->effective_to === null || $r->effective_to >= now());
    });
@endphp

<div class="card" style="margin-bottom:1.25rem;border-color:#ddd6fe;">
    <div class="card-header">
        <h3><i class="fas fa-percent" style="margin-right:.5rem;color:#7c3aed;"></i> Commission Rate</h3>
        <div style="display:flex;align-items:center;gap:.5rem;">
            @if($activeRevision)
            <span style="padding:.2rem .6rem;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;font-size:.82rem;font-weight:700;color:#7c3aed;">
                Current: {{ $activeRevision->commission_percentage }}%
            </span>
            @elseif($liveSheet->commission_percentage)
            <!-- <span style="padding:.2rem .6rem;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;font-size:.82rem;font-weight:700;color:#7c3aed;">
                Base: {{ $liveSheet->commission_percentage }}%
            </span> -->
            @endif
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('commRevisionForm').style.display=document.getElementById('commRevisionForm').style.display==='none'?'block':'none'">
                <i class="fas fa-plus"></i> Add Revision
            </button>
        </div>
    </div>

    {{-- Add Revision Form --}}
    <div id="commRevisionForm" style="display:none;padding:1rem 1.4rem;background:#f5f3ff;border-bottom:1px solid #ddd6fe;">
        <div style="font-size:.72rem;color:#7c3aed;margin-bottom:.5rem;">
            <i class="fas fa-info-circle"></i>
            Set a commission rate for a specific period. Leave "Effective To" blank if it should stay active until the next revision.
        </div>
        <form method="POST" action="{{ route('finance.live-sheets.commission.store', $liveSheet) }}">
            @csrf
            <div style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;">
                <div>
                    <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Commission % <span style="color:#dc2626;">*</span></label>
                    <input type="number" name="commission_percentage" step="0.01" min="0" max="100" required placeholder="e.g. 12"
                        style="width:90px;padding:.35rem .5rem;border:1px solid #ddd6fe;border-radius:6px;font-size:.85rem;font-family:monospace;text-align:center;">
                </div>
                <div>
                    <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Effective From <span style="color:#dc2626;">*</span></label>
                    <input type="date" name="effective_from" required value="{{ date('Y-m-d') }}"
                        style="padding:.35rem .5rem;border:1px solid #ddd6fe;border-radius:6px;font-size:.82rem;">
                </div>
                <div>
                    <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Effective To <span style="font-size:.55rem;color:#94a3b8;">(optional)</span></label>
                    <input type="date" name="effective_to"
                        style="padding:.35rem .5rem;border:1px solid #ddd6fe;border-radius:6px;font-size:.82rem;">
                </div>
                <div style="flex:1;min-width:120px;">
                    <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Remarks</label>
                    <input type="text" name="remarks" placeholder="e.g. Q3 revised rate"
                        style="width:100%;padding:.35rem .5rem;border:1px solid #ddd6fe;border-radius:6px;font-size:.82rem;">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save" style="margin-right:.2rem;"></i> Save</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('commRevisionForm').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>

    {{-- Revision History --}}
    <div class="card-body" style="padding:.85rem 1.25rem;">
        @if($revisions->isNotEmpty())
        <table class="data-table" style="font-size:.78rem;margin:0;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="width:30px;">#</th>
                    <th style="text-align:center;">Commission %</th>
                    <th>Effective From</th>
                    <th>Effective To</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Added By</th>
                    <th>Date</th>
                    <th style="width:40px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($revisions as $idx => $rev)
                @php
                    $isActive = $rev->effective_from <= now() && ($rev->effective_to === null || $rev->effective_to >= now());
                    $isFuture = $rev->effective_from > now();
                    $isExpired = $rev->effective_to && $rev->effective_to < now();
                @endphp
                <tr style="{{ $isActive ? 'background:#f0fdf4;border-left:3px solid #16a34a;' : ($isFuture ? 'background:#eff6ff;' : '') }}">
                    <td style="text-align:center;color:#94a3b8;">{{ $revisions->count() - $idx }}</td>
                    <td style="text-align:center;">
                        <span style="padding:.2rem .5rem;border-radius:4px;font-weight:700;font-size:.85rem;font-family:monospace;
                            {{ $isActive ? 'background:#dcfce7;color:#16a34a;' : ($isFuture ? 'background:#dbeafe;color:#1e40af;' : 'color:#94a3b8;') }}">
                            {{ $rev->commission_percentage }}%
                        </span>
                    </td>
                    <td style="font-family:monospace;font-size:.75rem;">{{ $rev->effective_from->format('d M Y') }}</td>
                    <td style="font-family:monospace;font-size:.75rem;">
                        @if($rev->effective_to)
                            {{ $rev->effective_to->format('d M Y') }}
                        @else
                            <span style="font-size:.65rem;color:#16a34a;font-style:italic;">Open-ended</span>
                        @endif
                    </td>
                    <td>
                        @if($isActive)
                            <span class="badge badge-success" style="font-size:.6rem;">Active</span>
                        @elseif($isFuture)
                            <span class="badge badge-info" style="font-size:.6rem;">Scheduled</span>
                        @else
                            <span class="badge badge-gray" style="font-size:.6rem;">Expired</span>
                        @endif
                    </td>
                    <td style="font-size:.72rem;color:#64748b;">{{ $rev->remarks ?? '—' }}</td>
                    <td style="font-size:.72rem;">{{ $rev->creator->name ?? '—' }}</td>
                    <td style="font-size:.68rem;color:#94a3b8;">{{ $rev->created_at->format('d M Y') }}</td>
                    <td>
                        <form method="POST" action="{{ route('finance.commission-revision.delete', $rev) }}" onsubmit="return confirm('Delete this revision?')" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:.72rem;padding:2px;" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:.82rem;">
            <i class="fas fa-percent" style="font-size:1.5rem;display:block;margin-bottom:.3rem;"></i>
            No commission revisions yet. Using base rate: <strong>{{ $liveSheet->commission_percentage ?? 0 }}%</strong>
            <div style="font-size:.72rem;margin-top:.3rem;">Click "+ Add Revision" to set period-specific commission rates.</div>
        </div>
        @endif
    </div>
</div>


    {{-- SAP Code Form --}}
    <form method="POST" action="{{ route('finance.live-sheets.sap', $liveSheet) }}">
        @csrf
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-barcode" style="margin-right:.5rem;color:#e8a838;"></i> Product SAP Codes and Vendor WSPs</h3>
                <div style="display:flex;gap:.5rem;align-items:center;">
                    @php
                    $totalItems = $liveSheet->items->count();
                    $filledItems = $liveSheet->items->filter(fn($i) => !empty(($i->product_details ?? [])['sap_code'] ?? ''))->count();
                    $vendorWspItems = $liveSheet->items->filter(fn ($i) => !empty(($i->product_details ?? [])['vendor_wsp'] ?? ''))->count();
                    @endphp
                    <span style="font-size:.75rem;color:#64748b;">
                        <span style="font-weight:700;color:{{ $filledItems === $totalItems ? '#16a34a' : '#e8a838' }};">SapCode:{{ $filledItems }}</span>
                        / {{ $totalItems }} assigned

                        <span style="font-weight:700;color:{{ $vendorWspItems === $totalItems ? '#16a34a' : '#e8a838' }};">V.WSP:{{ $vendorWspItems }}</span>
                        / {{$vendorWspItems}} assigned
                    </span>
                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Save all data?')">
                        <i class="fas fa-save" style="margin-right:.3rem;"></i> Save Data
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr style="background:#f0f4f8;">
                            <th style="width:30px;">S.no</th>
                            <th style="min-width:110px;">Vendor SKU</th>
                            <th style="min-width:200px;">Product Name</th>
                            <th style="min-width:80px;">Category</th>
                            <th style="min-width:50px;">FOB</th>
                            <th style="min-width:40px;">WSP</th>
                            <th style="min-width:30px;">Qty</th>
                            <th style="min-width:100px;">Barcode</th>
                            <th style="min-width:130px;background:#eff6ff;">SAP Code *</th>
                            <th style="min-width:110px;background:#e0ded5;">Vendor WSP</th>
                            <th style="background:#e0ded5;">Eeffective From</th>
                            <th style="background:#e0ded5;">Eeffective To</th>
                            <th style="background:#e0ded5;width:60px;"></th>
                            <th style="width:60px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach($liveSheet->items as $idx => $item)
                        @php $d = $item->product_details ?? [];
                        $sapCode = $item->product->sap_code ?? $d['sap_code'] ?? '';
                        $vendorWsp =  $item->product->vendor_wsp ?? $d['vendor_wsp'] ?? $d['vendor_payout_price'] ?? '';

                      //  $finalFob = floatval($d['final_fob'] ?? $item->unit_price ?? 0);
                      ////  $dutyAmt = $finalFob * (floatval($d['duty_percent'] ?? 0) / 100);
                     //   $freightAmt = floatval($d['freight_factor'] ?? 0) * $finalFob;
                      //  $landedCost = $finalFob + $dutyAmt + $freightAmt;
                      //  $wsp = $landedCost * floatval($d['wsp_factor'] ?? 0); 

                        
                    $finalFob = (float)($d['final_fob'] ?? $item->unit_price);
                    $dutyPercent = (float)($d['duty_percent'] ?? 0);
                    $freightFactor = (float)($d['freight_factor'] ?? 0);
                    $wspFactor = (float)($d['wsp_factor'] ?? 0);

                    $dutyAmt = $finalFob * ($dutyPercent / 100);
                    $freightAmt = $finalFob * ($freightFactor / 100);
                    $landedCost = $finalFob + $dutyAmt + $freightAmt;
                    $wsp = $landedCost * $wspFactor;
 
                    $wspRevision = \App\Models\WspRevision::getActiveWsp($liveSheet->id, $item->product_id);
                    $currentWsp = $wspRevision['wsp'] ?? null;
                 
                    $effectiveFrom = !empty($wspRevision['effective_from'])
                        ? \Carbon\Carbon::parse($wspRevision['effective_from'])->format('Y-m-d')
                        : null;

                    $effectiveTo = !empty($wspRevision['effective_to'])
                        ? \Carbon\Carbon::parse($wspRevision['effective_to'])->format('Y-m-d')
                        : null;

                    $wspCount = \App\Models\WspRevision::where('live_sheet_id', $liveSheet->id)->where('product_id', $item->product_id)->count();
 
                    @endphp
                        <tr style="{{ !empty($sapCode) ? 'background:#f0fdf4;' : '' }}">
                            <td style="text-align:center;color:#94a3b8;">{{ $idx + 1 }}</td>
                            <td style="font-family:monospace;font-weight:600;font-size:.82rem;">{{ $item->product->sku ?? '—' }}</td>
                            <td style="font-size:.82rem;font-weight:500;">{{ $item->product->name ?? '—' }}</td>
                            <td style="font-size:.78rem;">{{ $d['category'] ?? '—' }}</td>
                            <td style="font-family:monospace;font-weight:600;">{{ $activeCurrencySymbol . number_format($finalFob, 2) }}</td>
                            <td style="font-family:monospace;text-align:center;font-weight:600;">{{ $activeCurrencySymbol . number_format($wsp, 2) }}</td>
                            <td style="text-align:center;">{{ $item->quantity }}</td>
                            <td style="font-family:monospace;font-size:.78rem;">{{ $d['barcode'] ?? '—' }}</td>
                            <td style="background:#eff6ff;">
                                <input type="hidden" name="sap_codes[{{ $idx }}][item_id]" value="{{ $item->id }}">
                                <input type="text" name="sap_codes[{{ $idx }}][sap_code]" value="{{ $sapCode }}" placeholder="Enter SAP code..."
                                    style="width:100%;padding:.35rem .5rem;border:1px solid {{ !empty($sapCode) ? '#86efac' : '#93c5fd' }};border-radius:6px;font-size:.82rem;font-family:monospace;background:#fff;">
                            </td>
                            <td style="background: #e0ded5;">
                                <input type="number" step="0.01"
                                    name="sap_codes[{{ $idx }}][vendor_wsp]"
                                    value="{{  $currentWsp ?? $vendorWsp }}"
                                    placeholder="0.00"
                                    id="wsp-{{ $item->id }}"
                                    style="width:100%;padding:.35rem .5rem;border:1px solid {{ !empty($vendorWsp) ? '#86efac' : '#93c5fd' }};border-radius:6px;font-size:.82rem;font-family:monospace;background:#fff;">
                            </td>                            
                            <td style="background:#e0ded5;">
                                <input type="date" id="wsp-from-{{ $item->id }}" value="{{ $effectiveFrom ?? date('Y-m-d') }}"
                                    style="width:110px;padding:.2rem .3rem;border:1px solid #fde68a;border-radius:4px;font-size:.7rem;">
                            </td>
                            <td style="background:#e0ded5;"> 
                                <input type="date" id="wsp-to-{{ $item->id }}" value="{{ $effectiveTo ?? '' }}"
                                    style="width:110px;padding:.2rem .3rem;border:1px solid #fde68a;border-radius:4px;font-size:.7rem;">
                            </td>
                            <td style="background:#e0ded5;text-align:center;">
                                <button type="button" onclick="saveWsp({{ $item->id }}, {{ $item->product_id }})" class="btn btn-outline btn-sm" style="padding:2px 6px;" title="Save WSP">
                                    <i class="fas fa-save" style="color:#e8a838;"></i>
                                </button>
                                @if($wspCount > 0)
                                <button type="button" onclick="showWspHistory({{ $item->product_id }}, '{{ $item->product->sku ?? '' }}')" style="background:none;border:none;cursor:pointer;padding:2px;position:relative;" title="{{ $wspCount }} revision(s)">
                                    <i class="fas fa-history" style="color:#94a3b8;font-size:.7rem;"></i>
                                    <span style="position:absolute;top:-4px;right:-4px;background:#e8a838;color:#fff;font-size:.5rem;width:12px;height:12px;border-radius:50%;display:flex;align-items:center;justify-content:center;">{{ $wspCount }}</span>
                                </button>
                                @endif
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
            <button type="submit" class="btn btn-primary" onclick="return confirm('Save all data?')">
                <i class="fas fa-save" style="margin-right:.3rem;"></i> Save Data
            </button>
        </div>
    </form>
{{-- WSP Save + History Modal --}}
<div id="wspHistoryModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;width:600px;max-width:92%;max-height:80vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.15);">
        <div style="padding:.75rem 1.25rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:#fff;border-radius:12px 12px 0 0;">
            <h3 style="font-size:.95rem;font-weight:700;color:#0d1b2a;margin:0;">
                <i class="fas fa-history" style="color:#e8a838;margin-right:.3rem;"></i>
                WSP History — <span id="wspHistorySku" style="font-family:monospace;color:#e8a838;"></span>
            </h3>
            <button onclick="document.getElementById('wspHistoryModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:1.3rem;color:#94a3b8;">&times;</button>
        </div>
        <div id="wspHistoryContent" style="padding:1rem 1.25rem;">
            <div style="text-align:center;color:#94a3b8;padding:2rem;">Loading...</div>
        </div>
    </div>
</div>

<script>
function saveWsp(itemId, productId) {
    var wsp = document.getElementById('wsp-' + itemId).value;
    var from = document.getElementById('wsp-from-' + itemId).value;
    var to = document.getElementById('wsp-to-' + itemId).value;

    if (!wsp || !from) {
        alert('WSP and Effective From are required.');
        return;
    }

    fetch('{{ route("finance.live-sheets.wsp.store", $liveSheet) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            vendor_wsp: wsp,
            effective_from: from,
            effective_to: to || null,
            product_id: productId,
            remarks: null,
        })
    })
    .then(function(r) {
        if (r.redirected) {
            window.location.reload();
            return;
        }
        return r.json();
    })
    .then(function(data) {
        if (data && data.error) {
            alert(data.error);
        } else {
            // Flash green on saved input
            var input = document.getElementById('wsp-' + itemId);
            input.style.borderColor = '#16a34a';
            input.style.background = '#f0fdf4';
            setTimeout(function() {
                input.style.borderColor = '#fde68a';
                input.style.background = '';
            }, 2000);

            // Update history badge
            location.reload();
        }
    })
    .catch(function(err) { alert('Error: ' + err.message); });
}

function showWspHistory(productId, sku) {
    document.getElementById('wspHistorySku').textContent = sku;
    var modal = document.getElementById('wspHistoryModal');
    var content = document.getElementById('wspHistoryContent');
    modal.style.display = 'flex';
    content.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:2rem;">Loading...</div>';

    // Load history via inline data (no extra AJAX needed)
    var rows = document.querySelectorAll('.wsp-history-row[data-product="' + productId + '"]');
    if (rows.length === 0) {
        content.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:2rem;"><i class="fas fa-dollar-sign" style="font-size:1.5rem;display:block;margin-bottom:.3rem;"></i>No WSP revisions yet.</div>';
        return;
    }

    var html = '<table style="width:100%;font-size:.78rem;border-collapse:collapse;">';
    html += '<thead><tr style="background:#f0f4f8;"><th style="padding:.4rem .6rem;text-align:left;">#</th><th style="padding:.4rem .6rem;text-align:center;">WSP</th><th style="padding:.4rem .6rem;">From</th><th style="padding:.4rem .6rem;">To</th><th style="padding:.4rem .6rem;">Status</th><th style="padding:.4rem .6rem;">By</th><th style="padding:.4rem .6rem;">Date</th></tr></thead><tbody>';

    rows.forEach(function(row, idx) {
        html += row.innerHTML;
    });
    html += '</tbody></table>';
    content.innerHTML = html;
}

// Close modal on backdrop
document.getElementById('wspHistoryModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

{{-- Hidden history rows for each product --}}
@php
    $allWspRevisions = \App\Models\WspRevision::where('live_sheet_id', $liveSheet->id)
        ->whereNotNull('product_id')
        ->orderByDesc('effective_from')
        ->with('creator')
        ->get()
        ->groupBy('product_id');
@endphp

@foreach($allWspRevisions as $pid => $revisions)
@foreach($revisions as $idx => $rev)
@php
    $isActive = $rev->effective_from <= now() && ($rev->effective_to === null || $rev->effective_to >= now());
    $isFuture = $rev->effective_from > now();
@endphp
<template class="wsp-history-row" data-product="{{ $pid }}">
    <tr style="{{ $isActive ? 'background:#fefce8;border-left:3px solid #e8a838;' : '' }}">
        <td style="padding:.3rem .6rem;color:#94a3b8;">{{ $revisions->count() - $idx }}</td>
        <td style="padding:.3rem .6rem;text-align:center;">
            <span style="padding:.15rem .4rem;border-radius:4px;font-weight:700;font-family:monospace;{{ $isActive ? 'background:#fef3c7;color:#854d0e;' : ($isFuture ? 'background:#dbeafe;color:#1e40af;' : 'color:#94a3b8;') }}">
                {{ $activeCurrencySymbol ?? '$' }}{{ number_format($rev->vendor_wsp, 2) }}
            </span>
        </td>
        <td style="padding:.3rem .6rem;font-family:monospace;font-size:.72rem;">{{ $rev->effective_from->format('d M Y') }}</td>
        <td style="padding:.3rem .6rem;font-family:monospace;font-size:.72rem;">{{ $rev->effective_to ? $rev->effective_to->format('d M Y') : 'Open' }}</td>
        <td style="padding:.3rem .6rem;">
            @if($isActive)<span style="color:#e8a838;font-weight:700;font-size:.65rem;">Active</span>
            @elseif($isFuture)<span style="color:#1e40af;font-size:.65rem;">Scheduled</span>
            @else<span style="color:#94a3b8;font-size:.65rem;">Expired</span>@endif
        </td>
        <td style="padding:.3rem .6rem;font-size:.7rem;">{{ $rev->creator->name ?? '—' }}</td>
        <td style="padding:.3rem .6rem;font-size:.68rem;color:#94a3b8;">{{ $rev->created_at->format('d M Y') }}</td>
    </tr>
</template>
@endforeach
@endforeach
    <script>
        $(document).ready(function() {

            $('#saveCommissionBtn').on('click', function() {
                const btn = $(this);
                const originalText = btn.html();
                const liveSheetId = {{ $liveSheet->id }};
                const commission = $('#commission_percentage').val();

                // Basic validation
                if (commission < 0 || commission > 100) {
                    showStatus('Commission must be between 0 and 100', 'error');
                    return;
                }

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

                $.ajax({
                    url: '/finance/live-sheets/' + liveSheetId + '/commission',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        commission_percentage: commission
                    },
                    success: function(response) {
                        if (response.success) {
                            showStatus('✓ Saved successfully', 'success');
                            btn.html('<i class="fas fa-check"></i> Saved');

                            setTimeout(() => {
                                btn.html(originalText).prop('disabled', false);
                            }, 1500);
                        }
                    },
                    error: function() {
                        showStatus('Failed to save. Please try again.', 'error');
                        btn.html(originalText).prop('disabled', false);
                    }
                });
            });

            function showStatus(message, type) {
                const statusEl = $('#commission_status');
                statusEl.html(message);

                if (type === 'success') {
                    statusEl.css('color', '#166534');
                } else {
                    statusEl.css('color', '#dc2626');
                }

                setTimeout(() => {
                    statusEl.html('');
                }, 5000);
            }
        });
    </script>
    @endsection