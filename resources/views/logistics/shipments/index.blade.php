@extends('layouts.app')
@section('title', 'Shipment Tracking')
@section('page-title', 'Shipment Tracking')

@section('content')
{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('logistics.shipments') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="min-width:140px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @foreach(['planning','consolidated','locked','asn_generated','in_transit','arrived','grn_pending','grn_completed','cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <!-- <div style="min-width:110px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label>
                <select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>🇮🇳 2000</option>
                    <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>🇺🇸 2100</option>
                    <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>🇳🇱 2200</option>
                </select>
            </div> -->
            <div style="min-width:100px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Type</label>
                <select name="type" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    <option value="FCL" {{ request('type')==='FCL'?'selected':'' }}>FCL</option>
                    <option value="LCL" {{ request('type')==='LCL'?'selected':'' }}>LCL</option>
                    <option value="AIR" {{ request('type')==='AIR'?'selected':'' }}>AIR</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('logistics.shipments') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('logistics.container-planning') }}" class="btn btn-secondary btn-sm" style="margin-left:auto;"><i class="fas fa-cubes"></i> New Shipment</a>
        </form>
    </div>
</div>

{{-- Shipments Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-ship" style="margin-right:.5rem;color:#1e3a5f;"></i> All Shipments</h3><span style="font-size:.78rem;color:#64748b;">{{ $shipments->total() }} total</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Shipment Code</th>
                    <th>Type</th>
                    <th>Company / Country</th>
                    <th>Vendors</th>
                    <th>CBM</th>
                    <th style="text-align:center;min-width:90px;">No. of Pallets</th>
                    <th style="text-align:center;min-width:90px;">Manpower (No of Hours)</th>

                    <th>Utilization</th>
                    <th>Sailing Date</th>
                    <th>ETA</th>
                    <th>Status</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shipments as $sh)
                @php
                $utilPct = $sh->capacity_cbm > 0 ? round(($sh->total_cbm / $sh->capacity_cbm) * 100) : 0;
                
                $consignmentRemarks = $sh->consignments->filter(fn ($c) => !empty($c->remarks));

                @endphp
                <tr>
                    <td> 
                        <a href="{{ route('logistics.shipments.show', $sh) }}"  class="mono-link">{{ $sh->shipment_code }}</a>
                        @if($sh->container_number)<div style="font-size:.65rem;color:#94a3b8;">Container: {{ $sh->container_number }}</div>@endif
                    </td>
                    <td>
                        @php $typeBg = ['FCL'=>['#dbeafe','#1e40af','🚢'],'LCL'=>['#fef3c7','#92400e','📦'],'AIR'=>['#ede9fe','#6d28d9','✈️']]; @endphp
                        <span style="display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .5rem;background:{{ $typeBg[$sh->shipment_type][0] ?? '#f1f5f9' }};border-radius:6px;font-size:.8rem;font-weight:700;color:{{ $typeBg[$sh->shipment_type][1] ?? '#475569' }};">
                            {{ $typeBg[$sh->shipment_type][2] ?? '' }} {{ $sh->shipment_type }}
                        </span>
                    </td>
                    <td>
                        @php $flags = ['US'=>'🇺🇸','NL'=>'🇳🇱','IN'=>'🇮🇳']; $ccBg = ['2000'=>'#dcfce7','2100'=>'#dbeafe','2200'=>'#fef3c7']; @endphp
                        <span style="padding:.15rem .4rem;background:{{ $ccBg[$sh->company_code] ?? '#f1f5f9' }};border-radius:5px;font-size:.78rem;font-weight:600;">{{ $sh->company_code }}</span>
                        <span style="margin-left:.3rem;">{{ $flags[$sh->destination_country] ?? '' }} {{ $sh->destination_country }}</span>
                    </td>
                    <td style="font-size:.78rem;max-width:180px;">{{ $sh->consignments->pluck('vendor.company_name')->unique()->implode(', ') }}</td>
                    <td style="font-family:monospace;font-weight:700;">{{ number_format($sh->total_cbm, 2) }}</td>
                    <td style="text-align:center;">
                        <input type="number" min="0" max="9999"
                            value="{{ $sh->no_of_pallets ?? '' }}"
                            placeholder="—"
                            class="pallet-input"
                            data-shipment-id="{{ $sh->id }}"
                            style="width:70px;padding:.25rem .3rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:monospace;text-align:center;">
                        <div class="pallet-status-{{ $sh->id }}" style="font-size:.5rem;height:.7rem;margin-top:.1rem;"></div>
                    </td>
                    <td style="text-align:center;">
                        <input type="number" max="9999"
                            step="0.01"
                            value="{{ $sh->manpower_no_of_hours ?? '' }}"
                            placeholder="0"
                            class="manpower-input"
                            data-shipment-id="{{ $sh->id }}"
                            style="width:70px;padding:.25rem .3rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:monospace;text-align:center;">
                        <div class="manpower-status-{{ $sh->id }}" style="font-size:.5rem;height:.7rem;margin-top:.1rem;"></div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;min-width:60px;">
                                <div style="height:100%;width:{{ min($utilPct, 100) }}%;border-radius:4px;background:{{ $utilPct > 100 ? '#dc2626' : ($utilPct > 85 ? '#e8a838' : '#16a34a') }};"></div>
                            </div>
                            <span style="font-size:.72rem;font-weight:700;color:{{ $utilPct > 100 ? '#dc2626' : '#64748b' }};">{{ $utilPct }}%</span>
                        </div>

                        @if($consignmentRemarks->isNotEmpty())
                                <div style="font-size:.65rem;color:#94a3b8;">{{ $consignmentRemarks->count() }} consignment(s) with remarks</div>
                        @endif
                    </td>
                    <td>
                        @if($sh->sailing_date)
                        <div style="font-size:.82rem;font-weight:600;">{{ $sh->sailing_date->format('d M Y') }}</div>
                        @else
                        <span style="font-size:.75rem;color:#e8a838;font-weight:600;">Not set</span>
                        @endif
                    </td>
                    <td>
                        @if($sh->eta_date)
                        <div style="font-size:.82rem;">{{ $sh->eta_date->format('d M Y') }}</div>
                        @else
                        <span style="color:#94a3b8;font-size:.75rem;">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                        $sc = [
                        'planning'=>['badge-gray','fa-drafting-compass'],'consolidated'=>['badge-info','fa-boxes'],
                        'locked'=>['badge-info','fa-lock'],'asn_generated'=>['badge-warning','fa-file-alt'],
                        'in_transit'=>['badge-warning','fa-ship'],'arrived'=>['badge-success','fa-check-circle'],
                        'grn_pending'=>['badge-warning','fa-clipboard-check'],'grn_completed'=>['badge-success','fa-check-double'],
                        'cancelled'=>['badge-danger','fa-times-circle'],
                        ];
                        @endphp
                        <span class="badge {{ $sc[$sh->status][0] ?? 'badge-gray' }}">
                            <i class="fas {{ $sc[$sh->status][1] ?? 'fa-circle' }}" style="margin-right:.2rem;font-size:.55rem;"></i>
                            {{ ucfirst(str_replace('_',' ',$sh->status)) }}
                        </span>
                        <!-- <select class="shipment-status-select" data-id="{{ $sh->id }}"
                            style="padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.75rem;font-weight:600;
        {{ $sh->status === 'locked' ? 'background:#f0fdf4;color:#16a34a;' : ($sh->status === 'cancelled' ? 'background:#fef2f2;color:#dc2626;' : 'background:#fefce8;color:#854d0e;') }}">
                            <option value="planning" {{ $sh->status === 'planning' ? 'selected' : '' }}>Planning</option>
                            <option value="consolidated" {{ $sh->status === 'consolidated' ? 'selected' : '' }}>Consolidated</option>
                            <option value="in_transit" {{ $sh->status === 'in_transit' ? 'selected' : '' }}>In Transit</option>
                            <option value="grn_pending" {{ $sh->status === 'grn_pending' ? 'selected' : '' }}>GRN pending</option>
                            <option value="grn_completed" {{ $sh->status === 'grn_completed' ? 'selected' : '' }}>GRN completed</option>
                            <option value="delivered" {{ $sh->status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                            <option value="arrived" {{ $sh->status === 'arrived' ? 'selected' : '' }}>Arrived</option>

                            <option value="locked" {{ $sh->status === 'locked' ? 'selected' : '' }}>Locked</option>
                            <option value="reopened">↩ Reopen for Planning</option>
                            <option value="cancelled" {{ $sh->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>

                        </select>
                        <span class="shipment-status-msg-{{ $sh->id }}" style="font-size:.55rem;display:block;margin-top:.1rem;"></span>
                     -->
                    </td>
                    <td>
                        <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
                            <a href="{{ route('logistics.shipments.show', $sh) }}" class="btn btn-outline btn-sm" title="View Details"><i class="fas fa-eye"></i></a>
                            @if($sh->status === 'consolidated')
                            <button type="button" class="btn btn-primary btn-sm" title="Set Sailing Date & Lock" onclick="document.getElementById('lockPanel{{ $sh->id }}').style.display=document.getElementById('lockPanel{{ $sh->id }}').style.display==='none'?'table-row':'none'">
                                <i class="fas fa-lock"></i>
                            </button>
                            @endif
                            @if(in_array($sh->status, ['asn_generated','locked']) && $sh->asn)
                            <a href="{{ route('logistics.asn.download', $sh->asn) }}" class="btn btn-outline btn-sm" title="Download ASN"><i class="fas fa-download"></i></a>
                            @endif

                            @if(!in_array($sh->status, ['cancelled', 'delivered']))
                            <button type="button" class="btn btn-outline btn-sm" style="color:#dc2626;border-color:#fecaca;font-size:.65rem;"
                                onclick="openCancelModal('{{ route('logistics.shipments.cancel', $sh) }}', '{{ $sh->shipment_number }}')" title="Cancel Shipment">
                                <i class="fas fa-times-circle"></i>
                            </button>
                            @endif

                            @if($sh->status === 'cancelled')
                            <div style="padding:.6rem 1rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin-bottom:1rem;">
                                <div style="font-size:.82rem;font-weight:700;color:#dc2626;">
                                    <i class="fas fa-times-circle" style="margin-right:.3rem;"></i> Shipment Cancelled
                                </div>
                                <div style="font-size:.75rem;color:#991b1b;margin-top:.2rem;">
                                    Reason: {{ $sh->cancel_reason ?? '—' }}
                                </div>
                                <div style="font-size:.62rem;color:#94a3b8;margin-top:.15rem;">
                                    by {{ $sh->cancelledByUser->name ?? '—' }} on {{ $sh->cancelled_at?->format('d M Y H:i') ?? '—' }}
                                </div>
                            </div>
                            @endif
                        </div>
                    </td>

                </tr>

                {{-- Inline Lock / Sailing Date Panel --}}
                @if($sh->status === 'consolidated')
                <tr id="lockPanel{{ $sh->id }}" style="display:none;background:#eff6ff;">
                    <td colspan="10" style="padding:1rem;">
                        <form method="POST" action="{{ route('logistics.shipments.lock', $sh) }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;" onsubmit="return confirm('Lock shipment {{ $sh->shipment_code }}?\n\nThis will:\n• Set the sailing date\n• Lock the shipment\n• Auto-generate ASN\n• Notify HOD for platform pricing')">
                            @csrf
                            <div><label style="font-size:.68rem;font-weight:600;color:#1e40af;">Sailing Date *</label><input type="date" name="sailing_date" required style="padding:.35rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.68rem;font-weight:600;color:#1e40af;">ETA Date</label><input type="date" name="eta_date" style="padding:.35rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.68rem;font-weight:600;color:#1e40af;">Shipping Line</label><input type="text" name="shipping_line" placeholder="e.g. Maersk" style="width:120px;padding:.35rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.68rem;font-weight:600;color:#1e40af;">Vessel Name</label><input type="text" name="vessel_name" placeholder="Vessel..." style="width:120px;padding:.35rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.68rem;font-weight:600;color:#1e40af;">Bill of Lading</label><input type="text" name="bill_of_lading" placeholder="B/L..." style="width:120px;padding:.35rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-lock" style="margin-right:.2rem;"></i> Lock & Generate ASN</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('lockPanel{{ $sh->id }}').style.display='none'">Cancel</button>
                        </form>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-ship" style="font-size:2.5rem;display:block;margin-bottom:.5rem;"></i>No shipments found.<br><a href="{{ route('logistics.container-planning') }}" style="color:#1e3a5f;">Create your first shipment →</a></td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($shipments->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $shipments->links('pagination::tailwind') }}</div>@endif
</div>
<style>
.mono-link {
    font-weight: 700;
    font-family: monospace;
    font-size: .85rem;
    color: #1e3a5f;
    text-decoration: none;
}

.mono-link:hover {
    text-decoration: underline;
    color:#e8a838;
}

</style>

{{-- Cancel Modal --}}
{{-- Cancel Shipment Modal --}}
<div id="cancelModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;width:460px;max-width:92%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <div style="padding:.75rem 1.25rem;border-bottom:1px solid #fecaca;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:.9rem;font-weight:700;color:#dc2626;margin:0;">
                <i class="fas fa-times-circle" style="margin-right:.3rem;"></i> Cancel Shipment — <span id="cancelShipNum"></span>
            </h3>
            <button onclick="closeCancelModal()" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:#94a3b8;">&times;</button>
        </div>
        <form method="POST" id="cancelForm" action="">
            @csrf
            <div style="padding:1rem 1.25rem;">
                <div style="padding:.5rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;font-size:.78rem;color:#991b1b;margin-bottom:.75rem;">
                    <i class="fas fa-exclamation-triangle" style="margin-right:.3rem;"></i>
                    This will cancel the shipment and move all consignments back to <strong>Container Planning</strong>.
                </div>
                <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem;">Reason <span style="color:#dc2626;">*</span></label>
                <textarea name="cancel_reason" id="cancelReason" required rows="3"
                    placeholder="e.g. Container booking cancelled, vendor unable to fulfil..."
                    style="width:100%;padding:.5rem .6rem;border:1.5px solid #d1d5db;border-radius:8px;font-size:.85rem;font-family:inherit;resize:vertical;"></textarea>
            </div>
            <div style="padding:.6rem 1.25rem;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:.4rem;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeCancelModal()">Back</button>
                <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#fff;border:none;padding:.4rem .8rem;border-radius:6px;font-weight:700;cursor:pointer;"
                    onclick="return confirm('Cancel this shipment?')">
                    <i class="fas fa-times-circle" style="margin-right:.2rem;"></i> Confirm Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCancelModal(actionUrl, shipmentNumber) {
    document.getElementById('cancelForm').action = actionUrl;
    document.getElementById('cancelShipNum').textContent = shipmentNumber;
    document.getElementById('cancelReason').value = '';
    document.getElementById('cancelModal').style.display = 'flex';
    document.getElementById('cancelReason').focus();
}

function closeCancelModal() {
    document.getElementById('cancelModal').style.display = 'none';
}

document.getElementById('cancelModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});
</script>
<script>
    let palletTimers = {};
    document.querySelectorAll('.pallet-input').forEach(input => {
        input.addEventListener('input', function() {
            debounceSavePallets(this);
        });
        input.addEventListener('blur', function() {
            savePallets(this);
        });
    });

    function debounceSavePallets(el) {
        const id = el.dataset.shipmentId;
        clearTimeout(palletTimers[id]);
        palletTimers[id] = setTimeout(() => savePallets(el), 600);
    }

    function savePallets(el) {
        const id = el.dataset.shipmentId;
        const val = el.value.trim();
        const statusEl = document.querySelector('.pallet-status-' + id);

        if (val !== '' && (isNaN(val) || parseInt(val) < 0)) {
            if (statusEl) statusEl.innerHTML = '<span style="color:#dc2626;">Invalid</span>';
            return;
        }

        if (statusEl) statusEl.innerHTML = '<span style="color:#e8a838;"><i class="fas fa-spinner fa-spin"></i></span>';

        fetch('/logistics/shipments/' + id + '/update-pallets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    no_of_pallets: val === '' ? null : parseInt(val)
                })
            })
            .then(r => r.json())
            .then(data => {
                if (statusEl) {
                    if (data.success) {
                        statusEl.innerHTML = '<span style="color:#16a34a;">✓ Saved</span>';
                    } else {
                        statusEl.innerHTML = '<span style="color:#dc2626;">✗ Error</span>';
                    }
                    setTimeout(() => {
                        statusEl.innerHTML = '';
                    }, 3000);
                }
            })
            .catch(() => {
                if (statusEl) {
                    statusEl.innerHTML = '<span style="color:#dc2626;">✗ Failed</span>';
                    setTimeout(() => {
                        statusEl.innerHTML = '';
                    }, 3000);
                }
            });
    }

    let manpowerTimers = {};
    document.querySelectorAll('.manpower-input').forEach(input => {
        input.addEventListener('input', function() {
            debounceSaveManpower(this);
        });
        input.addEventListener('blur', function() {
            saveManpower(this);
        });
    });

    function debounceSaveManpower(el) {
        const id = el.dataset.shipmentId;
        clearTimeout(manpowerTimers[id]);
        manpowerTimers[id] = setTimeout(() => saveManpower(el), 600);
    }

    function saveManpower(el) {
        console.log('saveManpower')
        const id = el.dataset.shipmentId;
        const val = el.value.trim();
        const statusEl = document.querySelector('.manpower-status-' + id);

        if (val !== '' && (isNaN(val) || parseFloat(val) < 0)) {
            if (statusEl) statusEl.innerHTML = '<span style="color:#dc2626;">Invalid</span>';
            return;
        }

        if (statusEl) statusEl.innerHTML = '<span style="color:#e8a838;"><i class="fas fa-spinner fa-spin"></i></span>';

        fetch('/logistics/shipments/' + id + '/update-pallets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    manpower_no_of_hours: val === '' ? null : parseFloat(val)
                })
            })
            .then(r => r.json())
            .then(data => {
                if (statusEl) {
                    if (data.success) {
                        statusEl.innerHTML = '<span style="color:#16a34a;">✓ Saved</span>';
                    } else {
                        statusEl.innerHTML = '<span style="color:#dc2626;">✗ Error</span>';
                    }
                    setTimeout(() => {
                        statusEl.innerHTML = '';
                    }, 3000);
                }
            })
            .catch(() => {
                if (statusEl) {
                    statusEl.innerHTML = '<span style="color:#dc2626;">✗ Failed</span>';
                    setTimeout(() => {
                        statusEl.innerHTML = '';
                    }, 3000);
                }
            });
    }

    document.querySelectorAll('.shipment-status-select').forEach(select => {
        select.addEventListener('change', function() {
            var id = this.dataset.id;
            var status = this.value;
            var msg = document.querySelector('.shipment-status-msg-' + id);
            var confirmMsg = status === 'reopened' ?
                'Reopen this shipment? Its consignments will be released back to Container Planning.' :
                'Change shipment status to ' + status + '?';

            if (!confirm(confirmMsg)) {
                location.reload();
                return;
            }

            if (msg) msg.innerHTML = '<span style="color:#e8a838;"><i class="fas fa-spinner fa-spin"></i></span>';

            fetch('/logistics/shipments/' + id + '/change-status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        status: status
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (msg) msg.innerHTML = '<span style="color:#16a34a;">✓ ' + data.old_status + ' → ' + data.new_status + '</span>';
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        if (msg) msg.innerHTML = '<span style="color:#dc2626;">✗ Failed</span>';
                    }
                })
                .catch(() => {
                    if (msg) msg.innerHTML = '<span style="color:#dc2626;">✗ Error</span>';
                });
        });
    });
</script>
@endsection