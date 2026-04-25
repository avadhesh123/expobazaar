@extends('layouts.app')
@section('title', 'Warehouse Charges')
@section('page-title', 'Warehouse Charges')

@section('content')
{{-- KPIs --}}
<div class="grid-kpi" style="grid-template-columns:repeat(3,1fr);">
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Payable (to Warehouse)</div><div class="kpi-value" style="color:#dc2626;">${{ number_format($stats['total_payable'], 2) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;"><div class="kpi-label">Receivable (from Vendors)</div><div class="kpi-value" style="color:#16a34a;">${{ number_format($stats['total_receivable'], 2) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Variance (Payable)</div><div class="kpi-value" style="color:#e8a838;">${{ number_format(abs($stats['total_variance']), 2) }} {{ $stats['total_variance'] > 0 ? '(over)' : ($stats['total_variance'] < 0 ? '(under)' : '') }}</div><div style="font-size:.65rem;color:#94a3b8;">{{ $stats['pending_invoices'] }} pending invoices</div></div>
</div>

{{-- Filters + Run Charges --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('logistics.warehouse-charges') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Month</label><select name="month" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $month==$m?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Year</label><select name="year" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@for($y=date('Y');$y>=date('Y')-2;$y--)<option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>@endfor</select></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Category</label><select name="category" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"><option value="">All</option><option value="payable" {{ $category==='payable'?'selected':'' }}>Payable</option><option value="receivable" {{ $category==='receivable'?'selected':'' }}>Receivable</option></select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Warehouse</label><select name="warehouse_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"><option value="">All</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" {{ request('warehouse_id')==(string)$w->id?'selected':'' }}>{{ $w->name }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('logistics.warehouse-charges') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('logistics.warehouse-charges.variance', ['month'=>$month,'year'=>$year]) }}" class="btn btn-outline btn-sm" style="margin-left:auto;"><i class="fas fa-chart-bar"></i> Variance</a>
            <a href="{{ route('logistics.warehouse-charges.download', ['month'=>$month,'year'=>$year]) }}" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> CSV</a>
            <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('runPanel').style.display=document.getElementById('runPanel').style.display==='none'?'block':'none'"><i class="fas fa-play"></i> Run Charges</button>
        </form>
    </div>
</div>

{{-- Run Charges Panel --}}
<div id="runPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#16a34a;">
        <div class="card-body">
            <div style="font-size:.88rem;font-weight:700;color:#166534;margin-bottom:.5rem;"><i class="fas fa-calculator"></i> Run Monthly Charges</div>
            <form method="POST" action="{{ route('logistics.warehouse-charges.run') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;" onsubmit="return confirm('Calculate all warehouse + vendor charges for this period?')">@csrf
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Warehouse *</label><select name="warehouse_id" required style="padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;">@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Month *</label><select name="month" required style="padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $m==now()->month?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Year *</label><input type="number" name="year" value="{{ date('Y') }}" required min="2024" style="width:80px;padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;"></div>
                <button type="submit" class="btn btn-success"><i class="fas fa-play" style="margin-right:.3rem;"></i> Run</button>
            </form>
            <div style="margin-top:.4rem;font-size:.7rem;color:#64748b;"><i class="fas fa-info-circle"></i> Calculates: warehouse payable (from rate card) + vendor-wise recovery (from vendor rate cards or warehouse defaults). Will not overwrite existing records.</div>
        </div>
    </div>
</div>

{{-- Charges Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-calculator" style="margin-right:.5rem;color:#1e3a5f;"></i> Charges — {{ date('M', mktime(0,0,0,$month,1)) }} {{ $year }}</h3><span style="font-size:.78rem;color:#64748b;">{{ $charges->total() }} records</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Warehouse</th><th>Vendor</th><th>Category</th><th>Calculated</th><th>Actual Invoice</th><th>Variance</th><th>Status</th><th style="width:200px;">Actions</th></tr></thead>
            <tbody>
                @forelse($charges as $c)
                <tr style="{{ $c->charge_category==='receivable'?'background:#f0fdf4;':'' }}">
                    <td style="font-size:.82rem;font-weight:600;">{{ $c->warehouse->name ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $c->vendor->company_name ?? '—' }}<div style="font-size:.62rem;color:#94a3b8;">{{ $c->vendor->vendor_code ?? '' }}</div></td>
                    <td><span class="badge {{ $c->charge_category==='payable'?'badge-danger':'badge-success' }}">{{ ucfirst($c->charge_category) }}</span></td>
                    <td style="font-family:monospace;font-weight:700;">${{ number_format(floatval($c->calculated_amount), 2) }}</td>
                    <td style="font-family:monospace;">
                        @if($c->actual_amount !== null)
                            ${{ number_format(floatval($c->actual_amount), 2) }}
                            @if($c->invoice_number)<div style="font-size:.62rem;color:#94a3b8;">#{{ $c->invoice_number }}</div>@endif
                        @else
                            <span style="color:#94a3b8;">—</span>
                        @endif
                    </td>
                    <td style="font-family:monospace;font-weight:700;color:{{ floatval($c->variance ?? 0) > 0 ? '#dc2626' : (floatval($c->variance ?? 0) < 0 ? '#16a34a' : '#64748b') }};">
                        @if($c->variance !== null)
                            {{ floatval($c->variance) > 0 ? '+' : '' }}${{ number_format(floatval($c->variance), 2) }}
                        @else — @endif
                    </td>
                    <td>
                        @php $sc = ['calculated'=>'badge-warning','invoiced'=>'badge-info','approved'=>'badge-success','deducted'=>'badge-success']; @endphp
                        <span class="badge {{ $sc[$c->status] ?? 'badge-gray' }}">{{ ucfirst($c->status) }}</span>
                    </td>
                    <td>
                        <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('detail-{{ $c->id }}').style.display=document.getElementById('detail-{{ $c->id }}').style.display==='none'?'table-row':'none'" title="Line Items"><i class="fas fa-eye"></i></button>
                            @if($c->charge_category==='payable' && $c->status==='calculated')
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('inv-{{ $c->id }}').style.display=document.getElementById('inv-{{ $c->id }}').style.display==='none'?'table-row':'none'" title="Enter Invoice"><i class="fas fa-file-invoice"></i></button>
                            @endif
                            @if(in_array($c->status, ['calculated','invoiced']))
                            <form method="POST" action="{{ route('logistics.warehouse-charges.approve', $c) }}" style="display:inline;" onsubmit="return confirm('Approve this charge?')">@csrf<button type="submit" class="btn btn-success btn-sm" title="Approve"><i class="fas fa-check"></i></button></form>
                            @endif
                        </div>
                    </td>
                </tr>
                {{-- Line Items Detail Row --}}
                <tr id="detail-{{ $c->id }}" style="display:none;background:#f8fafc;">
                    <td colspan="8" style="padding:.75rem;">
                        <div style="font-size:.72rem;font-weight:700;color:#64748b;margin-bottom:.3rem;">CHARGE BREAKDOWN</div>
                        <table style="width:100%;border-collapse:collapse;font-size:.78rem;">
                            <tr style="background:#e8ecf1;"><th style="padding:.3rem .5rem;text-align:left;">Charge</th><th style="text-align:left;">UOM</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Rate</th><th style="text-align:right;">Amount</th></tr>
                            @foreach($c->items as $item)
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:.3rem .5rem;">{{ $item->charge_label }}</td>
                                <td>{{ $item->uom }}</td>
                                <td style="text-align:right;font-family:monospace;">{{ number_format(floatval($item->quantity), 2) }}</td>
                                <td style="text-align:right;font-family:monospace;">${{ number_format(floatval($item->rate), 4) }}</td>
                                <td style="text-align:right;font-family:monospace;font-weight:600;">${{ number_format(floatval($item->amount), 2) }}</td>
                            </tr>
                            @endforeach
                            <tr style="background:#f0f4f8;font-weight:700;"><td colspan="4" style="padding:.3rem .5rem;">Total</td><td style="text-align:right;font-family:monospace;">${{ number_format(floatval($c->calculated_amount), 2) }}</td></tr>
                        </table>
                    </td>
                </tr>
                {{-- Invoice Upload Row --}}
                @if($c->charge_category==='payable' && $c->status==='calculated')
                <tr id="inv-{{ $c->id }}" style="display:none;background:#eff6ff;">
                    <td colspan="8" style="padding:.75rem;">
                        <form method="POST" action="{{ route('logistics.warehouse-charges.invoice', $c) }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">@csrf
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Actual Amount *</label><input type="number" step="0.01" name="actual_amount" required placeholder="0.00" style="width:110px;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:monospace;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Invoice # *</label><input type="text" name="invoice_number" required placeholder="INV-001" style="width:120px;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Invoice Date *</label><input type="date" name="invoice_date" required value="{{ date('Y-m-d') }}" style="padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Invoice File</label><input type="file" name="invoice_file" accept=".pdf,.jpg,.png" style="font-size:.75rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Variance Remark</label><input type="text" name="variance_comment" placeholder="Optional" style="width:140px;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Submit Invoice</button>
                        </form>
                    </td>
                </tr>
                @endif
                @empty
                <tr><td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-calculator" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No charges for this period. Click "Run Charges" to calculate.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($charges->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $charges->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
