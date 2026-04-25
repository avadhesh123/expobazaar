@extends('layouts.app')
@section('title', 'GRN: ' . $grn->grn_number)
@section('page-title', 'GRN Details')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('logistics.grn') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All GRNs</a>
    <a href="{{ route('logistics.shipments.show', $grn->shipment) }}" class="btn btn-outline btn-sm"><i class="fas fa-ship"></i> View Shipment</a>
</div>

{{-- GRN Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <div>
                <div style="font-size:1.2rem;font-weight:800;color:#0d1b2a;font-family:monospace;">{{ $grn->grn_number }}</div>
                <div style="font-size:.78rem;color:#64748b;">Uploaded by {{ $grn->uploader->name ?? '—' }} on {{ $grn->created_at->format('d M Y H:i') }}</div>
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <span class="badge {{ $grn->status==='completed'?'badge-success':($grn->status==='verified'?'badge-info':'badge-warning') }}" style="font-size:.82rem;padding:.3rem .8rem;">{{ ucfirst($grn->status) }}</span>
                <div style="padding:.4rem .8rem;border-radius:8px;font-weight:700;font-size:.9rem;background:{{ $ageingDays>90?'#fee2e2':($ageingDays>60?'#fef3c7':($ageingDays>30?'#fefce8':'#dcfce7')) }};color:{{ $ageingDays>90?'#dc2626':($ageingDays>60?'#e8a838':($ageingDays>30?'#854d0e':'#166534')) }};">
                    {{ $ageingDays }} days ageing
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;">
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.65rem;color:#64748b;text-transform:uppercase;font-weight:600;">Shipment</div><div style="font-weight:700;font-family:monospace;font-size:.85rem;">{{ $grn->shipment->shipment_code ?? '—' }}</div></div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.65rem;color:#64748b;text-transform:uppercase;font-weight:600;">Warehouse</div><div style="font-weight:600;font-size:.85rem;">{{ $grn->warehouse->name ?? '—' }}</div></div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.65rem;color:#64748b;text-transform:uppercase;font-weight:600;">Company</div><div style="font-weight:600;font-size:.85rem;">{{ $grn->company_code }}</div></div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.65rem;color:#64748b;text-transform:uppercase;font-weight:600;">Receipt Date</div><div style="font-weight:600;font-size:.85rem;">{{ $grn->receipt_date->format('d M Y') }}</div></div>
            <div style="padding:.6rem;background:#dcfce7;border-radius:8px;"><div style="font-size:.65rem;color:#166534;text-transform:uppercase;font-weight:600;">Expected</div><div style="font-weight:800;font-size:1rem;color:#166534;">{{ $grn->total_items_expected }}</div></div>
            <div style="padding:.6rem;background:#dbeafe;border-radius:8px;"><div style="font-size:.65rem;color:#1e40af;text-transform:uppercase;font-weight:600;">Received</div><div style="font-weight:800;font-size:1rem;color:#1e40af;">{{ $grn->total_items_received }}</div></div>
            <div style="padding:.6rem;background:{{ $grn->damaged_items>0?'#fee2e2':'#f8fafc' }};border-radius:8px;"><div style="font-size:.65rem;color:{{ $grn->damaged_items>0?'#dc2626':'#64748b' }};text-transform:uppercase;font-weight:600;">Damaged</div><div style="font-weight:800;font-size:1rem;color:{{ $grn->damaged_items>0?'#dc2626':'#94a3b8' }};">{{ $grn->damaged_items }}</div></div>
            <div style="padding:.6rem;background:{{ $grn->missing_items>0?'#fef3c7':'#f8fafc' }};border-radius:8px;"><div style="font-size:.65rem;color:{{ $grn->missing_items>0?'#e8a838':'#64748b' }};text-transform:uppercase;font-weight:600;">Missing</div><div style="font-weight:800;font-size:1rem;color:{{ $grn->missing_items>0?'#e8a838':'#94a3b8' }};">{{ $grn->missing_items }}</div></div>
        </div>
        @if($grn->remarks)<div style="margin-top:.75rem;padding:.5rem .75rem;background:#fefce8;border-radius:6px;font-size:.82rem;color:#854d0e;"><i class="fas fa-sticky-note" style="margin-right:.3rem;"></i> {{ $grn->remarks }}</div>@endif
        @if($grn->grn_file)<div style="margin-top:.5rem;"><a href="{{ asset('storage/' . $grn->grn_file) }}" class="btn btn-outline btn-sm" target="_blank"><i class="fas fa-file-download"></i> Download GRN Document</a></div>@endif
    </div>
</div>

{{-- GRN Items --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-boxes" style="margin-right:.5rem;color:#1e3a5f;"></i> Received Items ({{ $grn->items->count() }})</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Product</th><th>SKU</th><th>Vendor</th><th>Expected</th><th>Received</th><th>Damaged</th><th>Missing</th><th>Match</th><th>Remarks</th></tr></thead>
            <tbody>
                @foreach($grn->items as $item)
                @php $match = $item->received_quantity >= $item->expected_quantity; @endphp
                <tr style="{{ !$match?'background:#fef2f2;':'' }}">
                    <td style="font-weight:600;font-size:.82rem;">{{ $item->product->name ?? '—' }}</td>
                    <td style="font-family:monospace;font-size:.8rem;">{{ $item->product->sku ?? '—' }}</td>
                    <td style="font-size:.8rem;">{{ $item->product->vendor->company_name ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;">{{ $item->expected_quantity }}</td>
                    <td style="text-align:center;font-family:monospace;font-weight:700;color:#166534;">{{ $item->received_quantity }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $item->damaged_quantity>0?'#dc2626':'#94a3b8' }};">{{ $item->damaged_quantity }}</td>
                    <td style="text-align:center;font-family:monospace;color:{{ $item->missing_quantity>0?'#e8a838':'#94a3b8' }};">{{ $item->missing_quantity }}</td>
                    <td style="text-align:center;">
                        @if($match)<i class="fas fa-check-circle" style="color:#16a34a;"></i>@else<i class="fas fa-exclamation-circle" style="color:#dc2626;"></i>@endif
                    </td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $item->remarks ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
