@extends('layouts.app')
@section('title', 'Variance Report')
@section('page-title', 'Warehouse Charges — Variance Analysis')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('logistics.warehouse-charges') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="grid-kpi" style="grid-template-columns:repeat(3,1fr);">
    <div class="kpi-card" style="border-left:3px solid #1e40af;"><div class="kpi-label">System Calculated</div><div class="kpi-value" style="color:#1e40af;">${{ number_format(floatval($totals['calculated']), 2) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Actual Invoiced</div><div class="kpi-value" style="color:#dc2626;">${{ number_format(floatval($totals['actual']), 2) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid {{ floatval($totals['variance']) > 0 ? '#dc2626' : '#16a34a' }};"><div class="kpi-label">Total Variance</div><div class="kpi-value" style="color:{{ floatval($totals['variance']) > 0 ? '#dc2626' : '#16a34a' }};">{{ floatval($totals['variance']) > 0 ? '+' : '' }}${{ number_format(floatval($totals['variance']), 2) }}</div></div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-chart-bar" style="margin-right:.5rem;color:#1e3a5f;"></i> Variance — {{ date('M', mktime(0,0,0,$month,1)) }} {{ $year }}</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Warehouse</th><th>Calculated</th><th>Actual</th><th>Variance</th><th>%</th><th>Invoice #</th><th>Remark</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($charges as $c)
                @php $pct = floatval($c->calculated_amount) > 0 ? round((floatval($c->variance) / floatval($c->calculated_amount)) * 100, 1) : 0; @endphp
                <tr>
                    <td style="font-weight:600;">{{ $c->warehouse->name ?? '—' }}</td>
                    <td style="font-family:monospace;">${{ number_format(floatval($c->calculated_amount), 2) }}</td>
                    <td style="font-family:monospace;font-weight:700;">${{ number_format(floatval($c->actual_amount), 2) }}</td>
                    <td style="font-family:monospace;font-weight:700;color:{{ floatval($c->variance) > 0 ? '#dc2626' : '#16a34a' }};">{{ floatval($c->variance) > 0 ? '+' : '' }}${{ number_format(floatval($c->variance), 2) }}</td>
                    <td style="font-weight:700;color:{{ abs($pct) > 10 ? '#dc2626' : ($pct != 0 ? '#e8a838' : '#16a34a') }};">{{ $pct > 0 ? '+' : '' }}{{ $pct }}%</td>
                    <td style="font-size:.78rem;">{{ $c->invoice_number ?? '—' }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $c->variance_comment ?? '—' }}</td>
                    <td><span class="badge {{ $c->status==='approved'?'badge-success':'badge-warning' }}">{{ ucfirst($c->status) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;">No variance data for this period. Upload actual invoices first.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
