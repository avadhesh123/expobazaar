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
            <div style="min-width:110px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label>
                <select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>🇮🇳 2000</option>
                    <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>🇺🇸 2100</option>
                    <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>🇳🇱 2200</option>
                </select>
            </div>
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
    <div class="card-header"><h3><i class="fas fa-ship" style="margin-right:.5rem;color:#1e3a5f;"></i> All Shipments</h3><span style="font-size:.78rem;color:#64748b;">{{ $shipments->total() }} total</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr><th>Shipment Code</th><th>Type</th><th>Company / Country</th><th>Vendors</th><th>CBM</th><th>Utilization</th><th>Sailing Date</th><th>ETA</th><th>Status</th><th style="width:140px;">Actions</th></tr>
            </thead>
            <tbody>
                @forelse($shipments as $sh)
                @php
                    $utilPct = $sh->capacity_cbm > 0 ? round(($sh->total_cbm / $sh->capacity_cbm) * 100) : 0;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('logistics.shipments.show', $sh) }}" style="font-weight:700;font-family:monospace;font-size:.85rem;color:#1e3a5f;text-decoration:none;">{{ $sh->shipment_code }}</a>
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
                    <td>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;min-width:60px;">
                                <div style="height:100%;width:{{ min($utilPct, 100) }}%;border-radius:4px;background:{{ $utilPct > 100 ? '#dc2626' : ($utilPct > 85 ? '#e8a838' : '#16a34a') }};"></div>
                            </div>
                            <span style="font-size:.72rem;font-weight:700;color:{{ $utilPct > 100 ? '#dc2626' : '#64748b' }};">{{ $utilPct }}%</span>
                        </div>
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
                <tr><td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-ship" style="font-size:2.5rem;display:block;margin-bottom:.5rem;"></i>No shipments found.<br><a href="{{ route('logistics.container-planning') }}" style="color:#1e3a5f;">Create your first shipment →</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($shipments->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $shipments->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
