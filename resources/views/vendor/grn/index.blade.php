@extends('layouts.app')
@section('title', 'My GRNs')
@section('page-title', 'Goods Receipt Notes')

@section('content')
<div class="grid-kpi" style="grid-template-columns:repeat(6,1fr);">
    <div class="kpi-card" style="border-left:3px solid #1e40af;">
        <div class="kpi-label">Total GRNs</div>
        <div class="kpi-value" style="color:#1e40af;">{{ $stats['total_grns'] }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #64748b;">
        <div class="kpi-label">Expected</div>
        <div class="kpi-value">{{ number_format($stats['total_expected']) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;">
        <div class="kpi-label">Received</div>
        <div class="kpi-value" style="color:#16a34a;">{{ number_format($stats['total_received']) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #dc2626;">
        <div class="kpi-label">Damaged</div>
        <div class="kpi-value" style="color:#dc2626;">{{ number_format($stats['total_damaged']) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;">
        <div class="kpi-label">Missing</div>
        <div class="kpi-value" style="color:#e8a838;">{{ number_format($stats['total_missing']) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #1e40af;">
        <div class="kpi-label">Excess</div>
        <div class="kpi-value" style="color:#1e40af;">{{ number_format($stats['total_excess']) }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-check" style="margin-right:.5rem;color:#1e3a5f;"></i> My GRNs</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>GRN #</th>
                    <th>Shipment</th>
                    <th>Warehouse</th>
                    <th>Receipt Date</th>
                    <th style="text-align:center;">Expected</th>
                    <th style="text-align:center;">Received</th>
                    <th style="text-align:center;">Damaged</th>
                    <th style="text-align:center;">Missing</th>
                    <th style="text-align:center;">Excess</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($grns as $g)
                @php
                $hasIssue = ($g->vendor_damaged > 0 || $g->vendor_missing > 0);
                $hasExcess = ($g->vendor_excess > 0);
                @endphp
                <tr style="{{ $hasIssue ? 'background:#fef2f2;' : ($hasExcess ? 'background:#eff6ff;' : '') }}">
                    <td style="font-family:monospace;font-weight:700;">{{ $g->grn_number }}</td>
                    <td style="font-family:monospace;font-size:.78rem;">{{ $g->shipment->shipment_code ?? '—' }}</td>
                    <td style="font-size:.78rem;">{{ $g->warehouse->name ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $g->receipt_date?->format('d M Y') ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-weight:600;">{{ $g->vendor_expected }}</td>
                    <td style="text-align:center;font-family:monospace;font-weight:700;color:#16a34a;">{{ $g->vendor_received }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $g->vendor_damaged > 0 ? '#dc2626' : '#94a3b8' }};font-weight:{{ $g->vendor_damaged > 0 ? '700' : '400' }};">{{ $g->vendor_damaged }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $g->vendor_missing > 0 ? '#e8a838' : '#94a3b8' }};font-weight:{{ $g->vendor_missing > 0 ? '700' : '400' }};">{{ $g->vendor_missing }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $g->vendor_excess > 0 ? '#1e40af' : '#94a3b8' }};font-weight:{{ $g->vendor_excess > 0 ? '700' : '400' }};">{{ $g->vendor_excess }}</td>
                    <td>@php $sc = ['received'=>'badge-success','partial'=>'badge-warning','pending'=>'badge-gray','verified'=>'badge-info']; @endphp<span class="badge {{ $sc[$g->status] ?? 'badge-gray' }}">{{ ucfirst($g->status ?? 'pending') }}</span></td>
                    <td><a href="{{ route('vendor.grn.show', $g) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View</a></td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-clipboard-check" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No GRNs found for your consignments.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($grns->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $grns->links('pagination::tailwind') }}</div>@endif
</div>
@endsection