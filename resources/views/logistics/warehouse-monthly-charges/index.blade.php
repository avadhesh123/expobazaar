@extends('layouts.app')
@section('title', 'Warehouse Charges & Reconciliation')
@section('page-title', 'Warehouse Charges & Reconciliation')

@section('content')
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

<div id="runPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#16a34a;">
        <div class="card-body">
            <form method="POST" action="{{ route('logistics.warehouse-monthly-charges.run') }}" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">@csrf
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Warehouse *</label><select name="warehouse_id" required style="padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;">@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }} ({{ $w->company_code }})</option>@endforeach</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Month</label><select name="month" required style="padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $m==now()->subMonth()->month?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Year</label><input type="number" name="year" value="{{ date('Y') }}" required min="2024" style="width:80px;padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;"></div>
                <button type="submit" class="btn btn-success" onclick="return confirm('Calculate and save?')"><i class="fas fa-calculator"></i> Calculate & Save</button>
                <!-- <button type="submit" name="dry_run" value="1" class="btn btn-outline"><i class="fas fa-eye"></i> Preview</button> -->
            </form>
        </div>
    </div>
</div>

@php $chargeHeads = ['unloading'=>'Unloading','putaway'=>'Putaway','storage'=>'Storage','order_processing'=>'Order Processing','pick_pack'=>'Pick & Pack','return_inward'=>'Return Inward']; @endphp

@forelse($charges as $c)
@php $hasInvoice = $c->actual_total !== null; $snap = $c->calculation_snapshot ?? []; $strategy = $snap['strategy'] ?? 'unknown'; @endphp
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <h3><i class="fas fa-warehouse" style="margin-right:.5rem;"></i> {{ $c->warehouse->name ?? '—' }} — {{ date('M',mktime(0,0,0,$c->charge_month,1)) }} {{ $c->charge_year }}</h3>
        <div style="display:flex;gap:.4rem;align-items:center;">
            <span class="badge badge-info" style="font-size:.6rem;text-transform:uppercase;">{{ $strategy }}</span>
            @php $stc = ['calculated'=>'badge-warning','invoice_entered'=>'badge-info','under_review'=>'badge-info','approved'=>'badge-success']; @endphp
            <span class="badge {{ $stc[$c->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$c->status)) }}</span>
        </div>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="margin:0;font-size:.78rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th>Charge Head</th>
                    <th style="text-align:right;">Expected</th>
                    <th style="text-align:right;">Actual</th>
                    <th style="text-align:right;">Variance</th>
                    <th style="text-align:right;">%</th>
                    <th>Status</th>
                    <th>Explanation</th>
                </tr>
            </thead>
            <tbody>
                @foreach($chargeHeads as $key => $label)
                @php $exp=floatval($c->{'expected_'.$key}??0); $act=$hasInvoice?floatval($c->{'actual_'.$key}??0):null; $var=$hasInvoice?round($act-$exp,2):null; $pct=($hasInvoice&&$exp>0)?round(($var/$exp)*100,1):null; $over=$hasInvoice&&$var!==null&&$exp>0&&abs($var)>($exp*0.1); $expl=($c->variance_explanations??[])[$key]??''; @endphp
                <tr style="{{ $over?'background:#fef2f2;':'' }}">
                    <td style="font-weight:600;">{{ $label }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($exp,2) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $act!==null?$activeCurrencySymbol.number_format($act,2):'—' }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:700;color:{{ ($var??0)>0?'#dc2626':(($var??0)<0?'#16a34a':'#64748b') }};">{{ $var!==null?($var>0?'+':'').$activeCurrencySymbol.number_format($var,2):'—' }}</td>
                    <td style="text-align:right;font-size:.75rem;color:{{ $over?'#dc2626':'#64748b' }};">{{ $pct!==null?($pct>0?'+':'').$pct.'%':'—' }}</td>
                    <td>@if($hasInvoice)<span class="badge {{ $over?'badge-danger':'badge-success' }}" style="font-size:.6rem;">{{ $over?'OVER':'OK' }}</span>@endif</td>
                    <td style="font-size:.72rem;color:#64748b;">{{ $expl?ucwords(str_replace('_',' ',$expl)):'—' }}</td>
                </tr>
                @endforeach
                @if($hasInvoice && floatval($c->actual_other??0)>0)
                <tr style="background:#fefce8;">
                    <td style="font-weight:600;">Other</td>
                    <td style="text-align:right;">—</td>
                    <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($c->actual_other),2) }}</td>
                    <td style="text-align:right;font-family:monospace;color:#e8a838;">+{{ $activeCurrencySymbol }}{{ number_format(floatval($c->actual_other),2) }}</td>
                    <td>—</td>
                    <td><span class="badge badge-warning" style="font-size:.6rem;">Review</span></td>
                    <td>—</td>
                </tr>
                @endif
                <tr style="background:#f0f4f8;font-weight:800;">
                    <td>TOTAL</td>
                    <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($c->expected_total),2) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $hasInvoice?$activeCurrencySymbol.number_format(floatval($c->actual_total),2):'—' }}</td>@php $vt=$hasInvoice?floatval($c->actual_total)-floatval($c->expected_total):null; @endphp<td style="text-align:right;font-family:monospace;color:{{ ($vt??0)>0?'#dc2626':'#16a34a' }};">{{ $vt!==null?($vt>0?'+':'').$activeCurrencySymbol.number_format($vt,2):'—' }}</td>
                    <td colspan="3"></td>
                </tr>
            </tbody>
        </table>

        <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;display:flex;gap:.5rem;flex-wrap:wrap;">
            @if($c->status==='calculated')<button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('inv-{{ $c->id }}').style.display=document.getElementById('inv-{{ $c->id }}').style.display==='none'?'block':'none'"><i class="fas fa-file-invoice"></i> Enter Invoice</button>@endif
            @if($c->status==='invoice_entered')<button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('expl-{{ $c->id }}').style.display=document.getElementById('expl-{{ $c->id }}').style.display==='none'?'block':'none'"><i class="fas fa-edit"></i> Explanations</button>@endif
            @if(in_array($c->status,['under_review','invoice_entered']))<form method="POST" action="{{ route('logistics.warehouse-monthly-charges.approve', $c) }}" style="display:inline;" onsubmit="return confirm('Approve?')">@csrf<button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button></form>@endif
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('drill-{{ $c->id }}').style.display=document.getElementById('drill-{{ $c->id }}').style.display==='none'?'block':'none'"><i class="fas fa-search"></i> Breakdown</button>
        </div>

        @if($c->status==='calculated')
        <div id="inv-{{ $c->id }}" style="display:none;padding:1rem 1.4rem;background:#eff6ff;border-top:1px solid #bfdbfe;">
            <div style="font-weight:700;color:#1e40af;margin-bottom:.5rem;">Enter Actual Warehouse Invoice</div>
            <form method="POST" action="{{ route('logistics.warehouse-monthly-charges.invoice', $c) }}" enctype="multipart/form-data">
                <@csrf
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.5rem;align-items:flex-end;margin-bottom:.5rem;">
                    <div><label style="font-size:.65rem;font-weight:600;">Invoice # *</label><input type="text" name="invoice_number" required style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                    <div><label style="font-size:.65rem;font-weight:600;">Invoice Date *</label><input type="date" name="invoice_date" required value="{{ date('Y-m-d') }}" style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                    @foreach($chargeHeads as $key => $label)
                    <div><label style="font-size:.65rem;font-weight:600;">{{ $label }} *</label><input type="number" step="0.01" name="actual_{{ $key }}" required value="0" style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:monospace;">
                        <div style="font-size:.55rem;color:#94a3b8;">Exp: {{ $activeCurrencySymbol }}{{ number_format(floatval($c->{'expected_'.$key}??0),2) }}</div>
                    </div>
                    @endforeach
                    <div><label style="font-size:.65rem;font-weight:600;">Other</label><input type="number" step="0.01" name="actual_other" value="0" style="width:100%;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:monospace;"></div>
                    <div><label style="font-size:.65rem;font-weight:600;">Invoice PDF</label><input type="file" name="invoice_file" accept=".pdf,.jpg,.png" style="font-size:.72rem;"></div>
                    <div><button type="submit" class="btn btn-primary btn-sm" style="width:100%;"><i class="fas fa-save"></i> Save</button></div>
        </div>
        </form>
    </div>
    @endif

    @if($c->status==='invoice_entered')
    <div id="expl-{{ $c->id }}" style="display:none;padding:1rem 1.4rem;background:#fefce8;border-top:1px solid #fde68a;">
        <div style="font-weight:700;color:#854d0e;margin-bottom:.5rem;">Variance Explanations</div>
        <form method="POST" action="{{ route('logistics.warehouse-monthly-charges.explanations', $c) }}">@csrf
            @foreach($chargeHeads as $key => $label)
            @php $exp=floatval($c->{'expected_'.$key}??0); $act=floatval($c->{'actual_'.$key}??0); $isOver=$exp>0&&abs($act-$exp)>($exp*0.1); @endphp
            @if($isOver)
            <div style="margin-bottom:.4rem;"><label style="font-size:.7rem;font-weight:600;color:#dc2626;">{{ $label }} — {{ $activeCurrencySymbol }}{{ number_format(abs($act-$exp),2) }} variance</label><select name="explanations[{{ $key }}]" required style="width:100%;padding:.3rem .5rem;border:1px solid #fca5a5;border-radius:6px;font-size:.82rem;">
                    <option value="">Select...</option>
                    <option value="rate_difference">Rate difference</option>
                    <option value="volume_difference">Volume difference</option>
                    <option value="adhoc_charges">Ad-hoc charges</option>
                    <option value="data_entry_error">Data entry error</option>
                    <option value="timing_difference">Timing difference</option>
                    <option value="other">Other</option>
                </select></div>
            @endif
            @endforeach
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-save"></i> Save</button>
        </form>
    </div>
    @endif

    <div id="drill-{{ $c->id }}" style="display:none;padding:1rem 1.4rem;background:#f8fafc;border-top:1px solid #e8ecf1;">
        @php $details=$snap['details']??[]; @endphp
        @if(empty($details))
        <div style="text-align:center;color:#94a3b8;padding:1rem;">No breakdown data. Recalculate to generate.</div>
        @else
        <div style="display:flex;justify-content:space-between;margin-bottom:.75rem;"><span style="font-weight:700;color:#64748b;font-size:.82rem;">Charge Breakdown</span><span class="badge badge-info" style="font-size:.6rem;text-transform:uppercase;">{{ $strategy }}</span></div>
        <table style="width:100%;border-collapse:collapse;font-size:.78rem;">
            <tr style="background:#e8ecf1;">
                <td colspan="4" style="padding:.4rem .6rem;font-weight:700;">Unloading</td>
            </tr>
            @forelse($details['unloading']['breakdown']??[] as $b)
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:.3rem .6rem;font-family:monospace;font-size:.72rem;">{{ $b['grn_no'] }}</td>
                <td><span class="badge {{ $b['type']==='FCL'?'badge-success':($b['type']==='AIR'?'badge-info':'badge-warning') }}" style="font-size:.55rem;">{{ $b['type'] }}</span></td>
                <td style="color:#64748b;">{{ $b['qty'] }} {{ $b['unit']??'' }} x {{ $activeCurrencySymbol }}{{ number_format($b['rate']??0,2) }}</td>
                <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $activeCurrencySymbol }}{{ number_format($b['charge']??0,2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="padding:.3rem .6rem;color:#94a3b8;">No unloading.</td>
            </tr>
            @endforelse
            <tr style="border-bottom:2px solid #e2e8f0;">
                <td colspan="3" style="padding:.3rem .6rem;text-align:right;font-weight:700;">Subtotal:</td>
                <td style="text-align:right;font-family:monospace;font-weight:700;">{{ $activeCurrencySymbol }}{{ number_format($details['unloading']['charge']??0,2) }}</td>
            </tr>

            <tr style="background:#e8ecf1;">
                <td colspan="4" style="padding:.4rem .6rem;font-weight:700;">Putaway</td>
            </tr>
            <tr style="border-bottom:2px solid #e2e8f0;">
                <td colspan="3" style="padding:.3rem .6rem;color:#64748b;">{{ $details['putaway']['total_cartons']??0 }} cartons x {{ $activeCurrencySymbol }}{{ number_format($details['putaway']['rate']??0,2) }}</td>
                <td style="text-align:right;font-family:monospace;font-weight:700;">{{ $activeCurrencySymbol }}{{ number_format($details['putaway']['charge']??0,2) }}</td>
            </tr>

            <tr style="background:#e8ecf1;">
                <td colspan="4" style="padding:.4rem .6rem;font-weight:700;">Storage</td>
            </tr>
            <tr style="border-bottom:2px solid #e2e8f0;">
                <td colspan="3" style="padding:.3rem .6rem;color:#64748b;">@if($strategy==='usa'){{ number_format($details['storage']['total_cft']??0,2) }} CFT x {{ $activeCurrencySymbol }}{{ number_format($details['storage']['rate']??0,2) }}/CFT @else Avg {{ $details['storage']['avg_pallets']??0 }} pallets x {{ $activeCurrencySymbol }}{{ number_format($details['storage']['rate']??0,2) }}/pallet ({{ $details['storage']['method']??'' }})@endif</td>
                <td style="text-align:right;font-family:monospace;font-weight:700;">{{ $activeCurrencySymbol }}{{ number_format($details['storage']['charge']??0,2) }}</td>
            </tr>

            <tr style="background:#e8ecf1;">
                <td colspan="4" style="padding:.4rem .6rem;font-weight:700;">Order Processing</td>
            </tr>
            @if($strategy==='usa')
            @php $ful=$details['fulfillment']??[]; @endphp
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:.3rem .6rem;">≤ {{ $ful['threshold']??'—' }} qty</td>
                <td colspan="2" style="color:#64748b;">{{ $ful['small_qty']??0 }} qty x {{ $activeCurrencySymbol }}{{ number_format($ful['lower_rate']??0,2) }}</td>
                <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($ful['small_charge']??0,2) }}</td>
            </tr>
            <tr style="border-bottom:2px solid #e2e8f0;">
                <td style="padding:.3rem .6rem;">> {{ $ful['threshold']??'—' }} qty</td>
                <td colspan="2" style="color:#64748b;">{{ $ful['large_qty']??0 }} qty x {{ $activeCurrencySymbol }}{{ number_format($ful['upper_rate']??0,2) }}</td>
                <td style="text-align:right;font-family:monospace;font-weight:700;">{{ $activeCurrencySymbol }}{{ number_format($ful['charge']??0,2) }}</td>
            </tr>
            @else
            @php $ful=$details['fulfillment']??[]; @endphp
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:.3rem .6rem;">Palletize</td>
                <td colspan="2" style="color:#64748b;">{{ $ful['palletize_qty']??0 }} qty x {{ $activeCurrencySymbol }}{{ number_format($ful['palletize_rate']??0,2) }}</td>
                <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($ful['palletize_charge']??0,2) }}</td>
            </tr>
            <tr style="border-bottom:2px solid #e2e8f0;">
                <td style="padding:.3rem .6rem;">Non-Palletize</td>
                <td colspan="2" style="color:#64748b;">{{ $ful['non_palletize_count']??0 }} orders x {{ $activeCurrencySymbol }}{{ number_format($ful['non_palletize_rate']??0,2) }}</td>
                <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($ful['non_palletize_charge']??0,2) }}</td>
            </tr>

            <tr>
                <td colspan="3" style="padding:.3rem .6rem;text-align:right;font-weight:700;">Subtotal:</td>
                <td style="text-align:right;font-family:monospace;font-weight:700;">{{ $activeCurrencySymbol }}{{ number_format($ful['charge']??0,2) }}</td>
            </tr>
            @endif

            <tr style="background:#e8ecf1;">
                <td colspan="4" style="padding:.4rem .6rem;font-weight:700;">Pick & Pack</td>
            </tr>
            @if($strategy==='usa')
            @php $pp=$details['pickPack']??[]; @endphp
            <tr style="border-bottom:2px solid #e2e8f0;">
                <td colspan="3" style="padding:.3rem .6rem;color:#64748b;">{{ $pp['total_units']??0 }} units x {{ $activeCurrencySymbol }}{{ number_format($pp['rate']??0,2) }}/unit</td>
                <td style="text-align:right;font-family:monospace;font-weight:700;">{{ $activeCurrencySymbol }}{{ number_format($pp['charge']??0,2) }}</td>
            </tr>
            @else
            @php $pp=$details['pickPack']??[]; @endphp
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:.3rem .6rem;">Palletize</td>
                <td colspan="2" style="color:#64748b;">{{ $pp['palletize_qty']??0 }} qty x {{ $activeCurrencySymbol }}{{ number_format($pp['palletize_rate']??0,2) }}</td>
                <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($pp['palletize_charge']??0,2) }}</td>
            </tr>
            <tr style="border-bottom:2px solid #e2e8f0;">
                <td style="padding:.3rem .6rem;">Non-Palletize</td>
                <td colspan="2" style="color:#64748b;">{{ $pp['non_palletize_count']??0 }} orders x {{ $activeCurrencySymbol }}{{ number_format($pp['non_palletize_rate']??0,2) }}</td>
                <td style="text-align:right;font-family:monospace;font-weight:700;">{{ $activeCurrencySymbol }}{{ number_format($pp['charge']??0,2) }}</td>
            </tr>
            @endif

            <tr style="background:#e8ecf1;">
                <td colspan="4" style="padding:.4rem .6rem;font-weight:700;">Return Inward</td>
            </tr>
            @php $ri=$details['returnInward']??[]; @endphp
            <tr style="border-bottom:2px solid #e2e8f0;">
                <td colspan="3" style="padding:.3rem .6rem;color:#64748b;">{{ $ri['return_qty']??0 }} units x {{ $activeCurrencySymbol }}{{ number_format($ri['rate']??0,2) }}/unit</td>
                <td style="text-align:right;font-family:monospace;font-weight:700;">{{ $activeCurrencySymbol }}{{ number_format($ri['charge']??0,2) }}</td>
            </tr>

            <tr style="background:#1e3a5f;">
                <td colspan="3" style="padding:.5rem .6rem;font-weight:800;color:#fff;text-align:right;">GRAND TOTAL</td>
                <td style="text-align:right;font-family:monospace;font-weight:800;color:#fff;font-size:.9rem;">{{ $activeCurrencySymbol }}{{ number_format($c->expected_total??0,2) }}</td>
            </tr>
        </table>
        @endif
    </div>
</div>
</div>
@empty
<div class="card">
    <div class="card-body" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-receipt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No charges for this period.</div>
</div>
@endforelse
@endsection