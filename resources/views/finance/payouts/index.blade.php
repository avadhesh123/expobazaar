@extends('layouts.app')
@section('title', 'Vendor Payouts')
@section('page-title', 'Vendor Payout Management')

@section('content')
<div class="grid-kpi" style="grid-template-columns:repeat(3,1fr);">
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Pending Payouts</div><div class="kpi-value" style="color:#dc2626;font-size:1.3rem;">${{ number_format($summary['total_payouts'], 0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;"><div class="kpi-label">Paid This Month</div><div class="kpi-value" style="color:#16a34a;font-size:1.3rem;">${{ number_format($summary['paid_this_month'], 0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Pending Invoices</div><div class="kpi-value" style="color:#e8a838;">{{ $summary['pending_invoices'] }}</div></div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('finance.payouts') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option></select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Vendor</label><select name="vendor_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($vendors as $v)<option value="{{ $v->id }}" {{ request('vendor_id')==(string)$v->id?'selected':'' }}>{{ $v->company_name }}</option>@endforeach</select></div>
            <div style="min-width:120px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label><select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach(['calculated','approved','payment_pending','paid','invoice_received'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Month</label><select name="month" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ request('month')==(string)$m?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Year</label><select name="year" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@for($y=date('Y');$y>=date('Y')-2;$y--)<option value="{{ $y }}" {{ request('year')==(string)$y?'selected':'' }}>{{ $y }}</option>@endfor</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('finance.payouts') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <button type="button" class="btn btn-secondary btn-sm" style="margin-left:auto;" onclick="document.getElementById('calcPanel').style.display=document.getElementById('calcPanel').style.display==='none'?'block':'none'"><i class="fas fa-calculator"></i> Calculate</button>
        </form>
    </div>
</div>

{{-- Calculate Panel --}}
<div id="calcPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#e8a838;">
        <div class="card-body">
            <form method="POST" action="{{ route('finance.payouts.calculate') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">@csrf
                <div class="form-group" style="margin-bottom:0;min-width:200px;"><label>Vendor *</label><select name="vendor_id" required><option value="">Select...</option>@foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->company_name }} ({{ $v->vendor_code }})</option>@endforeach</select></div>
                <div class="form-group" style="margin-bottom:0;"><label>Month *</label><select name="month" required>@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $m==date('n')?'selected':'' }}>{{ date('F',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
                <div class="form-group" style="margin-bottom:0;"><label>Year *</label><input type="number" name="year" value="{{ date('Y') }}" required min="2020" style="width:80px;"></div>
                <button type="submit" class="btn btn-secondary"><i class="fas fa-calculator" style="margin-right:.3rem;"></i> Calculate Payout</button>
            </form>
            <div style="margin-top:.5rem;font-size:.72rem;color:#64748b;"><i class="fas fa-info-circle"></i> Calculates: sales − storage charges − inward charges − logistics charges − platform deductions − chargebacks = net payout.</div>
        </div>
    </div>
</div>

{{-- Payouts Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-money-check-alt" style="margin-right:.5rem;color:#2d6a4f;"></i> Payouts</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Vendor</th><th>Period</th><th>Sales</th><th>Deductions</th><th>Net Payout</th><th>Status</th><th>Invoice</th><th style="width:180px;">Actions</th></tr></thead>
            <tbody>
                @forelse($payouts as $p)
                @php $totalDed = $p->total_storage_charges + $p->total_inward_charges + $p->total_logistics_charges + $p->total_platform_deductions + $p->total_chargebacks; @endphp
                <tr>
                    <td><div style="font-weight:600;font-size:.82rem;">{{ $p->vendor->company_name ?? '—' }}</div><div style="font-size:.65rem;color:#94a3b8;">{{ $p->vendor->vendor_code ?? '' }} · {{ $p->company_code }}</div></td>
                    <td style="font-weight:600;">{{ date('M',mktime(0,0,0,$p->payout_month,1)) }} {{ $p->payout_year }}</td>
                    <td style="font-family:monospace;color:#166534;font-weight:600;">${{ number_format($p->total_sales, 2) }}</td>
                    <td style="font-family:monospace;color:#dc2626;">-${{ number_format($totalDed, 2) }}</td>
                    <td style="font-family:monospace;font-weight:800;font-size:.9rem;color:{{ $p->net_payout >= 0 ? '#166534' : '#dc2626' }};">${{ number_format($p->net_payout, 2) }}</td>
                    <td>
                        @php $sc = ['calculated'=>'badge-warning','approved'=>'badge-info','payment_pending'=>'badge-warning','paid'=>'badge-success','invoice_received'=>'badge-success']; @endphp
                        <span class="badge {{ $sc[$p->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$p->status)) }}</span>
                        @if($p->payment_date)<div style="font-size:.62rem;color:#94a3b8;">{{ $p->payment_date->format('d M Y') }}</div>@endif
                    </td>
                    <td>
                        @if($p->vendor_invoice_file)
                            <a href="{{ asset('storage/' . $p->vendor_invoice_file) }}" target="_blank" style="font-size:.72rem;color:#166534;"><i class="fas fa-file-pdf"></i> {{ $p->vendor_invoice_number }}</a>
                        @elseif($p->status === 'paid')
                            <span style="font-size:.72rem;color:#e8a838;"><i class="fas fa-clock"></i> Pending</span>
                        @else
                            <span style="font-size:.72rem;color:#94a3b8;">—</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
                            <a href="{{ route('finance.payouts.show', $p) }}" class="btn btn-outline btn-sm" title="View Detail"><i class="fas fa-eye"></i></a>
                            @if(in_array($p->status, ['calculated','approved','payment_pending']))
                                <button type="button" class="btn btn-success btn-sm" title="Process Payment" onclick="toggleRow('payForm{{ $p->id }}')"><i class="fas fa-credit-card"></i></button>
                            @endif
                            @if($p->status === 'paid')
                                <a href="{{ route('finance.payouts.advice', $p) }}" class="btn btn-outline btn-sm" title="Payment Advice"><i class="fas fa-file-download"></i></a>
                                @if(!$p->vendor_invoice_file)
                                <button type="button" class="btn btn-outline btn-sm" title="Upload Invoice" onclick="toggleRow('invForm{{ $p->id }}')"><i class="fas fa-upload"></i></button>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
                {{-- Process Payment Row --}}
                @if(in_array($p->status, ['calculated','approved','payment_pending']))
                <tr id="payForm{{ $p->id }}" style="display:none;background:#f0fdf4;">
                    <td colspan="8" style="padding:.75rem;">
                        <form method="POST" action="{{ route('finance.payouts.process', $p) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;" onsubmit="return confirm('Process payment of ${{ number_format($p->net_payout,2) }}? Payment advice will be emailed to vendor.')">@csrf
                            <div><label style="font-size:.65rem;font-weight:600;color:#166534;">Date *</label><input type="date" name="payment_date" required value="{{ date('Y-m-d') }}" style="padding:.3rem .5rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#166534;">Reference</label><input type="text" name="payment_reference" placeholder="Txn ID" style="width:130px;padding:.3rem .5rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#166534;">Method</label><select name="payment_method" style="padding:.3rem .5rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.82rem;font-family:inherit;"><option value="bank_transfer">Bank Transfer</option><option value="wire">Wire</option><option value="cheque">Cheque</option></select></div>
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Process & Send Advice</button>
                        </form>
                    </td>
                </tr>
                @endif
                {{-- Invoice Upload Row --}}
                @if($p->status === 'paid' && !$p->vendor_invoice_file)
                <tr id="invForm{{ $p->id }}" style="display:none;background:#eff6ff;">
                    <td colspan="8" style="padding:.75rem;">
                        <form method="POST" action="{{ route('finance.payouts.invoice', $p) }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">@csrf
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Invoice # *</label><input type="text" name="vendor_invoice_number" required placeholder="INV-001" style="width:120px;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Invoice File (PDF) *</label><input type="file" name="vendor_invoice_file" required accept=".pdf,.jpg,.png" style="font-size:.78rem;"></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload Invoice</button>
                        </form>
                    </td>
                </tr>
                @endif
                @empty
                <tr><td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-money-check-alt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No payouts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($payouts->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $payouts->links('pagination::tailwind') }}</div>@endif
</div>

@push('scripts')
<script>function toggleRow(id){var r=document.getElementById(id);r.style.display=r.style.display==='none'?'table-row':'none';}</script>
@endpush
@endsection
