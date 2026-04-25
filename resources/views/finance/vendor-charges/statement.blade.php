@extends('layouts.app')
@section('title', 'Vendor Statement')
@section('page-title', 'Vendor Warehouse Charges Statement')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('finance.vendor-charges') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
</div>

@php $sym = $statement['currency'] === 'EUR' ? '€' : '$'; @endphp

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1.25rem 1.4rem;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
            <div>
                <div style="font-size:1.2rem;font-weight:800;color:#0d1b2a;">{{ $statement['vendor']->company_name }}</div>
                <div style="font-size:.78rem;color:#64748b;">{{ $statement['vendor']->vendor_code }} · {{ $statement['vendor']->company_code }} · Period: {{ $statement['period'] }}</div>
            </div>
            @if($statement['is_negative'])
            <div style="padding:.5rem 1rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;color:#dc2626;font-weight:700;">⚠ Negative Payout — Manual Review Required</div>
            @endif
        </div>
    </div>
</div>

{{-- Charges Breakdown --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3>Charges by GRN / Consignment</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>GRN #</th><th>Warehouse</th><th>Inward</th><th>Storage</th><th>Fulfillment</th><th>Pick & Pack</th><th>Material</th><th>Total</th></tr></thead>
            <tbody>
                @foreach($statement['charges'] as $c)
                <tr>
                    <td style="font-family:monospace;font-weight:600;">{{ $c->grn->grn_number ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $c->warehouse->name ?? '—' }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($c->inward_charge), 2) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($c->storage_charge), 2) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($c->fulfillment_charge), 2) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($c->pick_pack_charge), 2) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($c->material_cost), 2) }}</td>
                    <td style="font-family:monospace;font-weight:700;">{{ $sym }}{{ number_format(floatval($c->total_charges), 2) }}</td>
                </tr>
                @endforeach
                <tr style="background:#f0f4f8;font-weight:800;">
                    <td colspan="2">GRAND TOTAL</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($statement['totals']['inward']), 2) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($statement['totals']['storage']), 2) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($statement['totals']['fulfillment']), 2) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($statement['totals']['pick_pack']), 2) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($statement['totals']['material']), 2) }}</td>
                    <td style="font-family:monospace;font-size:1rem;color:#dc2626;">{{ $sym }}{{ number_format(floatval($statement['totals']['total']), 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- Payout Summary --}}
<div class="card">
    <div class="card-header"><h3>Payout Summary</h3></div>
    <div class="card-body">
        <table style="width:50%;min-width:300px;">
            <tr><td style="padding:.5rem;font-size:.88rem;">Gross Payout (Sales)</td><td style="font-family:monospace;font-weight:600;text-align:right;padding:.5rem;">{{ $sym }}{{ number_format($statement['gross_payout'], 2) }}</td></tr>
            <tr style="color:#dc2626;"><td style="padding:.5rem;font-size:.88rem;">Less: Warehouse Charges</td><td style="font-family:monospace;font-weight:600;text-align:right;padding:.5rem;">-{{ $sym }}{{ number_format($statement['totals']['total'], 2) }}</td></tr>
            <tr style="border-top:2px solid #0d1b2a;font-weight:800;font-size:1.1rem;"><td style="padding:.75rem .5rem;">Net Payout</td><td style="font-family:monospace;text-align:right;padding:.75rem .5rem;color:{{ $statement['net_payout'] >= 0 ? '#166534' : '#dc2626' }};">{{ $sym }}{{ number_format($statement['net_payout'], 2) }}</td></tr>
        </table>
    </div>
</div>
@endsection
