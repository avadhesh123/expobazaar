@extends('layouts.app')
@section('title', 'Warehouse Charges')
@section('page-title', 'Warehouse Cost Management')

@section('content')
{{-- Charge Summary KPIs --}}
<div class="grid-kpi" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));">
    @foreach(['inward'=>['Inward','#1e40af','#dbeafe'],'storage'=>['Storage','#e8a838','#fef3c7'],'pick_pack'=>['Pick & Pack','#2d6a4f','#dcfce7'],'consumable'=>['Consumable','#7c3aed','#ede9fe'],'last_mile'=>['Last Mile','#dc2626','#fee2e2']] as $key=>[$label,$color,$bg])
    <div class="kpi-card" style="border-left:3px solid {{ $color }};">
        <div class="kpi-label">{{ $label }}</div>
        <div class="kpi-value" style="color:{{ $color }};">${{ number_format($chargeSummary[$key] ?? 0, 0) }}</div>
    </div>
    @endforeach
    <div class="kpi-card" style="border-left:3px solid {{ ($chargeSummary['total_variance'] ?? 0) != 0 ? '#dc2626' : '#16a34a' }};">
        <div class="kpi-label">Total Variance</div>
        <div class="kpi-value" style="color:{{ ($chargeSummary['total_variance'] ?? 0) != 0 ? '#dc2626' : '#16a34a' }};">${{ number_format(abs($chargeSummary['total_variance'] ?? 0), 0) }}</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('logistics.warehouse-charges') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option></select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Vendor</label><select name="vendor_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($vendors as $v)<option value="{{ $v->id }}" {{ request('vendor_id')==(string)$v->id?'selected':'' }}>{{ $v->company_name }}</option>@endforeach</select></div>
            <div style="min-width:120px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Charge Type</label><select name="charge_type" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach(['inward','storage','pick_pack','consumable','last_mile','other'] as $t)<option value="{{ $t }}" {{ request('charge_type')===$t?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$t)) }}</option>@endforeach</select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Month</label><select name="month" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ request('month')==(string)$m?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Year</label><select name="year" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@for($y=date('Y');$y>=date('Y')-2;$y--)<option value="{{ $y }}" {{ request('year')==(string)$y?'selected':'' }}>{{ $y }}</option>@endfor</select></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label><select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach(['calculated','receipt_uploaded','verified','allocated'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('logistics.warehouse-charges') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <button type="button" class="btn btn-secondary btn-sm" style="margin-left:auto;" onclick="document.getElementById('calcPanel').style.display=document.getElementById('calcPanel').style.display==='none'?'block':'none'"><i class="fas fa-calculator"></i> Calculate Charges</button>
        </form>
    </div>
</div>

{{-- Calculate Charges Panel --}}
<div id="calcPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#e8a838;">
        <div class="card-body">
            <div style="font-size:.88rem;font-weight:700;color:#0d1b2a;margin-bottom:.75rem;">Calculate Monthly Warehouse Charges</div>
            <form method="POST" action="{{ route('logistics.warehouse-charges.calculate') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
                @csrf
                <div class="form-group" style="margin-bottom:0;min-width:180px;"><label>Vendor *</label><select name="vendor_id" required><option value="">Select...</option>@foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->company_name }}</option>@endforeach</select></div>
                <div class="form-group" style="margin-bottom:0;min-width:160px;"><label>Warehouse *</label><select name="warehouse_id" required><option value="">Select...</option>@foreach($warehouses as $wh)<option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->company_code }})</option>@endforeach</select></div>
                <div class="form-group" style="margin-bottom:0;"><label>Month *</label><select name="month" required>@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $m==date('n')?'selected':'' }}>{{ date('F',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
                <div class="form-group" style="margin-bottom:0;"><label>Year *</label><input type="number" name="year" value="{{ date('Y') }}" required min="2020" style="width:80px;"></div>
                <button type="submit" class="btn btn-secondary"><i class="fas fa-calculator" style="margin-right:.3rem;"></i> Calculate</button>
            </form>
            <div style="margin-top:.75rem;font-size:.75rem;color:#64748b;"><i class="fas fa-info-circle" style="margin-right:.2rem;"></i> Calculates inward, storage, pick & pack, consumable, and last mile charges based on the warehouse rate card and current inventory.</div>
        </div>
    </div>
</div>

{{-- Charges Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-calculator" style="margin-right:.5rem;color:#2d6a4f;"></i> Warehouse Charges</h3><span style="font-size:.78rem;color:#64748b;">{{ $charges->total() }} records</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Vendor</th><th>Warehouse</th><th>Company</th><th>Period</th><th>Type</th><th>Calculated</th><th>Actual</th><th>Variance</th><th>Status</th><th style="width:160px;">Actions</th></tr></thead>
            <tbody>
                @forelse($charges as $ch)
                <tr>
                    <td><div style="font-size:.82rem;font-weight:500;">{{ $ch->vendor->company_name ?? '—' }}</div><div style="font-size:.68rem;color:#94a3b8;">{{ $ch->vendor->vendor_code ?? '' }}</div></td>
                    <td style="font-size:.82rem;">{{ $ch->warehouse->name ?? '—' }}</td>
                    <td>{{ $ch->company_code }}</td>
                    <td style="font-weight:600;">{{ date('M',mktime(0,0,0,$ch->charge_month,1)) }} {{ $ch->charge_year }}</td>
                    <td><span class="badge badge-info">{{ ucfirst(str_replace('_',' ',$ch->charge_type)) }}</span></td>
                    <td style="font-family:monospace;font-weight:600;">${{ number_format($ch->calculated_amount, 2) }}</td>
                    <td style="font-family:monospace;font-weight:600;color:{{ $ch->actual_amount>0?'#0d1b2a':'#94a3b8' }};">{{ $ch->actual_amount > 0 ? '$'.number_format($ch->actual_amount, 2) : '—' }}</td>
                    <td>
                        @if($ch->variance != 0)
                            <span style="font-family:monospace;font-weight:700;color:{{ $ch->variance > 0 ? '#dc2626' : '#16a34a' }};">{{ $ch->variance > 0 ? '+' : '' }}${{ number_format($ch->variance, 2) }}</span>
                            @if($ch->variance_comment)<div style="font-size:.65rem;color:#64748b;max-width:120px;" title="{{ $ch->variance_comment }}">{{ Str::limit($ch->variance_comment, 30) }}</div>@endif
                        @else
                            <span style="color:#94a3b8;">—</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $ch->status==='allocated'?'badge-success':($ch->status==='receipt_uploaded'?'badge-info':($ch->status==='verified'?'badge-info':'badge-warning')) }}">{{ ucfirst(str_replace('_',' ',$ch->status)) }}</span></td>
                    <td>
                        @if($ch->status === 'calculated')
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('rcpt{{ $ch->id }}').style.display=document.getElementById('rcpt{{ $ch->id }}').style.display==='none'?'table-row':'none'"><i class="fas fa-upload"></i> Receipt</button>
                        @elseif($ch->receipt_file)
                        <a href="{{ asset('storage/' . $ch->receipt_file) }}" class="btn btn-outline btn-sm" target="_blank"><i class="fas fa-file-download"></i> Receipt</a>
                        @endif
                    </td>
                </tr>
                {{-- Receipt Upload Row --}}
                @if($ch->status === 'calculated')
                <tr id="rcpt{{ $ch->id }}" style="display:none;background:#eff6ff;">
                    <td colspan="10" style="padding:1rem;">
                        <form method="POST" action="{{ route('logistics.warehouse-charges.receipt', $ch) }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">
                            @csrf
                            <div><label style="font-size:.68rem;font-weight:600;color:#64748b;">Actual Amount *</label><input type="number" step="0.01" name="actual_amount" required value="{{ $ch->calculated_amount }}" style="width:120px;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:monospace;"></div>
                            <div><label style="font-size:.68rem;font-weight:600;color:#64748b;">Receipt File *</label><input type="file" name="receipt" required accept=".pdf,.jpg,.png,.xlsx" style="font-size:.78rem;"></div>
                            <div><label style="font-size:.68rem;font-weight:600;color:#64748b;">Variance Comment</label><input type="text" name="variance_comment" placeholder="Explain variance..." style="width:200px;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </td>
                </tr>
                @endif
                @empty
                <tr><td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-calculator" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No charges found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($charges->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $charges->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
