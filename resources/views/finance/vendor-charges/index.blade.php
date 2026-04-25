@extends('layouts.app')
@section('title', 'Vendor Charges')
@section('page-title', 'Monthly Vendor Warehouse Charges')

@section('content')
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Total Charges</div><div class="kpi-value" style="color:#dc2626;">${{ number_format($stats['total_charges'], 2) }}</div><div style="font-size:.62rem;color:#94a3b8;">{{ $stats['vendor_count'] }} vendors</div></div>
    <div class="kpi-card" style="border-left:3px solid #1e40af;"><div class="kpi-label">Storage</div><div class="kpi-value" style="color:#1e40af;">${{ number_format($stats['total_storage'], 2) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Fulfillment + P&P</div><div class="kpi-value" style="color:#e8a838;">${{ number_format($stats['total_fulfill'] + $stats['total_pickpack'], 2) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;"><div class="kpi-label">Pending Approval</div><div class="kpi-value" style="color:#16a34a;">{{ $stats['pending_count'] }}</div></div>
</div>

{{-- Filters + Run --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('finance.vendor-charges') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;">Month</label><select name="month" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $month==$m?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;">Year</label><select name="year" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@for($y=date('Y');$y>=date('Y')-2;$y--)<option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>@endfor</select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;">Vendor</label><select name="vendor_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"><option value="">All</option>@foreach($vendors as $v)<option value="{{ $v->id }}" {{ request('vendor_id')==(string)$v->id?'selected':'' }}>{{ $v->company_name }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('finance.vendor-charges') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('finance.vendor-charges.download', ['month'=>$month,'year'=>$year]) }}" class="btn btn-secondary btn-sm" style="margin-left:auto;"><i class="fas fa-download"></i> CSV</a>
            <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('runPanel').style.display=document.getElementById('runPanel').style.display==='none'?'block':'none'"><i class="fas fa-play"></i> Run Charges</button>
        </form>
    </div>
</div>

{{-- Run Panel --}}
<div id="runPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#16a34a;">
        <div class="card-body">
            <form method="POST" action="{{ route('finance.vendor-charges.run') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;" onsubmit="return confirm('Calculate monthly vendor charges? This uses approved rate cards and current inventory/sales data.')">@csrf
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Month *</label><select name="month" required style="padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $m==now()->month?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Year *</label><input type="number" name="year" value="{{ date('Y') }}" required min="2024" style="width:80px;padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Vendor</label><select name="vendor_id" style="padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;"><option value="">All Vendors</option>@foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->company_name }}</option>@endforeach</select></div>
                <button type="submit" class="btn btn-success"><i class="fas fa-play"></i> Run</button>
            </form>
            <div style="font-size:.7rem;color:#64748b;margin-top:.3rem;"><i class="fas fa-info-circle"></i> Calculates: Inward + Storage + Fulfillment + Pick&Pack + Material per vendor per GRN. Won't overwrite existing records.</div>
        </div>
    </div>
</div>

{{-- Charges Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-calculator" style="margin-right:.5rem;color:#1e3a5f;"></i> Vendor Charges — {{ date('M', mktime(0,0,0,$month,1)) }} {{ $year }}</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Vendor</th><th>GRN</th><th>Inward</th><th>Storage</th><th>Fulfillment</th><th>Pick & Pack</th><th>Material</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($charges as $c)
                @php $sym = $c->getCurrencySymbol(); @endphp
                <tr>
                    <td><div style="font-weight:600;font-size:.82rem;">{{ $c->vendor->company_name ?? '—' }}</div><div style="font-size:.6rem;color:#94a3b8;">{{ $c->vendor->vendor_code ?? '' }}</div></td>
                    <td style="font-family:monospace;font-size:.78rem;">{{ $c->grn->grn_number ?? '—' }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($c->inward_charge), 2) }}<div style="font-size:.58rem;color:#94a3b8;">{{ $c->inward_cartons }} cartons</div></td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($c->storage_charge), 2) }}<div style="font-size:.58rem;color:#94a3b8;">{{ number_format(floatval($c->storage_cft), 1) }} CFT</div></td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($c->fulfillment_charge), 2) }}<div style="font-size:.58rem;color:#94a3b8;">{{ $c->fulfillment_orders_small }}s + {{ $c->fulfillment_orders_large }}l</div></td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($c->pick_pack_charge), 2) }}<div style="font-size:.58rem;color:#94a3b8;">{{ $c->pick_pack_units }} units</div></td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($c->material_cost), 2) }}</td>
                    <td style="font-family:monospace;font-weight:800;color:#dc2626;">{{ $sym }}{{ number_format(floatval($c->total_charges), 2) }}</td>
                    <td>
                        @php $sc = ['calculated'=>'badge-warning','approved'=>'badge-success','deducted'=>'badge-info','disputed'=>'badge-danger']; @endphp
                        <span class="badge {{ $sc[$c->status] ?? 'badge-gray' }}">{{ ucfirst($c->status) }}</span>
                    </td>
                    <td>
                        @if($c->status === 'calculated')
                        <form method="POST" action="{{ route('finance.vendor-charges.approve', $c) }}" style="display:inline;" onsubmit="return confirm('Approve and lock?')">@csrf<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button></form>
                        @endif
                        <a href="{{ route('finance.vendor-charges.statement', [$c->vendor, 'month'=>$month, 'year'=>$year]) }}" class="btn btn-outline btn-sm" title="Statement"><i class="fas fa-file-alt"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;">No charges for this period. Click "Run Charges" to calculate.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($charges->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $charges->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
