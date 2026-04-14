@extends('layouts.app')
@section('title', 'ASN & Pricing')
@section('page-title', 'ASN & Platform Pricing')

@section('content')
<!-- <div style="display:flex;gap:1rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;cursor:pointer;" onclick="window.location='{{ route('hod.asn-list') }}'"><div class="kpi-label">Total ASNs</div><div class="kpi-value">{{ $stats['total'] }}</div></div>
    <div class="kpi-card" style="flex:1;cursor:pointer;border-left:3px solid #dc2626;" onclick="window.location='{{ route('hod.asn-list',['status'=>'generated']) }}'"><div class="kpi-label">Needs Pricing</div><div class="kpi-value" style="color:#dc2626;">{{ $stats['needs_pricing'] }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #e8a838;"><div class="kpi-label">Pricing Done</div><div class="kpi-value" style="color:#e8a838;">{{ $stats['pricing_done'] }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #16a34a;"><div class="kpi-label">Finalized</div><div class="kpi-value" style="color:#16a34a;">{{ $stats['finalized'] }}</div></div>
</div> -->

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('hod.asn-list') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:160px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label><select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach(['generated','locked','pricing_done','finalized'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>🇮🇳 2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>🇺🇸 2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>🇳🇱 2200</option></select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('hod.asn-list') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-alt" style="margin-right:.5rem;color:#e8a838;"></i> All ASNs</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>ASN Number</th><th>Shipment</th><th>Company</th><th>Country</th><th>Items</th><th>CBM</th><th>Generated</th><th>Pricing Progress</th><th>Status</th><th style="width:180px;">Actions</th></tr></thead>
            <tbody>
                @forelse($asns as $asn)
                @php
                    $tp = $asn->platformPricing->count();
                    $ap = $asn->platformPricing->where('status','approved')->count();
                    $fa = $asn->platformPricing->where('status','finance_approved')->count();
                    $rj = $asn->platformPricing->where('status','rejected')->count();
                    $sb = $asn->platformPricing->where('status','submitted')->count();
                    $flags = ['US'=>'🇺🇸','NL'=>'🇳🇱','IN'=>'🇮🇳'];
                    $ccBg = ['2000'=>'#dcfce7','2100'=>'#dbeafe','2200'=>'#fef3c7'];
                @endphp
                <tr>
                    <td style="font-weight:700;font-family:monospace;font-size:.85rem;">{{ $asn->asn_number }}</td>
                    <td><div style="font-size:.82rem;font-weight:500;">{{ $asn->shipment->shipment_code ?? '—' }}</div><div style="font-size:.68rem;color:#94a3b8;">{{ $asn->shipment->shipment_type ?? '' }}</div></td>
                    <td><span style="padding:.15rem .4rem;background:{{ $ccBg[$asn->company_code] ?? '#f1f5f9' }};border-radius:5px;font-size:.78rem;font-weight:600;">{{ $asn->company_code }}</span></td>
                    <td>{{ $flags[$asn->shipment->destination_country ?? ''] ?? '' }} {{ $asn->shipment->destination_country ?? '' }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $asn->total_items }}</td>
                    <td style="font-family:monospace;">{{ number_format($asn->total_cbm, 2) }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $asn->generated_at?->format('d M Y') ?? '—' }}</td>
                    <td>
                        @if($tp > 0)
                            @php $pct = round((($fa + $ap) / $tp) * 100); @endphp
                            <div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.2rem;"><div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;"><div style="height:100%;width:{{ $pct }}%;border-radius:4px;background:{{ $rj > 0 ? '#dc2626' : '#16a34a' }};"></div></div><span style="font-size:.68rem;font-weight:600;color:#64748b;">{{ $pct }}%</span></div>
                            <div style="display:flex;gap:.15rem;flex-wrap:wrap;">
                                @if($sb > 0)<span style="font-size:.55rem;padding:.08rem .25rem;background:#fef3c7;border-radius:3px;color:#92400e;">{{ $sb }} review</span>@endif
                                @if($fa > 0)<span style="font-size:.55rem;padding:.08rem .25rem;background:#dbeafe;border-radius:3px;color:#1e40af;">{{ $fa }} fin.ok</span>@endif
                                @if($ap > 0)<span style="font-size:.55rem;padding:.08rem .25rem;background:#dcfce7;border-radius:3px;color:#166534;">{{ $ap }} final</span>@endif
                                @if($rj > 0)<span style="font-size:.55rem;padding:.08rem .25rem;background:#fee2e2;border-radius:3px;color:#dc2626;">{{ $rj }} rejected</span>@endif
                            </div>
                        @else
                            <span style="font-size:.75rem;color:#94a3b8;">No pricing</span>
                        @endif
                    </td>
                    <td><span class="badge {{ ['generated'=>'badge-warning','locked'=>'badge-warning','pricing_done'=>'badge-info','finalized'=>'badge-success'][$asn->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$asn->status)) }}</span></td>
                    <td>
                        <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
                            @if(in_array($asn->status, ['generated','locked']))
                                <a href="{{ route('hod.pricing.prepare', $asn) }}" class="btn btn-primary btn-sm"><i class="fas fa-tags"></i> Price</a>
                            @endif
                            @if($asn->status === 'pricing_done')
                                @if($fa > 0 && $sb === 0 && $rj === 0)
                                    <form method="POST" action="{{ route('hod.pricing.finalize', $asn) }}" style="display:inline;" onsubmit="return confirm('Finalize pricing and send to Cataloguing?')">@csrf<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check-double"></i> Finalize</button></form>
                                @endif
                            @endif
                            @if($rj > 0)
                                <a href="{{ route('hod.pricing.prepare', $asn) }}" class="btn btn-danger btn-sm"><i class="fas fa-redo"></i> Revise</a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-file-alt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No ASNs found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($asns->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $asns->links('pagination::tailwind') }}</div>@endif
</div>
@endsection