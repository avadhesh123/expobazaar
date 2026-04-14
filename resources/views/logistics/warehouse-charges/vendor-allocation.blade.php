@extends('layouts.app')
@section('title', 'Vendor Cost Allocation')
@section('page-title', 'Vendor Warehousing Cost Allocation')

@section('content')
{{-- Info Banner --}}
<div style="padding:.75rem 1.2rem;background:#eff6ff;border-radius:10px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.82rem;color:#1e40af;display:flex;align-items:center;gap:.5rem;">
    <i class="fas fa-info-circle"></i>
    <span>Vendor warehousing costs are calculated monthly based on rate cards and deducted from vendor payouts. Charges are allocated by company code.</span>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('logistics.warehouse-charges.vendor-allocation') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ ($companyCode??'')==='2000'?'selected':'' }}>🇮🇳 2000</option><option value="2100" {{ ($companyCode??'')==='2100'?'selected':'' }}>🇺🇸 2100</option><option value="2200" {{ ($companyCode??'')==='2200'?'selected':'' }}>🇳🇱 2200</option></select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Month</label><select name="month" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $month==$m?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Year</label><select name="year" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">@for($y=date('Y');$y>=date('Y')-2;$y--)<option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>@endfor</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <div style="margin-left:auto;display:flex;gap:.4rem;">
                <a href="{{ route('logistics.warehouse-charges') }}" class="btn btn-outline btn-sm"><i class="fas fa-list"></i> All Charges</a>
                <a href="{{ route('logistics.rate-cards') }}" class="btn btn-outline btn-sm"><i class="fas fa-tags"></i> Rate Cards</a>
            </div>
        </form>
    </div>
</div>

{{-- Grand Totals --}}
@php
    $grandInward = collect($allocations)->sum('inward');
    $grandStorage = collect($allocations)->sum('storage');
    $grandPickPack = collect($allocations)->sum('pick_pack');
    $grandConsumable = collect($allocations)->sum('consumable');
    $grandLastMile = collect($allocations)->sum('last_mile');
    $grandTotal = collect($allocations)->sum('total_calculated');
    $grandVariance = collect($allocations)->sum('total_variance');
@endphp
<div class="grid-kpi" style="grid-template-columns:repeat(auto-fill,minmax(145px,1fr));">
    <div class="kpi-card" style="border-left:3px solid #1e40af;"><div class="kpi-label">Inward</div><div class="kpi-value" style="font-size:1.3rem;color:#1e40af;">${{ number_format($grandInward,0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Storage</div><div class="kpi-value" style="font-size:1.3rem;color:#e8a838;">${{ number_format($grandStorage,0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #2d6a4f;"><div class="kpi-label">Pick & Pack</div><div class="kpi-value" style="font-size:1.3rem;color:#2d6a4f;">${{ number_format($grandPickPack,0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #7c3aed;"><div class="kpi-label">Consumable</div><div class="kpi-value" style="font-size:1.3rem;color:#7c3aed;">${{ number_format($grandConsumable,0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Last Mile</div><div class="kpi-value" style="font-size:1.3rem;color:#dc2626;">${{ number_format($grandLastMile,0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #0d1b2a;"><div class="kpi-label">Total Charges</div><div class="kpi-value" style="font-size:1.3rem;">${{ number_format($grandTotal,0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid {{ $grandVariance!=0?'#dc2626':'#16a34a' }};"><div class="kpi-label">Variance</div><div class="kpi-value" style="font-size:1.3rem;color:{{ $grandVariance!=0?'#dc2626':'#16a34a' }};">${{ number_format(abs($grandVariance),0) }}</div></div>
</div>

{{-- Vendor Allocation Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users" style="margin-right:.5rem;color:#e8a838;"></i> Vendor Cost Allocation — {{ date('F',mktime(0,0,0,$month,1)) }} {{ $year }}</h3>
        <span style="font-size:.78rem;color:#64748b;">{{ count($allocations) }} vendors</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>Company</th>
                    <th style="color:#1e40af;">Inward</th>
                    <th style="color:#e8a838;">Storage</th>
                    <th style="color:#2d6a4f;">Pick & Pack</th>
                    <th style="color:#7c3aed;">Consumable</th>
                    <th style="color:#dc2626;">Last Mile</th>
                    <th>Total Calculated</th>
                    <th>Total Actual</th>
                    <th>Variance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allocations as $a)
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ $a['vendor']->company_name }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;font-family:monospace;">{{ $a['vendor']->vendor_code }}</div>
                    </td>
                    <td>
                        @php $ccBg = ['2000'=>'#dcfce7','2100'=>'#dbeafe','2200'=>'#fef3c7']; @endphp
                        <span style="padding:.15rem .4rem;background:{{ $ccBg[$a['vendor']->company_code] ?? '#f1f5f9' }};border-radius:5px;font-size:.78rem;font-weight:600;">{{ $a['vendor']->company_code }}</span>
                    </td>
                    <td style="font-family:monospace;color:#1e40af;">${{ number_format($a['inward'],2) }}</td>
                    <td style="font-family:monospace;color:#e8a838;">${{ number_format($a['storage'],2) }}</td>
                    <td style="font-family:monospace;color:#2d6a4f;">${{ number_format($a['pick_pack'],2) }}</td>
                    <td style="font-family:monospace;color:#7c3aed;">${{ number_format($a['consumable'],2) }}</td>
                    <td style="font-family:monospace;color:#dc2626;">${{ number_format($a['last_mile'],2) }}</td>
                    <td style="font-family:monospace;font-weight:800;">${{ number_format($a['total_calculated'],2) }}</td>
                    <td style="font-family:monospace;font-weight:600;color:{{ $a['total_actual']>0?'#0d1b2a':'#94a3b8' }};">{{ $a['total_actual']>0?'$'.number_format($a['total_actual'],2):'—' }}</td>
                    <td>
                        @if($a['total_variance'] != 0)
                            <span style="font-family:monospace;font-weight:700;color:{{ $a['total_variance']>0?'#dc2626':'#16a34a' }};">{{ $a['total_variance']>0?'+':'' }}${{ number_format($a['total_variance'],2) }}</span>
                        @else
                            <span style="color:#94a3b8;">—</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $a['status']==='receipt_uploaded'?'badge-info':'badge-warning' }}">{{ ucfirst(str_replace('_',' ',$a['status'])) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="11" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-calculator" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No charges calculated for this period. Use "Calculate Charges" to generate.</td></tr>
                @endforelse

                {{-- Grand Total Row --}}
                @if(count($allocations) > 0)
                <tr style="background:#f8fafc;font-weight:800;border-top:2px solid #e2e8f0;">
                    <td colspan="2" style="text-align:right;">GRAND TOTAL</td>
                    <td style="font-family:monospace;color:#1e40af;">${{ number_format($grandInward,2) }}</td>
                    <td style="font-family:monospace;color:#e8a838;">${{ number_format($grandStorage,2) }}</td>
                    <td style="font-family:monospace;color:#2d6a4f;">${{ number_format($grandPickPack,2) }}</td>
                    <td style="font-family:monospace;color:#7c3aed;">${{ number_format($grandConsumable,2) }}</td>
                    <td style="font-family:monospace;color:#dc2626;">${{ number_format($grandLastMile,2) }}</td>
                    <td style="font-family:monospace;">${{ number_format($grandTotal,2) }}</td>
                    <td style="font-family:monospace;">${{ number_format(collect($allocations)->sum('total_actual'),2) }}</td>
                    <td style="font-family:monospace;color:{{ $grandVariance!=0?'#dc2626':'#16a34a' }};">{{ $grandVariance!=0?'$'.number_format(abs($grandVariance),2):'—' }}</td>
                    <td></td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

{{-- Bulk Calculate Panel --}}
<div class="card" style="margin-top:1.25rem;border-color:#e8a838;">
    <div class="card-header" style="background:#fffbeb;"><h3><i class="fas fa-calculator" style="margin-right:.5rem;color:#e8a838;"></i> Bulk Calculate All Vendor Charges</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.warehouse-charges.bulk-calculate') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;" onsubmit="return confirm('Calculate charges for ALL vendors with inventory at this warehouse?')">
            @csrf
            <div class="form-group" style="margin-bottom:0;min-width:200px;"><label>Warehouse <span style="color:#dc2626;">*</span></label><select name="warehouse_id" required><option value="">Select...</option>@foreach($warehouses as $wh)<option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->company_code }})</option>@endforeach</select></div>
            <div class="form-group" style="margin-bottom:0;"><label>Month <span style="color:#dc2626;">*</span></label><select name="month" required>@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $m==$month?'selected':'' }}>{{ date('F',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
            <div class="form-group" style="margin-bottom:0;"><label>Year <span style="color:#dc2626;">*</span></label><input type="number" name="year" value="{{ $year }}" required min="2020" max="2030" style="width:80px;"></div>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-calculator" style="margin-right:.3rem;"></i> Calculate All Vendors</button>
        </form>
        <div style="margin-top:.75rem;padding:.5rem .75rem;background:#f8fafc;border-radius:6px;font-size:.75rem;color:#64748b;">
            <strong>Calculation rules:</strong> Inward & Storage = CBM in inventory × rate. Pick & Pack, Consumable, Last Mile = units sold that month × rate. These are deducted from vendor monthly payouts.
        </div>
    </div>
</div>
@endsection
