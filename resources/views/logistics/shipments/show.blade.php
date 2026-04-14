@extends('layouts.app')
@section('title', 'Shipment: ' . $shipment->shipment_code)
@section('page-title', 'Shipment Details')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('logistics.shipments') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Shipments</a>
    @if($shipment->asn)
        <a href="{{ route('logistics.asn.download', $shipment->asn) }}" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Download ASN</a>
    @endif
    @if(in_array($shipment->status, ['arrived','asn_generated','in_transit']) && !$shipment->grn)
        <a href="{{ route('logistics.grn.upload', $shipment) }}" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload GRN</a>
    @endif
</div>

{{-- Shipment Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1.25rem 1.4rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <div>
                <div style="font-size:1.3rem;font-weight:800;color:#0d1b2a;font-family:monospace;">{{ $shipment->shipment_code }}</div>
                @if($shipment->container_number)<div style="font-size:.82rem;color:#64748b;">Container: <strong>{{ $shipment->container_number }}</strong></div>@endif
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;">
                @php
                    $typeBg = ['FCL'=>['#dbeafe','#1e40af','🚢'],'LCL'=>['#fef3c7','#92400e','📦'],'AIR'=>['#ede9fe','#6d28d9','✈️']];
                    $sc = ['planning'=>'badge-gray','consolidated'=>'badge-info','locked'=>'badge-info','asn_generated'=>'badge-warning','in_transit'=>'badge-warning','arrived'=>'badge-success','grn_pending'=>'badge-warning','grn_completed'=>'badge-success','cancelled'=>'badge-danger'];
                @endphp
                <span style="padding:.3rem .7rem;background:{{ $typeBg[$shipment->shipment_type][0] ?? '#f1f5f9' }};border-radius:8px;font-weight:700;color:{{ $typeBg[$shipment->shipment_type][1] ?? '#475569' }};">{{ $typeBg[$shipment->shipment_type][2] ?? '' }} {{ $shipment->shipment_type }}</span>
                <span class="badge {{ $sc[$shipment->status] ?? 'badge-gray' }}" style="font-size:.82rem;padding:.3rem .8rem;">{{ ucfirst(str_replace('_',' ',$shipment->status)) }}</span>
            </div>
        </div>

        {{-- Info Grid --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:.75rem;">
            @php $flags = ['US'=>'🇺🇸','NL'=>'🇳🇱','IN'=>'🇮🇳']; $ccBg = ['2000'=>'#dcfce7','2100'=>'#dbeafe','2200'=>'#fef3c7']; @endphp
            <div style="padding:.65rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Company</div><div style="font-weight:700;"><span style="padding:.1rem .3rem;background:{{ $ccBg[$shipment->company_code] ?? '#f1f5f9' }};border-radius:4px;font-size:.82rem;">{{ $shipment->company_code }}</span></div></div>
            <div style="padding:.65rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Destination</div><div style="font-weight:700;">{{ $flags[$shipment->destination_country] ?? '' }} {{ $shipment->destination_country }}</div></div>
            <div style="padding:.65rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Total CBM</div><div style="font-weight:800;font-family:monospace;font-size:1rem;">{{ number_format($shipment->total_cbm, 2) }}</div></div>
            <div style="padding:.65rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Capacity</div>
                @php $utilPct = $shipment->capacity_cbm > 0 ? round(($shipment->total_cbm / $shipment->capacity_cbm) * 100) : 0; @endphp
                <div style="font-weight:700;color:{{ $utilPct > 100 ? '#dc2626' : '#166534' }};">{{ $utilPct }}% used</div>
                <div style="height:6px;background:#e2e8f0;border-radius:3px;margin-top:.2rem;"><div style="height:100%;width:{{ min($utilPct,100) }}%;border-radius:3px;background:{{ $utilPct > 100 ? '#dc2626' : ($utilPct > 85 ? '#e8a838' : '#16a34a') }};"></div></div>
            </div>
            <div style="padding:.65rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Items</div><div style="font-weight:700;">{{ $shipment->total_items }}</div></div>
            <div style="padding:.65rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Value</div><div style="font-weight:700;">${{ number_format($shipment->total_value, 2) }}</div></div>
            <div style="padding:.65rem;background:{{ $shipment->sailing_date ? '#dcfce7' : '#fef3c7' }};border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Sailing Date</div><div style="font-weight:700;">{{ $shipment->sailing_date?->format('d M Y') ?? 'Not set' }}</div></div>
            <div style="padding:.65rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">ETA</div><div style="font-weight:600;">{{ $shipment->eta_date?->format('d M Y') ?? '—' }}</div></div>
        </div>

        {{-- Shipping Details --}}
        @if($shipment->shipping_line || $shipment->vessel_name || $shipment->bill_of_lading || $shipment->port_of_loading)
        <div style="margin-top:1rem;padding:.75rem;background:#eff6ff;border-radius:8px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.5rem;">
            @if($shipment->shipping_line)<div><span style="font-size:.65rem;color:#1e40af;font-weight:600;">Shipping Line:</span><br><span style="font-weight:600;">{{ $shipment->shipping_line }}</span></div>@endif
            @if($shipment->vessel_name)<div><span style="font-size:.65rem;color:#1e40af;font-weight:600;">Vessel:</span><br><span style="font-weight:600;">{{ $shipment->vessel_name }}</span></div>@endif
            @if($shipment->voyage_number)<div><span style="font-size:.65rem;color:#1e40af;font-weight:600;">Voyage:</span><br><span style="font-weight:600;">{{ $shipment->voyage_number }}</span></div>@endif
            @if($shipment->bill_of_lading)<div><span style="font-size:.65rem;color:#1e40af;font-weight:600;">Bill of Lading:</span><br><span style="font-weight:600;">{{ $shipment->bill_of_lading }}</span></div>@endif
            @if($shipment->port_of_loading)<div><span style="font-size:.65rem;color:#1e40af;font-weight:600;">Port of Loading:</span><br><span style="font-weight:600;">{{ $shipment->port_of_loading }}</span></div>@endif
            @if($shipment->port_of_discharge)<div><span style="font-size:.65rem;color:#1e40af;font-weight:600;">Port of Discharge:</span><br><span style="font-weight:600;">{{ $shipment->port_of_discharge }}</span></div>@endif
        </div>
        @endif

        {{-- Lock info --}}
        @if($shipment->locked_at)
        <div style="margin-top:.75rem;padding:.5rem .75rem;background:#f0fdf4;border-radius:6px;font-size:.78rem;color:#166534;display:flex;align-items:center;gap:.3rem;">
            <i class="fas fa-lock"></i> Locked on {{ $shipment->locked_at->format('d M Y H:i') }} by {{ $shipment->lockedBy->name ?? 'system' }}
        </div>
        @endif

        {{-- Warehouse --}}
        @if($shipment->warehouse)
        <div style="margin-top:.5rem;padding:.5rem .75rem;background:#f8fafc;border-radius:6px;font-size:.78rem;color:#475569;">
            <i class="fas fa-warehouse" style="margin-right:.3rem;"></i> Destination Warehouse: <strong>{{ $shipment->warehouse->name }}</strong> ({{ $shipment->warehouse->code }})
        </div>
        @endif
    </div>
</div>

<div class="grid-2">
    {{-- Consignments in this Shipment --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-box" style="margin-right:.5rem;color:#2d6a4f;"></i> Consignments ({{ $shipment->consignments->count() }})</h3></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Consignment #</th><th>Vendor</th><th>Items</th><th>CBM</th></tr></thead>
                <tbody>
                    @foreach($shipment->consignments as $con)
                    <tr>
                        <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $con->consignment_number }}</td>
                        <td>
                            <div style="font-size:.82rem;">{{ $con->vendor->company_name ?? '—' }}</div>
                            <div style="font-size:.68rem;color:#94a3b8;">{{ $con->vendor->vendor_code ?? '' }}</div>
                        </td>
                        <td style="text-align:center;font-weight:600;">{{ $con->total_items }}</td>
                        <td style="font-family:monospace;font-weight:600;">{{ number_format($con->pivot->cbm ?? $con->total_cbm, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ASN --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-file-alt" style="margin-right:.5rem;color:#e8a838;"></i> ASN (Advance Shipping Notice)</h3></div>
        <div class="card-body">
            @if($shipment->asn)
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
                    <div style="padding:.6rem;background:#fef3c7;border-radius:8px;"><div style="font-size:.65rem;color:#92400e;font-weight:600;">ASN Number</div><div style="font-weight:800;font-family:monospace;">{{ $shipment->asn->asn_number }}</div></div>
                    <div style="padding:.6rem;background:#fef3c7;border-radius:8px;"><div style="font-size:.65rem;color:#92400e;font-weight:600;">Status</div><div><span class="badge {{ $shipment->asn->status==='pricing_done'?'badge-success':'badge-warning' }}">{{ ucfirst(str_replace('_',' ',$shipment->asn->status)) }}</span></div></div>
                    <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.65rem;color:#64748b;font-weight:600;">Total Items</div><div style="font-weight:700;">{{ $shipment->asn->total_items }}</div></div>
                    <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.65rem;color:#64748b;font-weight:600;">Generated</div><div style="font-size:.82rem;">{{ $shipment->asn->generated_at?->format('d M Y H:i') ?? '—' }}</div></div>
                </div>
                <a href="{{ route('logistics.asn.download', $shipment->asn) }}" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Download ASN for Warehouse</a>
                @if($shipment->asn->status === 'generated')
                    <div style="margin-top:.5rem;padding:.4rem .65rem;background:#eff6ff;border-radius:6px;font-size:.75rem;color:#1e40af;">
                        <i class="fas fa-info-circle"></i> ASN has been sent to HOD for platform pricing.
                    </div>
                @endif
            @else
                <div style="text-align:center;padding:2rem;color:#94a3b8;">
                    <i class="fas fa-file-alt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                    <div style="font-size:.85rem;">ASN not yet generated.</div>
                    <div style="font-size:.78rem;margin-top:.25rem;">Lock the shipment with a sailing date to auto-generate ASN.</div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Product Items Breakdown --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-list" style="margin-right:.5rem;color:#1e3a5f;"></i> Product Items</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>SKU</th><th>Product</th><th>Vendor</th><th>Consignment</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>CBM</th></tr></thead>
            <tbody>
                @php $grandTotal = 0; $grandCbm = 0; @endphp
                @foreach($shipment->consignments as $con)
                    @if($con->liveSheet)
                        @foreach($con->liveSheet->items as $item)
                        @php $grandTotal += $item->total_price; $grandCbm += $item->total_cbm; @endphp
                        <tr>
                            <td style="font-family:monospace;font-size:.8rem;">{{ $item->product->sku ?? '—' }}</td>
                            <td style="font-size:.82rem;font-weight:500;">{{ $item->product->name ?? '—' }}</td>
                            <td style="font-size:.78rem;color:#64748b;">{{ $con->vendor->company_name ?? '—' }}</td>
                            <td style="font-size:.75rem;font-family:monospace;">{{ $con->consignment_number }}</td>
                            <td style="text-align:center;font-weight:600;">{{ $item->quantity }}</td>
                            <td style="font-family:monospace;">${{ number_format($item->unit_price, 2) }}</td>
                            <td style="font-family:monospace;font-weight:600;">${{ number_format($item->total_price, 2) }}</td>
                            <td style="font-family:monospace;">{{ number_format($item->total_cbm, 2) }}</td>
                        </tr>
                        @endforeach
                    @endif
                @endforeach
                <tr style="background:#f8fafc;font-weight:700;">
                    <td colspan="4" style="text-align:right;">TOTAL</td>
                    <td style="text-align:center;">{{ $shipment->total_items }}</td>
                    <td></td>
                    <td style="font-family:monospace;">${{ number_format($grandTotal, 2) }}</td>
                    <td style="font-family:monospace;">{{ number_format($grandCbm, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- GRN Status --}}
@if($shipment->grn)
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-clipboard-check" style="margin-right:.5rem;color:#16a34a;"></i> GRN Received</h3><a href="{{ route('logistics.grn.show', $shipment->grn) }}" class="btn btn-outline btn-sm">View GRN</a></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.75rem;">
            <div style="padding:.5rem;background:#dcfce7;border-radius:8px;text-align:center;"><div style="font-size:.65rem;color:#166534;font-weight:600;">GRN #</div><div style="font-weight:700;font-family:monospace;">{{ $shipment->grn->grn_number }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;text-align:center;"><div style="font-size:.65rem;color:#64748b;font-weight:600;">Received</div><div style="font-weight:700;">{{ $shipment->grn->total_items_received }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;text-align:center;"><div style="font-size:.65rem;color:#64748b;font-weight:600;">Damaged</div><div style="font-weight:700;color:#dc2626;">{{ $shipment->grn->damaged_items }}</div></div>
            <div style="padding:.5rem;background:#f8fafc;border-radius:8px;text-align:center;"><div style="font-size:.65rem;color:#64748b;font-weight:600;">Receipt Date</div><div style="font-weight:600;font-size:.82rem;">{{ $shipment->grn->receipt_date->format('d M Y') }}</div></div>
        </div>
    </div>
</div>
@endif

{{-- Lock Shipment (if still consolidated) --}}
@if($shipment->status === 'consolidated')
<div class="card" style="margin-top:1.25rem;border-color:#e8a838;">
    <div class="card-header" style="background:#fffbeb;"><h3><i class="fas fa-lock" style="margin-right:.5rem;color:#e8a838;"></i> Lock Shipment & Generate ASN</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.shipments.lock', $shipment) }}" onsubmit="return confirm('Lock this shipment?\n\nThis will set the sailing date, lock the shipment, and auto-generate an ASN for HOD pricing.')">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;">
                <div class="form-group"><label>Sailing Date <span style="color:#dc2626;">*</span></label><input type="date" name="sailing_date" required></div>
                <div class="form-group"><label>ETA Date</label><input type="date" name="eta_date"></div>
                <div class="form-group"><label>Shipping Line</label><input type="text" name="shipping_line" placeholder="e.g. Maersk"></div>
                <div class="form-group"><label>Vessel Name</label><input type="text" name="vessel_name" placeholder="Vessel name"></div>
                <div class="form-group"><label>Voyage Number</label><input type="text" name="voyage_number" placeholder="Voyage #"></div>
                <div class="form-group"><label>Bill of Lading</label><input type="text" name="bill_of_lading" placeholder="B/L number"></div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-lock" style="margin-right:.3rem;"></i> Lock Shipment & Generate ASN</button>
        </form>
    </div>
</div>
@endif
@endsection
