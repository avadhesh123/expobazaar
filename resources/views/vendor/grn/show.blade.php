@extends('layouts.app')
@section('title', 'GRN — ' . $grn->grn_number)
@section('page-title', 'GRN Details')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('vendor.grn') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back to GRNs</a>
</div>

{{-- GRN Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1.25rem 1.4rem;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;">
            <div>
                <div style="font-size:1.2rem;font-weight:800;font-family:monospace;color:#0d1b2a;">{{ $grn->grn_number }}</div>
                <div style="font-size:.78rem;color:#64748b;margin-top:.2rem;">
                    Shipment: <strong>{{ $grn->shipment->shipment_code ?? '—' }}</strong>
                    · Warehouse: <strong>{{ $grn->warehouse->name ?? '—' }}</strong>
                    · {{ $grn->warehouse->city ?? '' }}
                </div>
            </div>
            <div style="text-align:right;">
                @php $sc = ['received'=>'badge-success','partial'=>'badge-warning','pending'=>'badge-gray','verified'=>'badge-info']; @endphp
                <span class="badge {{ $sc[$grn->status] ?? 'badge-gray' }}" style="font-size:.82rem;">{{ ucfirst($grn->status ?? 'pending') }}</span>
                <div style="font-size:.72rem;color:#64748b;margin-top:.2rem;">Receipt: {{ $grn->receipt_date?->format('d M Y') ?? '—' }}</div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;">
            <div style="text-align:center;padding:.6rem;background:#eff6ff;border-radius:8px;">
                <div style="font-size:.62rem;font-weight:600;color:#64748b;text-transform:uppercase;">Expected</div>
                <div style="font-size:1.1rem;font-weight:800;color:#1e40af;">{{ number_format($itemStats['total_expected']) }}</div>
            </div>
            <div style="text-align:center;padding:.6rem;background:#f0fdf4;border-radius:8px;">
                <div style="font-size:.62rem;font-weight:600;color:#64748b;text-transform:uppercase;">Received</div>
                <div style="font-size:1.1rem;font-weight:800;color:#16a34a;">{{ number_format($itemStats['total_received']) }}</div>
            </div>
            <div style="text-align:center;padding:.6rem;background:#fef2f2;border-radius:8px;">
                <div style="font-size:.62rem;font-weight:600;color:#64748b;text-transform:uppercase;">Damaged</div>
                <div style="font-size:1.1rem;font-weight:800;color:#dc2626;">{{ number_format($itemStats['total_damaged']) }}</div>
            </div>
            <div style="text-align:center;padding:.6rem;background:#fefce8;border-radius:8px;">
                <div style="font-size:.62rem;font-weight:600;color:#64748b;text-transform:uppercase;">Missing</div>
                <div style="font-size:1.1rem;font-weight:800;color:#e8a838;">{{ number_format($itemStats['total_missing']) }}</div>
            </div>
            <div style="text-align:center;padding:.6rem;background:#eff6ff;border-radius:8px;">
                <div style="font-size:.62rem;font-weight:600;color:#64748b;text-transform:uppercase;">Excess</div>
                <div style="font-size:1.1rem;font-weight:800;color:#1e40af;">{{ number_format($itemStats['total_excess']) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Remarks --}}
@if($grn->remarks)
<div style="padding:.5rem 1rem;background:#fefce8;border-radius:8px;border:1px solid #fde68a;margin-bottom:1.25rem;font-size:.78rem;color:#854d0e;">
    <strong>Remarks:</strong> {{ $grn->remarks }}
</div>
@endif

{{-- Line Items --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-boxes" style="margin-right:.5rem;color:#1e3a5f;"></i> Your Items in this GRN</h3><span style="font-size:.78rem;color:#64748b;">{{ $vendorItems->count() }} items</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Product</th><th>SKU</th><th>Consignment</th><th style="text-align:center;">Expected</th><th style="text-align:center;">Received</th><th style="text-align:center;">Damaged</th><th style="text-align:center;">Missing</th><th style="text-align:center;">Excess</th><th>Remarks</th></tr></thead>
            <tbody>
                @forelse($vendorItems as $item)
                @php
                    $expected = intval($item->expected_quantity);
                    $received = intval($item->received_quantity);
                    $damaged  = intval($item->damaged_quantity);
                    $missing  = intval($item->missing_quantity);
                    $excess   = intval($item->excess_quantity ?? 0);
                    $isShort  = $received < $expected;
                    $isOver   = ($received + $damaged) > $expected;
                @endphp
                <tr style="{{ $isShort ? 'background:#fef2f2;' : ($isOver ? 'background:#eff6ff;' : '') }}">
                    <td>
                        <div style="font-weight:600;font-size:.82rem;">{{ $item->product->name ?? '—' }}</div>
                        @if($item->product && $item->product->category)
                        <div style="font-size:.6rem;color:#94a3b8;">{{ $item->product->category->name ?? '' }}</div>
                        @endif
                    </td>
                    <td style="font-family:monospace;font-size:.8rem;">{{ $item->product->sku ?? '—' }}</td>
                    <td style="font-family:monospace;font-size:.75rem;">{{ $item->consignment->consignment_number ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-weight:600;">{{ $expected }}</td>
                    <td style="text-align:center;font-family:monospace;font-weight:700;color:#16a34a;">{{ $received }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $damaged > 0 ? '#dc2626' : '#64748b' }};font-weight:{{ $damaged > 0 ? '700' : '400' }};">{{ $damaged }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $missing > 0 ? '#e8a838' : '#64748b' }};font-weight:{{ $missing > 0 ? '700' : '400' }};">{{ $missing }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $excess > 0 ? '#1e40af' : '#64748b' }};font-weight:{{ $excess > 0 ? '700' : '400' }};">{{ $excess }}</td>
                    <td style="font-size:.72rem;color:#64748b;">{{ $item->remarks ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:3rem;color:#94a3b8;">No items found for your products in this GRN.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- GRN Document --}}
@if($grn->grn_file)
<div class="card" style="margin-top:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;display:flex;align-items:center;justify-content:space-between;">
        <div style="font-size:.82rem;color:#64748b;"><i class="fas fa-file-pdf" style="color:#dc2626;margin-right:.3rem;"></i> GRN Document attached</div>
        <a href="{{ asset('storage/app/public/' . $grn->grn_file) }}" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Download</a>
    </div>
</div>
@endif
@endsection
