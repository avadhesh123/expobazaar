@extends('layouts.app')
@section('title', 'Pricing Status')
@section('page-title', 'Pricing Status — ' . $asn->asn_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('hod.asn-list') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> ASN List</a>
    @if(in_array($asn->status, ['generated', 'locked', 'pricing_done']))
        <a href="{{ route('hod.pricing.prepare', $asn) }}" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> Edit Pricing</a>
    @endif
</div>

{{-- Summary --}}
<div class="grid-kpi" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));">
    <div class="kpi-card"><div class="kpi-label">Total Price Items</div><div class="kpi-value">{{ $summary['total_items'] }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Total Cost</div><div class="kpi-value" style="font-size:1.3rem;">${{ number_format($summary['total_cost'],0) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Total Selling</div><div class="kpi-value" style="font-size:1.3rem;color:#166534;">${{ number_format($summary['total_selling'],0) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Avg Margin</div><div class="kpi-value" style="font-size:1.3rem;color:{{ ($summary['avg_margin'] ?? 0) >= 25 ? '#16a34a' : '#e8a838' }};">{{ number_format($summary['avg_margin'],1) }}%</div></div>
</div>

{{-- Pipeline Status --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;">
            @foreach([
                ['Submitted','submitted','#fef3c7','#92400e',$summary['submitted']],
                ['Finance Approved','finance_approved','#dbeafe','#1e40af',$summary['finance_approved']],
                ['Finalized','approved','#dcfce7','#166534',$summary['approved']],
                ['Rejected','rejected','#fee2e2','#dc2626',$summary['rejected']],
            ] as [$label,$key,$bg,$color,$count])
            <div style="display:flex;align-items:center;gap:.4rem;">
                <div style="width:36px;height:36px;border-radius:50%;background:{{ $bg }};display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;color:{{ $color }};">{{ $count }}</div>
                <span style="font-size:.78rem;font-weight:600;color:{{ $color }};">{{ $label }}</span>
            </div>
            @if(!$loop->last)<i class="fas fa-arrow-right" style="color:#d1d5db;font-size:.6rem;"></i>@endif
            @endforeach

            {{-- Finalize button --}}
            @if($summary['finance_approved'] > 0 && $summary['submitted'] === 0 && $summary['rejected'] === 0)
                <form method="POST" action="{{ route('hod.pricing.finalize', $asn) }}" style="margin-left:auto;" onsubmit="return confirm('Finalize all pricing for {{ $asn->asn_number }}?\n\nThis sends approved pricing to the Cataloguing Team.')">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="fas fa-check-double" style="margin-right:.3rem;"></i> Finalize & Send to Cataloguing</button>
                </form>
            @endif
        </div>
    </div>
</div>

{{-- Pricing by Channel --}}
@foreach($byChannel as $channelId => $channelPricings)
@php $channel = $channelPricings->first()->salesChannel; @endphp
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3>
            @php $icons = ['Amazon'=>'fab fa-amazon','Wayfair'=>'fas fa-couch','Shopify'=>'fab fa-shopify','Faire'=>'fas fa-store']; @endphp
            <i class="{{ $icons[$channel->name ?? ''] ?? 'fas fa-store' }}" style="margin-right:.5rem;color:#e8a838;"></i>
            {{ $channel->name ?? 'Channel #'.$channelId }}
        </h3>
        @php
            $chApproved = $channelPricings->whereIn('status', ['finance_approved','approved'])->count();
            $chTotal = $channelPricings->count();
        @endphp
        <span style="font-size:.78rem;color:#64748b;">{{ $chApproved }}/{{ $chTotal }} approved</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr><th>Product</th><th>SKU</th><th>Cost</th><th>Platform</th><th>Selling</th><th>MAP</th><th>Margin</th><th>Prepared By</th><th>Finance Review</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach($channelPricings as $p)
                <tr style="{{ $p->status==='rejected'?'background:#fef2f2;':($p->status==='approved'?'background:#f0fdf4;':'') }}">
                    <td style="font-weight:600;font-size:.82rem;">{{ $p->product->name ?? '—' }}</td>
                    <td style="font-family:monospace;font-size:.8rem;">{{ $p->product->sku ?? '—' }}</td>
                    <td style="font-family:monospace;">${{ number_format($p->cost_price,2) }}</td>
                    <td style="font-family:monospace;">${{ number_format($p->platform_price,2) }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">${{ number_format($p->selling_price,2) }}</td>
                    <td style="font-family:monospace;color:#64748b;">{{ $p->map_price ? '$'.number_format($p->map_price,2) : '—' }}</td>
                    <td>
                        <span style="font-weight:700;color:{{ $p->margin_percent >= 30 ? '#16a34a' : ($p->margin_percent >= 15 ? '#e8a838' : '#dc2626') }};">{{ number_format($p->margin_percent,1) }}%</span>
                        <div style="margin-top:.15rem;background:#e2e8f0;border-radius:3px;height:4px;width:60px;">
                            <div style="height:4px;border-radius:3px;width:{{ min($p->margin_percent,100) }}%;background:{{ $p->margin_percent >= 30 ? '#16a34a' : ($p->margin_percent >= 15 ? '#e8a838' : '#dc2626') }};"></div>
                        </div>
                    </td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $p->preparer->name ?? '—' }}</td>
                    <td>
                        @if($p->financeReviewer)
                            <div style="font-size:.78rem;">{{ $p->financeReviewer->name }}</div>
                            @if($p->remarks)<div style="font-size:.65rem;color:#64748b;font-style:italic;">{{ Str::limit($p->remarks, 30) }}</div>@endif
                        @else
                            <span style="font-size:.75rem;color:#94a3b8;">Pending</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusConfig = [
                                'submitted'=>['badge-warning','Pending Review'],
                                'finance_approved'=>['badge-info','Finance OK'],
                                'approved'=>['badge-success','Finalized'],
                                'rejected'=>['badge-danger','Rejected'],
                            ];
                            $sc = $statusConfig[$p->status] ?? ['badge-gray', ucfirst($p->status)];
                        @endphp
                        <span class="badge {{ $sc[0] }}">{{ $sc[1] }}</span>
                        @if($p->approved_at)<div style="font-size:.62rem;color:#94a3b8;margin-top:.1rem;">{{ $p->approved_at->format('d M Y') }}</div>@endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach

{{-- Margin Legend --}}
<div style="display:flex;gap:1.5rem;font-size:.75rem;color:#64748b;margin-top:.5rem;">
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#16a34a;margin-right:.3rem;"></span> Margin ≥ 30% (Good)</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#e8a838;margin-right:.3rem;"></span> 15-30% (Moderate)</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#dc2626;margin-right:.3rem;"></span> < 15% (Low)</span>
</div>
@endsection
