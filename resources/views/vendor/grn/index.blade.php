@extends('layouts.app')
@section('title', 'Goods Receipt Notes')
@section('page-title', 'My GRN — Goods Receipt Notes')

@section('content')
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card"><div class="kpi-label">Total GRNs</div><div class="kpi-value">{{ $stats['total_grns'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;"><div class="kpi-label">Items Received</div><div class="kpi-value" style="color:#16a34a;">{{ number_format($stats['total_received']) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Damaged</div><div class="kpi-value" style="color:#dc2626;">{{ number_format($stats['total_damaged']) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Missing</div><div class="kpi-value" style="color:#e8a838;">{{ number_format($stats['total_missing']) }}</div></div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-clipboard-check" style="margin-right:.5rem;color:#1e3a5f;"></i> Goods Receipt Notes</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>GRN #</th><th>Shipment</th><th>Warehouse</th><th>Receipt Date</th><th>Expected</th><th>Received</th><th>Damaged</th><th>Missing</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
                @forelse($grns as $g)
                @php
                    $receivePct = $g->total_items_expected > 0 ? round(($g->total_items_received / $g->total_items_expected) * 100) : 0;
                    $hasIssues = $g->damaged_items > 0 || $g->missing_items > 0;
                @endphp
                <tr style="{{ $hasIssues ? 'background:#fffbeb;' : '' }}">
                    <td style="font-family:monospace;font-weight:700;">{{ $g->grn_number }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $g->shipment->shipment_number ?? '—' }}</td>
                    <td style="font-size:.78rem;">{{ $g->warehouse->name ?? '—' }}<div style="font-size:.62rem;color:#94a3b8;">{{ $g->warehouse->location ?? '' }}</div></td>
                    <td style="font-family:monospace;font-size:.82rem;">{{ $g->receipt_date ? $g->receipt_date->format('d M Y') : '—' }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $g->total_items_expected }}</td>
                    <td style="text-align:center;font-weight:700;color:#16a34a;">{{ $g->total_items_received }}
                        <div style="font-size:.6rem;color:#94a3b8;">{{ $receivePct }}%</div>
                    </td>
                    <td style="text-align:center;font-weight:600;color:{{ $g->damaged_items > 0 ? '#dc2626' : '#94a3b8' }};">{{ $g->damaged_items }}</td>
                    <td style="text-align:center;font-weight:600;color:{{ $g->missing_items > 0 ? '#e8a838' : '#94a3b8' }};">{{ $g->missing_items }}</td>
                    <td>
                        @php $sc = ['pending'=>'badge-warning','verified'=>'badge-success','completed'=>'badge-success','partial'=>'badge-info','disputed'=>'badge-danger']; @endphp
                        <span class="badge {{ $sc[$g->status] ?? 'badge-gray' }}">{{ ucfirst($g->status) }}</span>
                    </td>
                    <td style="font-size:.72rem;color:#64748b;max-width:150px;">{{ Str::limit($g->remarks, 50) }}</td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-clipboard-check" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No GRN records found. GRNs appear once your shipments are received at the warehouse.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($grns->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $grns->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
