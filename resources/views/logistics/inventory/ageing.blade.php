@extends('layouts.app')
@section('title', 'Inventory Ageing')
@section('page-title', 'Inventory Ageing Dashboard')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('logistics.inventory') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Inventory</a>
    <a href="{{ route('logistics.inventory.download', ['company_code' => $companyCode]) }}" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Download CSV</a>
</div>

{{-- Ageing Summary --}}
<div class="grid-kpi" style="grid-template-columns:repeat(5,1fr);">
    @foreach(['0_30'=>['0-30d','#16a34a','#dcfce7'],'31_60'=>['31-60d','#e8a838','#fef3c7'],'61_90'=>['61-90d','#f97316','#fff7ed'],'91_120'=>['91-120d','#dc2626','#fee2e2'],'120_plus'=>['120+d','#991b1b','#fef2f2']] as $key=>[$label,$color,$bg])
    <div class="kpi-card" style="border-left:3px solid {{ $color }};cursor:pointer;" onclick="window.location='{{ route('logistics.inventory', ['ageing'=>$key,'company_code'=>$companyCode]) }}'">
        <div class="kpi-label">{{ $label }}</div>
        <div class="kpi-value" style="color:{{ $color }};">{{ number_format($ageing[$key] ?? 0) }}</div>
        <div style="font-size:.68rem;color:#94a3b8;">units</div>
    </div>
    @endforeach
</div>

{{-- Ageing by Warehouse --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-warehouse" style="margin-right:.5rem;color:#1e3a5f;"></i> Ageing by Warehouse</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Warehouse</th><th>Company</th><th style="color:#16a34a;">0-30d</th><th style="color:#e8a838;">31-60d</th><th style="color:#f97316;">61-90d</th><th style="color:#dc2626;">91+d</th><th>Total</th></tr></thead>
            <tbody>
                @foreach($byWarehouse as $row)
                @php $total = $row['0_30'] + $row['31_60'] + $row['61_90'] + $row['91_plus']; @endphp
                <tr>
                    <td style="font-weight:600;">{{ $row['warehouse']->name }}</td>
                    <td>{{ $row['warehouse']->company_code }}</td>
                    <td style="text-align:center;font-weight:600;color:#16a34a;">{{ number_format($row['0_30']) }}</td>
                    <td style="text-align:center;font-weight:600;color:#e8a838;">{{ number_format($row['31_60']) }}</td>
                    <td style="text-align:center;font-weight:600;color:#f97316;">{{ number_format($row['61_90']) }}</td>
                    <td style="text-align:center;font-weight:700;color:#dc2626;">{{ number_format($row['91_plus']) }}</td>
                    <td style="text-align:center;font-weight:800;">{{ number_format($total) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- GRN Ageing (Shipment-wise) --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-clock" style="margin-right:.5rem;color:#dc2626;"></i> GRN Ageing (Shipment-wise)</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>GRN #</th><th>Shipment</th><th>Warehouse</th><th>Receipt Date</th><th>Items</th><th>Ageing</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($grnAgeing as $grn)
                <tr style="{{ $grn->ageing_days > 90 ? 'background:#fef2f2;' : '' }}">
                    <td style="font-weight:600;font-family:monospace;font-size:.82rem;">{{ $grn->grn_number }}</td>
                    <td style="font-size:.8rem;">{{ $grn->shipment->shipment_code ?? '—' }}</td>
                    <td style="font-size:.8rem;">{{ $grn->warehouse->name ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $grn->receipt_date->format('d M Y') }}</td>
                    <td style="text-align:center;">{{ $grn->total_items_received }}</td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .5rem;border-radius:6px;font-weight:700;font-size:.82rem;background:{{ $grn->ageing_days>90?'#fee2e2':($grn->ageing_days>60?'#fef3c7':($grn->ageing_days>30?'#fefce8':'#dcfce7')) }};color:{{ $grn->ageing_days>90?'#dc2626':($grn->ageing_days>60?'#e8a838':($grn->ageing_days>30?'#854d0e':'#166534')) }};">
                            <i class="fas fa-clock" style="font-size:.65rem;"></i> {{ $grn->ageing_days }} days
                        </span>
                    </td>
                    <td><span class="badge {{ $grn->status==='completed'?'badge-success':'badge-warning' }}">{{ ucfirst($grn->status) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;">No GRN data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
