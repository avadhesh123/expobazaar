@extends('layouts.app')
@section('title', 'Container Planning')
@section('page-title', 'Container Planning & Consolidation')

@section('content')
@php $fclCapacity = 65; @endphp

<div style="display:flex;gap:1rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">Available Consignments</div>
                <div class="kpi-value">{{ $consignments->count() }}</div>
            </div>
            <div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-box"></i></div>
        </div>
    </div>
    <div class="kpi-card" style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">Total CBM</div>
                <div class="kpi-value">{{ number_format($totalCbm, 2) }}</div>
            </div>
            <div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-cube"></i></div>
        </div>
    </div>
    <div class="kpi-card" style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">FCL Capacity</div>
                <div class="kpi-value">{{ $fclCapacity }} <span style="font-size:.7rem;font-weight:400;">CBM</span></div>
            </div>
            <div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-ship"></i></div>
        </div>
    </div>
    <div class="kpi-card" style="flex:1;">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div>
                <div class="kpi-label">Est. FCL Containers</div>
                <div class="kpi-value">{{ $totalCbm > 0 ? ceil($totalCbm / $fclCapacity) : 0 }}</div>
            </div>
            <div class="kpi-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-boxes"></i></div>
        </div>
    </div>
    <!-- <form method="POST" action="{{ route('logistics.consignments.update-values') }}" style="display:inline;"
    onsubmit="return confirm('Recalculate total value for all consignments?')">
    @csrf
    <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-sync" style="margin-right:.2rem;"></i> Recalculate All Values
    </button>
</form> -->

</div>

<form method="POST" action="{{ route('logistics.shipments.create') }}" id="shipmentForm" onsubmit="return validateShipment()">
    @csrf
    <div class="card" style="margin-bottom:1rem;border-color:#e8a838;">
        <div class="card-body" style="padding:.85rem 1.4rem;">
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
               
                <div style="flex:1;min-width:250px;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:.3rem;">
                        <span style="font-size:.72rem;font-weight:600;color:#64748b;">Selected CBM</span>
                        <span style="font-size:.82rem;font-weight:800;color:#0d1b2a;" id="selectedCbmText">0.00 / {{ $fclCapacity }} CBM</span>
                    </div>
                    <div style="height:24px;background:#e2e8f0;border-radius:6px;overflow:hidden;position:relative;">
                        <div id="cbmBar" style="height:100%;width:0%;border-radius:6px;transition:width .3s,background .3s;background:#16a34a;"></div>
                    </div>
                    <div id="cbmWarning" style="display:none;font-size:.72rem;color:#dc2626;font-weight:600;margin-top:.2rem;"><i class="fas fa-exclamation-triangle"></i> Over FCL capacity!</div>
                </div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Type</label><select name="shipment_type" required style="padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                        <option value="FCL">FCL (65 CBM)</option>
                        <option value="LCL">LCL (30 CBM)</option>
                        <option value="AIR">AIR (10 CBM)</option>
                    </select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label>

                    <span style="padding:.15rem .4rem;background:{{ $activeCode === '2400' ? '#dcfce7' : ($activeCode === '2100' ? '#dbeafe' : '#fef3c7') }};border-radius:5px;font-size:.78rem;font-weight:600;">
                        {{ $activeCode === '2400' ? '🇬🇧 2400 UK' : ($activeCode === '2100' ? '🇺🇸 2100 USA' : '🇪🇺 2200 EU') }}
                    </span>
                    <input type="hidden" name="company_code" value="{{ $activeCode }}">
                </div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Container #</label>
                    <input type="text" name="container_number" placeholder="Optional" style="width:120px;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                </div>
                 {{-- Add this inside each form, before the submit button --}}
                <div style="background-color: #eba6a6;">
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Created Date </label>
                    <input type="date" name="custom_date" value="{{ old('custom_date', date('Y-m-d')) }}" style="max-width:200px;">
                </div>
                {{-- End of Date Input --}}
                <button type="submit" class="btn btn-primary"><i class="fas fa-ship" style="margin-right:.3rem;"></i> Create Shipment</button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-box" style="margin-right:.5rem;color:#2d6a4f;"></i> Available Consignments</h3><span style="font-size:.72rem;color:#64748b;">Only consignments not yet assigned to a shipment</span>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;text-align:center;"><input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="width:16px;height:16px;"></th>
                        <th>Consignment #</th>
                        <th>Vendor</th>
                        <th>Factory Location</th>
                        <th>Goods Ready Date</th>
                        <th>Country</th>
                        <th>Total Skus</th>
                        <th>Total Qty</th>
                        <th>Total FOB</th>
                        <th>Total Net Wt</th>
                        <th>Total Gross Wt</th>
                        <th>Total Master Cartons</th>
                        <th>CBM</th>
                        <th>Value</th>
                        <th>Remarks</th>
                        <th>Live Sheet</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consignments as $con)
                    <tr class="consignment-row" data-company-code="{{ $con->company_code }}">
                        <td style="text-align:center;"><input type="checkbox" name="consignment_ids[]" value="{{ $con->id }}" class="con-check" data-cbm="{{ $con->total_cbm }}" onchange="updateCbm()" style="width:16px;height:16px;accent-color:#16a34a;"></td>
                        <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $con->consignment_number }}</td>
                        <td>
                            <div style="font-size:.82rem;">{{ $con->vendor->company_name ?? '—' }}</div>
                            <div style="font-size:.68rem;color:#94a3b8;">{{ $con->vendor->vendor_code ?? '' }}</div>
                        </td>
                        <td>{{ $con->liveSheet->factory_location ?? '—' }}</td>
                        <td>{{ $con->liveSheet && $con->liveSheet->ex_factory_date ? $con->liveSheet->ex_factory_date->format('d M Y') : '—' }}</td>
                        <td>@php $fl=['US'=>'🇺🇸','NL'=>'🇳🇱','IN'=>'🇮🇳','UK'=>'🇬🇧','EU'=>'🇪🇺']; @endphp {{ $fl[$con->destination_country] ?? '' }} {{ $con->destination_country }}</td>
                       
                        <td>{{ number_format($con->stats['total_skus']) }}</td>
                        <td>{{ number_format($con->stats['total_qty']) }}</td>
                        <td>{{ $activeCurrencySymbol }}{{ number_format($con->stats['total_fob'], 2) }}</td>
                        <td>{{ number_format($con->stats['total_net_wt'], 2) }} kg</td>
                        <td>{{ number_format($con->stats['total_gross_wt'], 2) }} kg</td>
                        <td>{{ number_format($con->stats['total_master_cartons']) }}</td>
 

                        <!-- <th>Total Qty(sum of Final Qty)</th>
                        <th>Total FOB(Final FOB)</th>
                        <th>Total Net Wt(ntw = wt* Final Qty = sum of ntw (in kg))</th>
                        <th>Total Gross Wt()</th>
                        <th>Total Master Cartons</th> -->
                         <td style="font-family:monospace;font-weight:700;">{{ number_format($con->total_cbm, 3) }} <span style="font-size:.65rem;color:#94a3b8;">CBM</span></td>
                        <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($con->total_value, 2) }}</td>
                       
                        {{-- Add to each row --}}
                        <td>
                            <div style="display:flex;align-items:center;gap:.3rem;">
                                <span id="remark-text-{{ $con->id }}" style="font-size:.72rem;color:#64748b;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                    title="{{ $con->remarks ?? '' }}">
                                    {{ $con->remarks ? \Str::limit($con->remarks, 30) : '—' }}
                                </span>
                                <button type="button" onclick="openRemarkEdit({{ $con->id }}, '{{ addslashes($con->remarks ?? '') }}')"
                                    style="background:none;border:none;cursor:pointer;color:#e8a838;font-size:.65rem;padding:2px;" title="Edit remarks">
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </td>
                        <td>@if($con->liveSheet)
                            <span class="badge badge-success">
                                
                            </span>
                            <a href="{{ route('logistics.container-planning.download-livesheet', $con) }}" 
                                class="btn btn-success btn-sm" title="Download Live Sheet">
                                    <i class="fas fa-lock" style="font-size:.5rem;margin-right:.15rem;"></i>  {{ $con->liveSheet->live_sheet_number }}
                                </a>
                        @else<span class="badge badge-gray">—</span>@endif</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-check-circle" style="font-size:2rem;color:#16a34a;display:block;margin-bottom:.5rem;"></i>All consignments have been assigned to shipments.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>
{{-- Consignment Remarks Modal --}}
<div id="remarkModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;width:460px;max-width:92%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <div style="padding:.75rem 1.25rem;border-bottom:1px solid #fde68a;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:.9rem;font-weight:700;color:#854d0e;margin:0;">
                <i class="fas fa-comment-alt" style="margin-right:.3rem;color:#e8a838;"></i> Consignment Remarks
            </h3>
            <button onclick="closeRemarkModal()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:#94a3b8;">&times;</button>
        </div>
        <form method="POST" id="remarkForm" action="">
            @csrf
            <div style="padding:1rem 1.25rem;">
                <textarea name="remarks" id="remarkInput" rows="4" required
                    placeholder="e.g. Qty mismatch: EB-001 shows 100 in PO but 95 received. Approved to proceed..."
                    style="width:100%;padding:.5rem .65rem;border:1.5px solid #fde68a;border-radius:8px;font-size:.85rem;font-family:inherit;resize:vertical;"></textarea>
                <div style="font-size:.6rem;color:#94a3b8;margin-top:.2rem;">
                    <i class="fas fa-info-circle"></i> This remark will also be visible in the shipment after it is generated.
                </div>
            </div>
            <div style="padding:.6rem 1.25rem;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:.4rem;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeRemarkModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" style="padding:.4rem .7rem;">
                    <i class="fas fa-save" style="margin-right:.2rem;"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRemarkEdit(consignmentId, currentRemark) {
    document.getElementById('remarkForm').action = '/logistics/container-planning/' + consignmentId + '/remarks';
    document.getElementById('remarkInput').value = currentRemark.replace(/\\n/g, '\n');
    document.getElementById('remarkModal').style.display = 'flex';
    document.getElementById('remarkInput').focus();
}

function closeRemarkModal() {
    document.getElementById('remarkModal').style.display = 'none';
}

document.getElementById('remarkModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeRemarkModal();
});
</script>
@push('scripts')
<script>
    var capacities = {
        'FCL': 65,
        'LCL': 30,
        'AIR': 10
    };
    var fclCapacity = capacities[document.querySelector('[name="shipment_type"]').value] || 65;

    // Update capacity when shipment type changes
    document.querySelector('[name="shipment_type"]').addEventListener('change', function() {
        fclCapacity = capacities[this.value] || 65;
        updateCbm();
    });

    function toggleAll(m) {
        document.querySelectorAll('.consignment-row').forEach(function(row) {
            if (row.style.display !== 'none') {
                var cb = row.querySelector('.con-check');
                if (cb) cb.checked = m.checked;
            }
        });
        updateCbm();
    }

    function updateCbm() {
        var t = 0;
        document.querySelectorAll('.con-check:checked').forEach(function(c) {
            var row = c.closest('.consignment-row');
            if (row && row.style.display !== 'none') {
                t += parseFloat(c.dataset.cbm) || 0;
            }
        });
        var p = fclCapacity > 0 ? (t / fclCapacity) * 100 : 0;
        document.getElementById('selectedCbmText').textContent = t.toFixed(2) + ' / ' + fclCapacity + ' CBM';
        var b = document.getElementById('cbmBar');
        b.style.width = Math.min(p, 100) + '%';
        b.style.background = p > 100 ? '#dc2626' : (p > 85 ? '#e8a838' : '#16a34a');
        document.getElementById('cbmWarning').style.display = p > 100 ? 'block' : 'none';
    }

    function validateShipment() {
        var checked = [];
        document.querySelectorAll('.con-check:checked').forEach(function(c) {
            var row = c.closest('.consignment-row');
            if (row && row.style.display !== 'none') checked.push(c);
        });
        if (checked.length === 0) {
            alert('Select at least one consignment.');
            return false;
        }
        // Uncheck hidden rows before submit
        document.querySelectorAll('.con-check:checked').forEach(function(c) {
            var row = c.closest('.consignment-row');
            if (row && row.style.display === 'none') c.checked = false;
        });
        return confirm('Create shipment with ' + checked.length + ' consignment(s)?');
    }
</script>
@endpush


<script>
    (function() {
        var select = document.getElementById('companyFilter');

        function filterConsignments() {
            var selected = select.value;
            document.querySelectorAll('.consignment-row').forEach(function(row) {
                row.style.display = (row.getAttribute('data-company-code') === selected) ? '' : 'none';
            });
            // Uncheck hidden rows and update CBM
            document.querySelectorAll('.consignment-row').forEach(function(row) {
                if (row.style.display === 'none') {
                    var cb = row.querySelector('.con-check');
                    if (cb) cb.checked = false;
                }
            });
            document.getElementById('selectAll').checked = false;
            if (typeof updateCbm === 'function') updateCbm();
        }
        select.addEventListener('change', filterConsignments);
        filterConsignments();
    })();
</script>


@endsection