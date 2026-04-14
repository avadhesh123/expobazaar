@extends('layouts.app')
@section('title', 'HOD Dashboard')
@section('page-title', 'Management Dashboard')

@section('content')
{{-- KPIs --}}
<div class="grid-kpi">
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">ASN Pending Pricing</div><div class="kpi-value" style="color:#dc2626;">{{ $data['kpis']['asn_pending'] ?? 0 }}</div></div>
            <div class="kpi-icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-file-alt"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Pricing Under Finance Review</div><div class="kpi-value" style="color:#e8a838;">{{ $data['kpis']['pricing_under_review'] ?? 0 }}</div></div>
            <div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-clock"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Pricing Approved</div><div class="kpi-value" style="color:#166534;">{{ $data['kpis']['pricing_approved'] ?? 0 }}</div></div>
            <div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">SKUs Priced (Month)</div><div class="kpi-value" style="color:#1e40af;">{{ $data['kpis']['skus_priced'] ?? 0 }}</div></div>
            <div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-tags"></i></div>
        </div>
    </div>
</div>

{{-- Process Flow --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
            @foreach([
                ['ASN Generated', 'fas fa-file-alt', '#dbeafe', '#1e40af'],
                ['HOD Prepares Pricing', 'fas fa-tags', '#fef3c7', '#92400e'],
                ['Finance Reviews', 'fas fa-file-invoice-dollar', '#ede9fe', '#6d28d9'],
                ['HOD Finalizes', 'fas fa-check-double', '#dcfce7', '#166534'],
                ['Sent to Cataloguing', 'fas fa-list', '#fce4ec', '#c62828'],
            ] as [$label, $icon, $bg, $color])
                <div style="display:flex;align-items:center;gap:.4rem;padding:.4rem .7rem;background:{{ $bg }};border-radius:8px;">
                    <i class="{{ $icon }}" style="color:{{ $color }};font-size:.75rem;"></i>
                    <span style="font-size:.72rem;font-weight:600;color:{{ $color }};">{{ $label }}</span>
                </div>
                @if(!$loop->last)<i class="fas fa-arrow-right" style="color:#d1d5db;font-size:.6rem;"></i>@endif
            @endforeach
        </div>
    </div>
</div>

<div class="grid-2">
    {{-- ASNs Awaiting Pricing --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-exclamation-circle" style="margin-right:.5rem;color:#dc2626;"></i> ASNs Awaiting Pricing</h3><a href="{{ route('hod.asn-list') }}" class="btn btn-outline btn-sm">View All</a></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>ASN #</th><th>Shipment</th><th>Items</th><th>Date</th><th>Action</th></tr></thead>
                <tbody>
                    @forelse(($data['pending_asns'] ?? collect())->take(5) as $asn)
                    <tr>
                        <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $asn->asn_number }}</td>
                        <td style="font-size:.8rem;">{{ $asn->shipment->shipment_code ?? '—' }}</td>
                        <td style="text-align:center;">{{ $asn->total_items }}</td>
                        <td style="font-size:.78rem;color:#64748b;">{{ $asn->generated_at?->format('d M') }}</td>
                        <td><a href="{{ route('hod.pricing.prepare', $asn) }}" class="btn btn-primary btn-sm"><i class="fas fa-tags"></i> Price</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:1.5rem;"><i class="fas fa-check-circle" style="color:#16a34a;"></i> All caught up!</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pricing Pipeline --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-stream" style="margin-right:.5rem;color:#7c3aed;"></i> Pricing Pipeline</h3></div>
        <div class="card-body">
            @php
                $pipeline = [
                    ['label'=>'Pending Pricing','count'=>$data['kpis']['asn_pending'] ?? 0,'color'=>'#dc2626','bg'=>'#fee2e2'],
                    ['label'=>'Submitted to Finance','count'=>$data['kpis']['pricing_under_review'] ?? 0,'color'=>'#e8a838','bg'=>'#fef3c7'],
                    ['label'=>'Finance Approved','count'=>$data['kpis']['pricing_approved'] ?? 0,'color'=>'#16a34a','bg'=>'#dcfce7'],
                    ['label'=>'Finalized / Cataloguing','count'=>$data['kpis']['pricing_finalized'] ?? 0,'color'=>'#1e40af','bg'=>'#dbeafe'],
                ];
                $maxCount = max(collect($pipeline)->pluck('count')->max(), 1);
            @endphp
            @foreach($pipeline as $step)
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem;">
                <span style="width:140px;font-size:.78rem;font-weight:600;color:#334155;">{{ $step['label'] }}</span>
                <div style="flex:1;height:24px;background:#f1f5f9;border-radius:6px;overflow:hidden;">
                    <div style="height:100%;width:{{ ($step['count']/$maxCount)*100 }}%;background:{{ $step['color'] }};border-radius:6px;min-width:{{ $step['count']>0?'4px':'0' }};display:flex;align-items:center;justify-content:flex-end;padding-right:.5rem;">
                        @if($step['count'] > 0)<span style="font-size:.68rem;font-weight:700;color:#fff;">{{ $step['count'] }}</span>@endif
                    </div>
                </div>
                <span style="font-weight:800;font-size:.9rem;color:{{ $step['color'] }};min-width:30px;text-align:right;">{{ $step['count'] }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-bolt" style="margin-right:.5rem;color:#e8a838;"></i> Quick Actions</h3></div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <a href="{{ route('hod.asn-list') }}" class="btn btn-outline"><i class="fas fa-file-alt"></i> ASN & Pricing List</a>
        <a href="{{ route('hod.asn-list', ['status' => 'generated']) }}" class="btn btn-primary"><i class="fas fa-tags"></i> Start Pricing</a>
    </div>
</div>
@endsection
