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
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" required style="padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                        <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>🇮🇳 2000</option>
                        <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>🇺🇸 2100</option>
                        <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>🇳🇱 2200</option>

                    </select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Container #</label><input type="text" name="container_number" placeholder="Optional" style="width:120px;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
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
                        <th>Items</th>
                        <th>CBM</th>
                        <th>Value</th>
                        <th>Live Sheet</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consignments as $con)
                    <tr>
                        <td style="text-align:center;"><input type="checkbox" name="consignment_ids[]" value="{{ $con->id }}" class="con-check" data-cbm="{{ $con->total_cbm }}" onchange="updateCbm()" style="width:16px;height:16px;accent-color:#16a34a;"></td>
                        <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $con->consignment_number }}</td>
                        <td>
                            <div style="font-size:.82rem;">{{ $con->vendor->company_name ?? '—' }}</div>
                            <div style="font-size:.68rem;color:#94a3b8;">{{ $con->vendor->vendor_code ?? '' }}</div>
                        </td>
                        <td>{{ $con->factory_location ?? '—' }}</td>
                        <td>{{ $con->goods_ready_date ? date('d M Y', strtotime($con->goods_ready_date)) : '—' }}</td>
                        <td>@php $fl=['US'=>'🇺🇸','NL'=>'🇳🇱','IN'=>'🇮🇳']; @endphp {{ $fl[$con->destination_country] ?? '' }} {{ $con->destination_country }}</td>
                        <td style="text-align:center;font-weight:600;">{{ $con->total_items }}</td>
                        <td style="font-family:monospace;font-weight:700;">{{ number_format($con->total_cbm, 2) }} <span style="font-size:.65rem;color:#94a3b8;">CBM</span></td>
                        <td style="font-family:monospace;">${{ number_format($con->total_value, 2) }}</td>
                        <td>@if($con->liveSheet)<span class="badge badge-success"><i class="fas fa-lock" style="font-size:.5rem;margin-right:.15rem;"></i> {{ $con->liveSheet->live_sheet_number }}</span>@else<span class="badge badge-gray">—</span>@endif</td>
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

@push('scripts')
<script>
    var fclCapacity = {
        {
            $fclCapacity
        }
    };

    function toggleAll(m) {
        document.querySelectorAll('.con-check').forEach(function(c) {
            c.checked = m.checked;
        });
        updateCbm();
    }

    function updateCbm() {
        var t = 0;
        document.querySelectorAll('.con-check:checked').forEach(function(c) {
            t += parseFloat(c.dataset.cbm) || 0;
        });
        var p = fclCapacity > 0 ? (t / fclCapacity) * 100 : 0;
        document.getElementById('selectedCbmText').textContent = t.toFixed(2) + ' / ' + fclCapacity + ' CBM';
        var b = document.getElementById('cbmBar');
        b.style.width = Math.min(p, 100) + '%';
        b.style.background = p > 100 ? '#dc2626' : (p > 85 ? '#e8a838' : '#16a34a');
        document.getElementById('cbmWarning').style.display = p > 100 ? 'block' : 'none';
    }

    function validateShipment() {
        var c = document.querySelectorAll('.con-check:checked');
        if (c.length === 0) {
            alert('Select at least one consignment.');
            return false;
        }
        return confirm('Create shipment with ' + c.length + ' consignment(s)?');
    }
</script>
@endpush
@endsection