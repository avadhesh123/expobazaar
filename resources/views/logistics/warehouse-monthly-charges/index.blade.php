@extends('layouts.app')
@section('title', 'Warehouse Charges & Reconciliation')
@section('page-title', 'Warehouse Charges & Reconciliation')

@section('content')
{{-- Filters + Run --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('logistics.warehouse-monthly-charges') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Month</label><select name="month" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $month==$m?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
            <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Year</label><select name="year" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@for($y=date('Y');$y>=date('Y')-2;$y--)<option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>@endfor</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <button type="button" class="btn btn-success btn-sm" style="margin-left:auto;" onclick="document.getElementById('runPanel').style.display=document.getElementById('runPanel').style.display==='none'?'block':'none'"><i class="fas fa-play"></i> Calculate Charges</button>
        </form>
    </div>
</div>

{{-- Run Panel --}}
<div id="runPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#16a34a;">
        <div class="card-body">
            <form method="POST" action="{{ route('logistics.warehouse-monthly-charges.run') }}" style="display:flex;gap:.75rem;align-items:flex-end;" onsubmit="return confirm('Calculate expected warehouse charges for this period?')">@csrf
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Warehouse *</label><select name="warehouse_id" required style="padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;">@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Month</label><select name="month" required style="padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $m==now()->subMonth()->month?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Year</label><input type="number" name="year" value="{{ date('Y') }}" required min="2024" style="width:80px;padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;"></div>
                <button type="submit" class="btn btn-success"><i class="fas fa-calculator"></i> Calculate</button>
            </form>
        </div>
    </div>
</div>

@forelse($charges as $c)
@php $s = $c->getCurrencySymbol(); $hasInvoice = $c->actual_total !== null; @endphp
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <h3><i class="fas fa-warehouse" style="margin-right:.5rem;"></i> {{ $c->warehouse->name ?? '—' }} — {{ $c->period }}</h3>
        <div style="display:flex;gap:.4rem;align-items:center;">
            @php $stc = ['calculated'=>'badge-warning','invoice_entered'=>'badge-info','under_review'=>'badge-info','approved'=>'badge-success','locked'=>'badge-success']; @endphp
            <span class="badge {{ $stc[$c->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$c->status)) }}</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">

        {{-- Variance Table --}}
        <table class="data-table" style="margin:0;">
            <thead><tr><th>Charge Head</th><th style="text-align:right;">Expected (System)</th><th style="text-align:right;">Actual (Invoice)</th><th style="text-align:right;">Variance</th><th style="text-align:right;">%</th><th>Status</th><th>Explanation</th></tr></thead>
            <tbody>
                @foreach(['inward'=>'Inward Handling','storage'=>'Storage','fulfillment'=>'Fulfillment','pick_pack'=>'Pick & Pack'] as $key=>$label)
                @php
                    $exp = floatval($c->{'expected_'.$key});
                    $act = $hasInvoice ? floatval($c->{'actual_'.$key} ?? 0) : null;
                    $var = $hasInvoice ? floatval($c->{'variance_'.$key} ?? 0) : null;
                    $pct = ($hasInvoice && $exp > 0) ? round(($var / $exp) * 100, 1) : null;
                    $over = $hasInvoice ? $c->isOverLimit($key) : false;
                    $expl = ($c->variance_explanations ?? [])[$key] ?? '';
                @endphp
                <tr style="{{ $over ? 'background:#fef2f2;' : '' }}">
                    <td style="font-weight:600;">{{ $label }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format($exp, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $act !== null ? $s.number_format($act, 2) : '—' }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:700;color:{{ $var > 0 ? '#dc2626' : ($var < 0 ? '#16a34a' : '#64748b') }};">{{ $var !== null ? ($var>0?'+':'').$s.number_format($var,2) : '—' }}</td>
                    <td style="text-align:right;font-size:.78rem;color:{{ $over?'#dc2626':'#64748b' }};">{{ $pct !== null ? ($pct>0?'+':'').$pct.'%' : '—' }}</td>
                    <td>@if($hasInvoice)<span class="badge {{ $over?'badge-danger':'badge-success' }}">{{ $over?'OVER LIMIT':'Within Limit' }}</span>@endif</td>
                    <td style="font-size:.72rem;color:#64748b;">{{ $expl ?: '—' }}</td>
                </tr>
                @endforeach
                @if($hasInvoice && floatval($c->actual_other ?? 0) > 0)
                <tr style="background:#fefce8;"><td style="font-weight:600;">Other Charges</td><td style="text-align:right;">—</td><td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($c->actual_other),2) }}</td><td style="text-align:right;font-family:monospace;color:#e8a838;">{{ $s }}{{ number_format(floatval($c->actual_other),2) }}</td><td>—</td><td><span class="badge badge-warning">For Review</span></td><td>—</td></tr>
                @endif
                <tr style="background:#f0f4f8;font-weight:800;">
                    <td>TOTAL</td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($c->expected_total), 2) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $hasInvoice ? $s.number_format(floatval($c->actual_total),2) : '—' }}</td>
                    <td style="text-align:right;font-family:monospace;color:{{ floatval($c->variance_total??0)>0?'#dc2626':'#16a34a' }};">{{ $hasInvoice ? ($c->variance_total>0?'+':'').$s.number_format(floatval($c->variance_total),2) : '—' }}</td>
                    <td colspan="3"></td>
                </tr>
            </tbody>
        </table>

        {{-- Actions --}}
        <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;display:flex;gap:.5rem;flex-wrap:wrap;">
            @if($c->status === 'calculated')
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('inv-{{ $c->id }}').style.display=document.getElementById('inv-{{ $c->id }}').style.display==='none'?'block':'none'"><i class="fas fa-file-invoice"></i> Enter Invoice</button>
            @endif
            @if($c->status === 'invoice_entered')
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('expl-{{ $c->id }}').style.display=document.getElementById('expl-{{ $c->id }}').style.display==='none'?'block':'none'"><i class="fas fa-edit"></i> Add Explanations</button>
            @endif
            @if(in_array($c->status, ['under_review', 'invoice_entered']))
            <form method="POST" action="{{ route('logistics.warehouse-monthly-charges.approve', $c) }}" style="display:inline;" onsubmit="return confirm('Approve and lock this reconciliation?')">@csrf<button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve & Lock</button></form>
            @endif
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('drill-{{ $c->id }}').style.display=document.getElementById('drill-{{ $c->id }}').style.display==='none'?'block':'none'"><i class="fas fa-search"></i> GRN Drill-Down</button>
        </div>

        {{-- Invoice Entry Form --}}
        @if($c->status === 'calculated')
        <div id="inv-{{ $c->id }}" style="display:none;padding:1rem 1.4rem;background:#eff6ff;border-top:1px solid #bfdbfe;">
            <div style="font-weight:700;color:#1e40af;margin-bottom:.5rem;">Enter Actual Warehouse Invoice</div>
            <form method="POST" action="{{ route('logistics.warehouse-monthly-charges.invoice', $c) }}" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.5rem;align-items:flex-end;">@csrf
                <div><label style="font-size:.65rem;font-weight:600;">Invoice # *</label><input type="text" name="invoice_number" required style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                <div><label style="font-size:.65rem;font-weight:600;">Invoice Date *</label><input type="date" name="invoice_date" required value="{{ date('Y-m-d') }}" style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                <div><label style="font-size:.65rem;font-weight:600;">Inward *</label><input type="number" step="0.01" name="actual_inward" required placeholder="0.00" style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.65rem;font-weight:600;">Storage *</label><input type="number" step="0.01" name="actual_storage" required placeholder="0.00" style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.65rem;font-weight:600;">Fulfillment *</label><input type="number" step="0.01" name="actual_fulfillment" required placeholder="0.00" style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.65rem;font-weight:600;">Pick & Pack *</label><input type="number" step="0.01" name="actual_pick_pack" required placeholder="0.00" style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.65rem;font-weight:600;">Other</label><input type="number" step="0.01" name="actual_other" value="0" style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.65rem;font-weight:600;">Invoice PDF</label><input type="file" name="invoice_file" accept=".pdf,.jpg,.png" style="font-size:.72rem;"></div>
                <div><button type="submit" class="btn btn-primary btn-sm" style="width:100%;"><i class="fas fa-save"></i> Save Invoice</button></div>
            </form>
        </div>
        @endif

        {{-- Variance Explanations Form --}}
        @if($c->status === 'invoice_entered')
        <div id="expl-{{ $c->id }}" style="display:none;padding:1rem 1.4rem;background:#fefce8;border-top:1px solid #fde68a;">
            <div style="font-weight:700;color:#854d0e;margin-bottom:.5rem;">Variance Explanations (required for over-limit items)</div>
            <form method="POST" action="{{ route('logistics.warehouse-monthly-charges.explanations', $c) }}">@csrf
                @foreach(['inward'=>'Inward','storage'=>'Storage','fulfillment'=>'Fulfillment','pick_pack'=>'Pick & Pack'] as $key=>$label)
                @if($c->isOverLimit($key))
                <div style="margin-bottom:.4rem;">
                    <label style="font-size:.7rem;font-weight:600;color:#dc2626;">{{ $label }} — OVER LIMIT *</label>
                    <select name="explanations[{{ $key }}]" required style="width:100%;padding:.3rem .5rem;border:1px solid #fca5a5;border-radius:6px;font-size:.82rem;">
                        <option value="">Select reason...</option>
                        <option value="rate_difference" {{ ($c->variance_explanations[$key] ?? '')==='rate_difference'?'selected':'' }}>Rate difference</option>
                        <option value="volume_difference" {{ ($c->variance_explanations[$key] ?? '')==='volume_difference'?'selected':'' }}>Volume difference</option>
                        <option value="adhoc_charges" {{ ($c->variance_explanations[$key] ?? '')==='adhoc_charges'?'selected':'' }}>Ad-hoc charges</option>
                        <option value="data_entry_error" {{ ($c->variance_explanations[$key] ?? '')==='data_entry_error'?'selected':'' }}>Data entry error</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                @endif
                @endforeach
                <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-save"></i> Save Explanations</button>
            </form>
        </div>
        @endif

        {{-- GRN Drill-Down --}}
        <div id="drill-{{ $c->id }}" style="display:none;padding:1rem 1.4rem;background:#f8fafc;border-top:1px solid #e8ecf1;">
            <div style="font-weight:700;color:#64748b;margin-bottom:.5rem;font-size:.78rem;">GRN-Level Breakdown ({{ $c->grnDetails->count() }} GRNs)</div>
            <table style="width:100%;border-collapse:collapse;font-size:.78rem;">
                <tr style="background:#e8ecf1;"><th style="padding:.3rem .5rem;text-align:left;">GRN</th><th style="text-align:right;">Inward</th><th style="text-align:right;">Storage</th><th style="text-align:right;">Fulfillment</th><th style="text-align:right;">P&P</th><th style="text-align:right;">Total</th></tr>
                @foreach($c->grnDetails as $d)
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:.3rem .5rem;font-family:monospace;">{{ $d->grn->grn_number ?? '#'.$d->grn_id }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($d->inward_charge),2) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($d->storage_charge),2) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($d->fulfillment_charge),2) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($d->pick_pack_charge),2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $s }}{{ number_format(floatval($d->total_charge),2) }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>
</div>
@empty
<div class="card"><div class="card-body" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-receipt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No charges for this period. Click "Calculate Charges" to run.</div></div>
@endforelse
@endsection
