@extends('layouts.app')
@section('title', 'Vendor Payouts')
@section('page-title', 'Vendor Payout Management')

@section('content')
{{-- FILTERS --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('finance.payouts') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="min-width:110px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label>
                <select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option>
                    <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option>
                    <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option>
                </select>
            </div>
            <div style="min-width:120px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @foreach(['draft','calculated','approved','payment_pending','paid','invoice_received'] as $s)
                        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:80px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Month</label>
                <select name="month" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ request('month')==(string)$m?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor
                </select>
            </div>
            <div style="min-width:80px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Year</label>
                <select name="year" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @for($y=date('Y');$y>=date('Y')-2;$y--)<option value="{{ $y }}" {{ request('year')==(string)$y?'selected':'' }}>{{ $y }}</option>@endfor
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('finance.payouts') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>

            {{-- Calculate Payout Button --}}
            <div style="margin-left:auto;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('calcPanel').style.display=document.getElementById('calcPanel').style.display==='none'?'block':'none'">
                    <i class="fas fa-calculator"></i> Calculate Payout
                </button>
            </div>
        </form>
    </div>
</div>

{{-- CALCULATE PAYOUT PANEL --}}
<div id="calcPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#e8a838;">
        <div class="card-body">
            <div style="font-size:.88rem;font-weight:700;color:#0d1b2a;margin-bottom:.75rem;">Calculate Monthly Vendor Payout</div>
            <form method="POST" action="{{ route('finance.payouts.calculate') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
                @csrf
                <div class="form-group" style="margin-bottom:0;min-width:200px;">
                    <label>Vendor <span style="color:#dc2626;">*</span></label>
                    <select name="vendor_id" required style="padding:.4rem .5rem;border:1px solid #fde68a;border-radius:8px;font-size:.82rem;font-family:inherit;">
                        <option value="">Select vendor...</option>
                        @foreach(\App\Models\Vendor::active()->orderBy('company_name')->get() as $v)
                            <option value="{{ $v->id }}">{{ $v->company_name }} ({{ $v->vendor_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Month <span style="color:#dc2626;">*</span></label>
                    <select name="month" required style="padding:.4rem .5rem;border:1px solid #fde68a;border-radius:8px;font-size:.82rem;font-family:inherit;">
                        @for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $m==date('n')?'selected':'' }}>{{ date('F',mktime(0,0,0,$m,1)) }}</option>@endfor
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Year <span style="color:#dc2626;">*</span></label>
                    <input type="number" name="year" value="{{ date('Y') }}" required min="2020" max="2030" style="width:80px;padding:.4rem .5rem;border:1px solid #fde68a;border-radius:8px;font-size:.82rem;">
                </div>
                <button type="submit" class="btn btn-secondary"><i class="fas fa-calculator" style="margin-right:.3rem;"></i> Calculate</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('calcPanel').style.display='none'">Cancel</button>
            </form>
        </div>
    </div>
</div>

{{-- PAYOUTS TABLE --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-money-check-alt" style="margin-right:.5rem;color:#2d6a4f;"></i> Payouts</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr><th>Vendor</th><th>Period</th><th>Sales</th><th>Storage</th><th>Inward</th><th>Logistics</th><th>Platform Ded.</th><th>Chargebacks</th><th>Net Payout</th><th>Status</th><th style="width:140px;">Actions</th></tr>
            </thead>
            <tbody>
                @forelse($payouts as $p)
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:.82rem;">{{ $p->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $p->vendor->vendor_code ?? '' }} · {{ $p->company_code }}</div>
                    </td>
                    <td style="font-weight:600;font-size:.85rem;">{{ date('M', mktime(0,0,0,$p->payout_month,1)) }} {{ $p->payout_year }}</td>
                    <td style="font-family:monospace;color:#166534;font-weight:600;">${{ number_format($p->total_sales, 2) }}</td>
                    <td style="font-family:monospace;font-size:.8rem;color:#dc2626;">-${{ number_format($p->total_storage_charges, 2) }}</td>
                    <td style="font-family:monospace;font-size:.8rem;color:#dc2626;">-${{ number_format($p->total_inward_charges, 2) }}</td>
                    <td style="font-family:monospace;font-size:.8rem;color:#dc2626;">-${{ number_format($p->total_logistics_charges, 2) }}</td>
                    <td style="font-family:monospace;font-size:.8rem;color:#dc2626;">-${{ number_format($p->total_platform_deductions, 2) }}</td>
                    <td style="font-family:monospace;font-size:.8rem;color:#991b1b;">-${{ number_format($p->total_chargebacks, 2) }}</td>
                    <td style="font-family:monospace;font-weight:800;font-size:.9rem;color:{{ $p->net_payout >= 0 ? '#166534' : '#dc2626' }};">${{ number_format($p->net_payout, 2) }}</td>
                    <td>
                        @php $sc = ['draft'=>'badge-gray','calculated'=>'badge-info','approved'=>'badge-info','payment_pending'=>'badge-warning','paid'=>'badge-success','invoice_received'=>'badge-success']; @endphp
                        <span class="badge {{ $sc[$p->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$p->status)) }}</span>
                        @if($p->payment_date)<div style="font-size:.65rem;color:#94a3b8;margin-top:.15rem;">Paid: {{ $p->payment_date->format('d M Y') }}</div>@endif
                    </td>
                    <td>
                        @if(in_array($p->status, ['calculated', 'approved', 'payment_pending']))
                            <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('payForm{{ $p->id }}').style.display=document.getElementById('payForm{{ $p->id }}').style.display==='none'?'table-row':'none'" title="Process Payment">
                                <i class="fas fa-credit-card"></i> Pay
                            </button>
                        @elseif($p->status === 'paid')
                            @if($p->payment_advice_file)
                                <a href="{{ asset('storage/' . $p->payment_advice_file) }}" class="btn btn-outline btn-sm" target="_blank"><i class="fas fa-download"></i> Advice</a>
                            @endif
                        @endif
                    </td>
                </tr>

                {{-- Process Payment Inline Row --}}
                @if(in_array($p->status, ['calculated', 'approved', 'payment_pending']))
                <tr id="payForm{{ $p->id }}" style="display:none;background:#f0fdf4;">
                    <td colspan="11" style="padding:1rem;">
                        <form method="POST" action="{{ route('finance.payouts.process', $p) }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
                            @csrf
                            <div><label style="font-size:.68rem;font-weight:600;color:#166534;">Payment Date *</label><input type="date" name="payment_date" required value="{{ date('Y-m-d') }}" style="padding:.35rem .5rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.68rem;font-weight:600;color:#166534;">Reference</label><input type="text" name="payment_reference" placeholder="Txn ID..." style="width:150px;padding:.35rem .5rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.68rem;font-weight:600;color:#166534;">Method</label>
                                <select name="payment_method" style="padding:.35rem .5rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.82rem;font-family:inherit;">
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="wire">Wire</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Process payment of ${{ number_format($p->net_payout,2) }} to {{ $p->vendor->company_name ?? "vendor" }}?')"><i class="fas fa-check"></i> Process Payment</button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('payForm{{ $p->id }}').style.display='none'">Cancel</button>
                        </form>
                    </td>
                </tr>
                @endif
                @empty
                <tr><td colspan="11" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-money-check-alt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No payouts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($payouts->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $payouts->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection
