@extends('layouts.app')
@section('title', 'My Rate Card')
@section('page-title', 'Warehouse Rate Card')

@section('content')
@if($rateCard)
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <h3><i class="fas fa-file-invoice-dollar" style="margin-right:.5rem;color:#e8a838;"></i> My Rate Card</h3>
        <div style="display:flex;gap:.4rem;align-items:center;">
            <span style="font-size:.78rem;color:#64748b;">{{ $history->count() }} rate card(s) across {{ $history->pluck('warehouse_id')->unique()->count() }} warehouse(s)</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th>Vendor</th>
                    <th>Warehouse</th>
                    <th style="text-align:right;">Inward / Carton</th>
                    <th style="text-align:right;">Storage / CFT</th>
                    <th style="text-align:right;">Fulfill ≤ Threshold</th>
                    <th style="text-align:right;">Fulfill > Threshold</th>
                    <th style="text-align:center;">Threshold</th>
                    <th style="text-align:right;">Pick & Pack / Unit</th>
                    <th>Effective</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history as $rc)
                <tr style="{{ $rc->status === 'approved' ? 'background:#f0fdf4;' : '' }}">
                    <td>
                        <div style="font-weight:600;">{{ $vendor->company_name }}</div>
                        <div style="font-size:.6rem;color:#94a3b8;">{{ $vendor->vendor_code ?? '' }} · {{ $rc->currency }}</div>
                    </td>
                    <td style="font-size:.78rem;">
                        @php $wh = $warehouses->firstWhere('company_code', $rc->company_code); @endphp
                        {{ $wh->name ?? $rc->company_code }}
                    </td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $sym }}{{ number_format(floatval($rc->inward_rate_per_carton), 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $sym }}{{ number_format(floatval($rc->storage_rate_per_cft), 4) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $sym }}{{ number_format(floatval($rc->fulfillment_rate_small), 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $sym }}{{ number_format(floatval($rc->fulfillment_rate_large), 2) }}</td>
                    <td style="text-align:center;font-weight:700;">{{ $rc->fulfillment_qty_threshold }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $sym }}{{ number_format(floatval($rc->pick_pack_rate_per_unit), 2) }}</td>
                    <td style="font-size:.72rem;">{{ $rc->effective_from?->format('d M Y') ?? '—' }} → {{ $rc->effective_to?->format('d M Y') ?? 'ongoing' }}</td>
                    <td>@php $sc = ['approved'=>'badge-success','expired'=>'badge-gray','draft'=>'badge-gray','pending_approval'=>'badge-warning']; @endphp<span class="badge {{ $sc[$rc->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$rc->status)) }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card">
    <div class="card-body" style="text-align:center;padding:3rem;">
        <i class="fas fa-file-invoice-dollar" style="font-size:2.5rem;color:#d1d5db;display:block;margin-bottom:.75rem;"></i>
        <div style="font-size:1rem;font-weight:700;color:#64748b;margin-bottom:.3rem;">No Rate Card Assigned</div>
        <div style="font-size:.82rem;color:#94a3b8;">Your warehouse rate card has not been set up yet. Please contact the operations team for details.</div>
    </div>
</div>
@endif
@endsection