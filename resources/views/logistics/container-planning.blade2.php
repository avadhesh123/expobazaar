@extends('layouts.app')
@section('title', 'Container Planning')
@section('page-title', 'Container Planning & Consolidation')

@section('content')
{{-- Capacity Reference --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Locked Live Sheets</div><div class="kpi-value">{{ $liveSheets->count() }}</div></div>
            <div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-clipboard-list"></i></div>
        </div>
    </div>
    <div class="kpi-card" style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Total Available CBM</div><div class="kpi-value">{{ number_format($totalCbm, 2) }}</div></div>
            <div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-cube"></i></div>
        </div>
    </div>
    <div class="kpi-card" style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">FCL Capacity</div><div class="kpi-value">{{ $fclCapacity }} <span style="font-size:.7rem;font-weight:400;">CBM</span></div></div>
            <div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-ship"></i></div>
        </div>
    </div>
    <div class="kpi-card" style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Estimated FCL Containers</div><div class="kpi-value">{{ $totalCbm > 0 ? ceil($totalCbm / $fclCapacity) : 0 }}</div></div>
            <div class="kpi-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-boxes"></i></div>
        </div>
    </div>
</div>

{{-- Shipment Builder --}}
<form method="POST" action="{{ route('logistics.shipments.create') }}" id="shipmentForm" onsubmit="return validateShipment()">
    @csrf

    {{-- Selection Controls --}}
    <div class="card" style="margin-bottom:1rem;border-color:#e8a838;">
        <div class="card-body" style="padding:.85rem 1.4rem;">
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
                {{-- CBM Gauge --}}
                <div style="flex:1;min-width:250px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;">
                        <span style="font-size:.72rem;font-weight:600;color:#64748b;">Selected CBM</span>
                        <span style="font-size:.82rem;font-weight:800;color:#0d1b2a;" id="selectedCbmText">0.00 / {{ $fclCapacity }} CBM</span>
                    </div>
                    <div style="height:24px;background:#e2e8f0;border-radius:6px;overflow:hidden;position:relative;">
                        <div id="cbmBar" style="height:100%;width:0%;border-radius:6px;transition:width .3s,background .3s;background:#16a34a;"></div>
                        {{-- FCL limit line --}}
                        <div style="position:absolute;top:0;bottom:0;left:{{ min(100, 100) }}%;width:2px;background:#0d1b2a;" title="FCL Limit: {{ $fclCapacity }} CBM"></div>
                    </div>
                    <div id="cbmWarning" style="display:none;margin-top:.3rem;padding:.3rem .6rem;background:#fee2e2;border-radius:6px;font-size:.75rem;color:#dc2626;font-weight:600;">
                        <i class="fas fa-exclamation-triangle"></i> CBM exceeds FCL capacity of {{ $fclCapacity }} CBM! Consider splitting into multiple shipments or using LCL.
                    </div>
                </div>

                {{-- Shipment Type --}}
                <div style="min-width:150px;">
                    <label style="font-size:.72rem;font-weight:600;color:#64748b;display:block;margin-bottom:.3rem;">Shipment Type <span style="color:#dc2626;">*</span></label>
                    <select name="shipment_type" id="shipmentType" required style="width:100%;padding:.45rem .6rem;border:1px solid #d1d5db;border-radius:8px;font-size:.85rem;font-weight:600;font-family:inherit;">
                        <option value="FCL">🚢 FCL (Full Container)</option>
                        <option value="LCL">📦 LCL (Less than Container)</option>
                        <option value="AIR">✈️ AIR</option>
                    </select>
                </div>

                {{-- Company Code --}}
                <div style="min-width:130px;">
                    <label style="font-size:.72rem;font-weight:600;color:#64748b;display:block;margin-bottom:.3rem;">Company Code <span style="color:#dc2626;">*</span></label>
                    <select name="company_code" required style="width:100%;padding:.45rem .6rem;border:1px solid #d1d5db;border-radius:8px;font-size:.85rem;font-family:inherit;">
                        <option value="2000">2000 – India</option>
                        <option value="2100" selected>2100 – USA</option>
                        <option value="2200">2200 – NL</option>
                    </select>
                </div>

                {{-- Optional Fields --}}
                <div style="min-width:130px;">
                    <label style="font-size:.72rem;font-weight:600;color:#64748b;display:block;margin-bottom:.3rem;">Container Number</label>
                    <input type="text" name="container_number" placeholder="e.g. MSKU1234567" style="width:100%;padding:.45rem .6rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                </div>

                <div style="min-width:120px;">
                    <label style="font-size:.72rem;font-weight:600;color:#64748b;display:block;margin-bottom:.3rem;">Port of Loading</label>
                    <input type="text" name="port_of_loading" placeholder="e.g. JNPT" style="width:100%;padding:.45rem .6rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn btn-primary" style="white-space:nowrap;">
                    <i class="fas fa-ship" style="margin-right:.3rem;"></i> Create Shipment
                </button>
            </div>
        </div>
    </div>

    {{-- Live Sheets Table --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-list" style="margin-right:.5rem;color:#1e3a5f;"></i> Consignments — Ready for Shipment</h3>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <label style="display:flex;align-items:center;gap:.3rem;font-size:.82rem;font-weight:600;cursor:pointer;">
                    <input type="checkbox" id="selectAll" onclick="toggleAll(this)" style="width:16px;height:16px;accent-color:#e8a838;">
                    Select All
                </label>
                <span style="font-size:.82rem;color:#64748b;">(<span id="selectedCount">0</span> selected)</span>
            </div>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" onclick="toggleAll(this)" style="accent-color:#e8a838;"></th>
                       
                        <th>Consignment #</th>
                        <th>Vendor</th>
                        <th>Country</th>
                        <th>Company</th>
                        <th>Items</th>
                        <th>CBM</th>
                        <th>Locked</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($liveSheets as $ls)
                    @php
                        $con = $ls->consignment;
                    @endphp
                    <tr id="row{{ $con->id }}">
                        <td>
                            <input type="checkbox" name="consignment_ids[]" value="{{ $con->id }}"
                                class="consignment-cb" data-cbm="{{ $ls->total_cbm }}"
                                onchange="updateSelection()"
                                style="width:17px;height:17px;accent-color:#e8a838;cursor:pointer;">
                        </td>
                        <td style="font-weight:600;font-family:monospace;font-size:.82rem;">{{ $ls->live_sheet_number }}</td>
                        <td>
                            <div style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $con->consignment_number }}</div>
                        </td>
                        <td>
                            <div style="font-size:.82rem;font-weight:500;">{{ $con->vendor->company_name ?? '—' }}</div>
                            <div style="font-size:.68rem;color:#94a3b8;">{{ $con->vendor->vendor_code ?? '' }}</div>
                        </td>
                        <td>
                            @php $flags = ['US'=>'🇺🇸','NL'=>'🇳🇱','IN'=>'🇮🇳']; @endphp
                            <span style="font-size:.9rem;">{{ $flags[$con->destination_country] ?? '' }}</span>
                            <span style="font-weight:600;">{{ $con->destination_country }}</span>
                        </td>
                        <td>
                            @php $ccBg = ['2000'=>'#dcfce7','2100'=>'#dbeafe','2200'=>'#fef3c7']; @endphp
                            <span style="padding:.15rem .45rem;background:{{ $ccBg[$con->company_code] ?? '#f1f5f9' }};border-radius:5px;font-size:.78rem;font-weight:600;">{{ $con->company_code }}</span>
                        </td>
                        <td style="text-align:center;font-weight:600;">{{ $con->total_items }}</td>
                        <td>
                            <span style="font-family:monospace;font-weight:800;font-size:.9rem;color:#1e3a5f;">{{ number_format($ls->total_cbm, 2) }}</span>
                            <span style="font-size:.68rem;color:#94a3b8;">CBM</span>
                        </td>
                        <td>
                            <div style="font-size:.72rem;color:#166534;"><i class="fas fa-lock" style="margin-right:.2rem;"></i>{{ $ls->locked_at?->format('d M Y') }}</div>
                            <div style="font-size:.65rem;color:#94a3b8;">by {{ $ls->lockedByUser->name ?? '—' }}</div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:3rem;color:#94a3b8;">
                            <i class="fas fa-clipboard-check" style="font-size:2.5rem;display:block;margin-bottom:.5rem;color:#16a34a;"></i>
                            <div style="font-size:.9rem;font-weight:600;">No Consignment available</div>
                            <!-- <div style="font-size:.82rem;">Live sheets need to be approved and locked by the Sourcing team before they appear here.</div> -->
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>

{{-- Process Info --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-info-circle" style="margin-right:.5rem;color:#7c3aed;"></i> Container Planning Process</h3></div>
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;">
            <div style="padding:.85rem;background:#eff6ff;border-radius:10px;border-left:3px solid #1e40af;">
                <div style="font-size:.72rem;font-weight:700;color:#1e40af;text-transform:uppercase;margin-bottom:.3rem;">Step 1</div>
                <div style="font-size:.82rem;font-weight:600;color:#0d1b2a;">Select Consignments</div>
                <div style="font-size:.72rem;color:#64748b;">Use checkboxes to select locked live sheets. Watch the CBM gauge fill up.</div>
            </div>
            <div style="padding:.85rem;background:#fefce8;border-radius:10px;border-left:3px solid #e8a838;">
                <div style="font-size:.72rem;font-weight:700;color:#e8a838;text-transform:uppercase;margin-bottom:.3rem;">Step 2</div>
                <div style="font-size:.82rem;font-weight:600;color:#0d1b2a;">Choose Shipment Type</div>
                <div style="font-size:.72rem;color:#64748b;">FCL (65 CBM max), LCL, or AIR. System warns if CBM exceeds capacity.</div>
            </div>
            <div style="padding:.85rem;background:#f0fdf4;border-radius:10px;border-left:3px solid #16a34a;">
                <div style="font-size:.72rem;font-weight:700;color:#16a34a;text-transform:uppercase;margin-bottom:.3rem;">Step 3</div>
                <div style="font-size:.82rem;font-weight:600;color:#0d1b2a;">Create Shipment</div>
                <div style="font-size:.72rem;color:#64748b;">Unique shipment code generated. Moves to shipment tracking window.</div>
            </div>
            <div style="padding:.85rem;background:#faf5ff;border-radius:10px;border-left:3px solid #7c3aed;">
                <div style="font-size:.72rem;font-weight:700;color:#7c3aed;text-transform:uppercase;margin-bottom:.3rem;">Step 4</div>
                <div style="font-size:.82rem;font-weight:600;color:#0d1b2a;">Lock & Generate ASN</div>
                <div style="font-size:.72rem;color:#64748b;">Set sailing date → Lock shipment → ASN auto-generated → Sent to HOD for pricing.</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
var fclCapacity = {{ $fclCapacity }};

function toggleAll(el) {
    document.querySelectorAll('.consignment-cb').forEach(function(cb) { cb.checked = el.checked; });
    updateSelection();
}

function updateSelection() {
    var total = 0;
    var count = 0;
    document.querySelectorAll('.consignment-cb:checked').forEach(function(cb) {
        total += parseFloat(cb.getAttribute('data-cbm'));
        count++;
    });

    document.getElementById('selectedCount').textContent = count;
    document.getElementById('selectedCbmText').textContent = total.toFixed(2) + ' / ' + fclCapacity + ' CBM';

    // Update CBM bar
    var pct = Math.min((total / fclCapacity) * 100, 100);
    var bar = document.getElementById('cbmBar');
    bar.style.width = pct + '%';

    // Color based on utilization
    if (total > fclCapacity) {
        bar.style.background = '#dc2626';
        document.getElementById('cbmWarning').style.display = 'block';
    } else if (total > fclCapacity * 0.85) {
        bar.style.background = '#e8a838';
        document.getElementById('cbmWarning').style.display = 'none';
    } else {
        bar.style.background = '#16a34a';
        document.getElementById('cbmWarning').style.display = 'none';
    }

    // Highlight selected rows
    document.querySelectorAll('.consignment-cb').forEach(function(cb) {
        var row = cb.closest('tr');
        row.style.background = cb.checked ? '#fffbeb' : '';
        row.style.borderLeft = cb.checked ? '3px solid #e8a838' : '';
    });
}

function validateShipment() {
    var count = document.querySelectorAll('.consignment-cb:checked').length;
    if (count === 0) {
        alert('Please select at least one consignment.');
        return false;
    }

    var total = 0;
    document.querySelectorAll('.consignment-cb:checked').forEach(function(cb) {
        total += parseFloat(cb.getAttribute('data-cbm'));
    });

    var type = document.getElementById('shipmentType').value;

    if (type === 'FCL' && total > fclCapacity) {
        return confirm('WARNING: Total CBM (' + total.toFixed(2) + ') exceeds FCL capacity (' + fclCapacity + ' CBM).\n\nDo you still want to create this shipment?');
    }

    return confirm('Create ' + type + ' shipment with ' + count + ' consignment(s)?\nTotal CBM: ' + total.toFixed(2));
}
</script>
@endpush
@endsection
