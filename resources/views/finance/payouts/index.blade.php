@extends('layouts.app')
@section('title', 'Vendor Payouts')
@section('page-title', 'Vendor Payout Management')

@section('content')
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card" style="border-left:3px solid #dc2626;">
        <div class="kpi-label">Pending Payouts</div>
        <div class="kpi-value" style="color:#dc2626;font-size:1.3rem;">{{ $activeCurrencySymbol }}{{ number_format($summary['total_payouts'], 0) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;">
        <div class="kpi-label">Paid This Month</div>
        <div class="kpi-value" style="color:#16a34a;font-size:1.3rem;">{{ $activeCurrencySymbol }}{{ number_format($summary['paid_this_month'], 0) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #1e40af;">
        <div class="kpi-label">Partially Paid</div>
        <div class="kpi-value" style="color:#1e40af;font-size:1.3rem;">{{ $activeCurrencySymbol }}{{ number_format($summary['partially_paid'] ?? 0, 0) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;">
        <div class="kpi-label">Pending Invoices</div>
        <div class="kpi-value" style="color:#e8a838;">{{ $activeCurrencySymbol }}{{ number_format($summary['pending_invoices'], 0) }}</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('finance.payouts') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            @php
            $user = auth()->user();
            $userCompanyCodes = $user->company_codes ?? [];
            if (is_string($userCompanyCodes)) $userCompanyCodes = json_decode($userCompanyCodes, true) ?? [];
            $allowedCompanies = array_filter(array_map('strval', $userCompanyCodes));
            @endphp

            <div style="min-width:110px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label>
                <select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @if($user->isAdmin())
                    <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option>
                    <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option>
                    <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option>
                    @else
                    @foreach($allowedCompanies as $code)
                    <option value="{{ $code }}" {{ request('company_code') === $code ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                    @endif
                </select>
            </div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Vendor</label><select name="vendor_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>@foreach($vendors as $v)<option value="{{ $v->id }}" {{ request('vendor_id')==(string)$v->id?'selected':'' }}>{{ $v->company_name }}</option>@endforeach
                </select></div>
            <div style="min-width:120px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label><select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>@foreach(['calculated','partially_paid','approved','payment_pending','paid','invoice_received'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach
                </select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Month</label><select name="month" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ request('month')==(string)$m?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor
                </select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Year</label><select name="year" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>@for($y=date('Y');$y>=date('Y')-2;$y--)<option value="{{ $y }}" {{ request('year')==(string)$y?'selected':'' }}>{{ $y }}</option>@endfor
                </select></div>
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
                <div class="form-group" style="margin-bottom:0;min-width:200px;"><label>Vendor *</label><select name="pay_vendor_id" required>
                        <option value="">Select...</option>@foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->company_name }} ({{ $v->vendor_code }})</option>@endforeach
                    </select></div>
                <div class="form-group" style="margin-bottom:0;"><label>Month *</label><select name="pay_month" required>@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $m==date('n')?'selected':'' }}>{{ date('F',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
                <div class="form-group" style="margin-bottom:0;"><label>Year *</label><input type="number" name="pay_year" value="{{ date('Y') }}" required min="2020" style="width:80px;"></div>
                <button type="submit" class="btn btn-secondary"><i class="fas fa-calculator" style="margin-right:.3rem;"></i> Calculate Payout</button>
            </form>
        </div>
    </div>
</div>

{{-- Payouts Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-money-check-alt" style="margin-right:.5rem;color:#2d6a4f;"></i> Payouts</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>Period</th>
                    <th>Sales</th>
                    <th>Deductions</th>
                    <th>Net Payout</th>
                    <th style="text-align:right;">Paid</th>
                    <th style="text-align:right;">Balance</th>
                    <th>Status</th>
                    <th>Invoice</th>
                    <th style="width:180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payouts as $p)
                @php
                    $totalDed = floatval($p->total_storage_charges) + floatval($p->total_inward_charges) + floatval($p->total_logistics_charges) + floatval($p->total_platform_deductions) + floatval($p->total_chargebacks);
                    $totalPaid = floatval($p->total_paid ?? $p->paid_amount ?? 0);
                    $netPayout = floatval($p->net_payout);
                    $balance = max(0, round($netPayout - $totalPaid, 2));
                    $pctPaid = $netPayout > 0 ? min(100, round(($totalPaid / $netPayout) * 100, 0)) : 0;
                    $isFullyPaid = $balance <= 0.01 && $totalPaid > 0;
                    $isPartial = $totalPaid > 0 && !$isFullyPaid;
                @endphp
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:.82rem;">{{ $p->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.65rem;color:#94a3b8;">{{ $p->vendor->vendor_code ?? '' }} · {{ $p->company_code }}</div>
                    </td>
                    <td style="font-weight:600;">{{ date('M',mktime(0,0,0,$p->payout_month,1)) }} {{ $p->payout_year }}</td>
                    <td style="font-family:monospace;color:#166534;font-weight:600;">{{ $activeCurrencySymbol }}{{ number_format($p->total_sales, 2) }}</td>
                    <td style="font-family:monospace;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($totalDed, 2) }}</td>
                    <td style="font-family:monospace;font-weight:800;font-size:.9rem;color:{{ $netPayout >= 0 ? '#166534' : '#dc2626' }};">{{ $activeCurrencySymbol }}{{ number_format($netPayout, 2) }}</td>

                    {{-- Paid Amount --}}
                    <td style="text-align:right;">
                        @if($totalPaid > 0)
                        <div style="font-family:monospace;font-weight:700;color:#16a34a;">{{ $activeCurrencySymbol }}{{ number_format($totalPaid, 2) }}</div>
                        <div style="height:4px;background:#e2e8f0;border-radius:2px;margin-top:.2rem;width:60px;margin-left:auto;">
                            <div style="height:100%;width:{{ $pctPaid }}%;background:{{ $isFullyPaid ? '#16a34a' : '#e8a838' }};border-radius:2px;"></div>
                        </div>
                        @else
                        <span style="font-size:.72rem;color:#94a3b8;">—</span>
                        @endif
                    </td>

                    {{-- Balance --}}
                    <td style="text-align:right;">
                        @if($isFullyPaid)
                        <span style="font-size:.65rem;padding:2px 6px;border-radius:4px;background:#f0fdf4;color:#16a34a;font-weight:700;">Cleared</span>
                        @elseif($totalPaid > 0)
                        <div style="font-family:monospace;font-weight:700;color:#dc2626;">{{ $activeCurrencySymbol }}{{ number_format($balance, 2) }}</div>
                        @else
                        <div style="font-family:monospace;color:#64748b;">{{ $activeCurrencySymbol }}{{ number_format($netPayout, 2) }}</div>
                        @endif
                    </td>

                    <td>
                        @php
                            $statusLabel = $p->status;
                            if ($isPartial && $p->status !== 'partially_paid') $statusLabel = 'partially_paid';
                            $sc = ['calculated'=>'badge-warning','partially_paid'=>'badge-info','approved'=>'badge-info','payment_pending'=>'badge-warning','paid'=>'badge-success','invoice_received'=>'badge-success'];
                        @endphp
                        <span class="badge {{ $sc[$statusLabel] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$statusLabel)) }}</span>
                        @if($p->paid_date)<div style="font-size:.62rem;color:#94a3b8;">{{ \Carbon\Carbon::parse($p->paid_date)->format('d M Y') }}</div>@endif
                    </td>
                    <td>
                        @if($p->vendor_invoice_file)
                        <a href="{{ \App\Helpers\FileStorage::url($p->vendor_invoice_file) }}" target="_blank" style="font-size:.72rem;color:#166534;"><i class="fas fa-file-pdf"></i> {{ $p->vendor_invoice_number }}</a>
                        @elseif($p->status === 'paid')
                        <span style="font-size:.72rem;color:#e8a838;"><i class="fas fa-clock"></i> Pending</span>
                        @else
                        <span style="font-size:.72rem;color:#94a3b8;">—</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
                            <a href="{{ route('finance.payouts.show', $p) }}" class="btn btn-outline btn-sm" title="View Detail"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('finance.payouts.download', $p) }}" class="btn btn-outline btn-sm" title="Download XLSX"><i class="fas fa-download"></i></a>
                            @if(!$isFullyPaid)
                            <button type="button" class="btn btn-success btn-sm" title="Record Payment" onclick="openPaymentModal({{ $p->id }}, '{{ $p->vendor->company_name ?? '' }}', {{ $balance }}, {{ $netPayout }}, {{ $totalPaid }})"><i class="fas fa-credit-card"></i></button>
                            @endif
                            @if($isFullyPaid || $p->status === 'paid')
                            <a href="{{ route('finance.payouts.advice', $p) }}" class="btn btn-outline btn-sm" title="Payment Advice"><i class="fas fa-file-download"></i></a>
                            @if(!$p->vendor_invoice_file)
                            <button type="button" class="btn btn-outline btn-sm" title="Upload Invoice" onclick="toggleRow('invForm{{ $p->id }}')"><i class="fas fa-upload"></i></button>
                            @endif
                            @endif
                        </div>
                    </td>
                </tr>
                {{-- Invoice Upload Row --}}
                @if(($isFullyPaid || $p->status === 'paid') && !$p->vendor_invoice_file)
                <tr id="invForm{{ $p->id }}" style="display:none;background:#eff6ff;">
                    <td colspan="10" style="padding:.75rem;">
                        <form method="POST" action="{{ route('finance.payouts.invoice', $p) }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">@csrf
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Invoice # *</label><input type="text" name="vendor_invoice_number" required placeholder="INV-001" style="width:120px;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Invoice File (PDF) *</label><input type="file" name="vendor_invoice_file" required accept=".pdf,.jpg,.png" style="font-size:.78rem;"></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload Invoice</button>
                        </form>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-money-check-alt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No payouts found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($payouts->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $payouts->links('pagination::tailwind') }}</div>@endif
</div>

{{-- Payment Modal --}}
<div id="paymentModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;width:520px;max-width:94%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #bbf7d0;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:.95rem;font-weight:700;color:#166534;margin:0;">
                <i class="fas fa-credit-card" style="margin-right:.3rem;"></i> Record Payment — <span id="payVendorName"></span>
            </h3>
            <button onclick="closePaymentModal()" style="background:none;border:none;cursor:pointer;font-size:1.3rem;color:#94a3b8;">&times;</button>
        </div>

        {{-- Summary Bar --}}
        <div style="padding:.6rem 1.25rem;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;gap:1.5rem;font-size:.78rem;">
            <div>
                <span style="color:#64748b;">Net Payout:</span>
                <strong id="payNetPayout" style="color:#166534;"></strong>
            </div>
            <div>
                <span style="color:#64748b;">Already Paid:</span>
                <strong id="payAlreadyPaid" style="color:#1e40af;"></strong>
            </div>
            <div>
                <span style="color:#64748b;">Balance:</span>
                <strong id="payBalance" style="color:#dc2626;"></strong>
            </div>
        </div>

        <form method="POST" id="paymentForm" action="">
            @csrf
            <div style="padding:1.25rem;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div>
                        <label style="font-size:.72rem;font-weight:600;color:#374151;display:block;margin-bottom:.25rem;">Amount <span style="color:#dc2626;">*</span></label>
                        <input type="number" name="amount" id="payAmount" step="0.01" min="0.01" required
                            style="width:100%;padding:.5rem .6rem;border:1.5px solid #bbf7d0;border-radius:8px;font-size:.9rem;font-family:monospace;text-align:center;">
                        <div style="font-size:.58rem;color:#94a3b8;margin-top:.15rem;">Max: <span id="payMaxLabel"></span></div>
                    </div>
                    <div>
                        <label style="font-size:.72rem;font-weight:600;color:#374151;display:block;margin-bottom:.25rem;">Payment Date <span style="color:#dc2626;">*</span></label>
                        <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}"
                            style="width:100%;padding:.5rem .6rem;border:1.5px solid #d1d5db;border-radius:8px;font-size:.85rem;">
                    </div>
                    <div>
                        <label style="font-size:.72rem;font-weight:600;color:#374151;display:block;margin-bottom:.25rem;">Payment Mode</label>
                        <select name="payment_mode" style="width:100%;padding:.5rem .6rem;border:1.5px solid #d1d5db;border-radius:8px;font-size:.85rem;font-family:inherit;">
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="wire">Wire Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="upi">UPI</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:.72rem;font-weight:600;color:#374151;display:block;margin-bottom:.25rem;">Reference #</label>
                        <input type="text" name="reference_number" placeholder="Txn ID, Cheque #..."
                            style="width:100%;padding:.5rem .6rem;border:1.5px solid #d1d5db;border-radius:8px;font-size:.85rem;">
                    </div>
                </div>
                <div style="margin-top:.75rem;">
                    <label style="font-size:.72rem;font-weight:600;color:#374151;display:block;margin-bottom:.25rem;">Remarks</label>
                    <input type="text" name="remarks" placeholder="e.g. Tranche 1, partial payment, final settlement..."
                        style="width:100%;padding:.5rem .6rem;border:1.5px solid #d1d5db;border-radius:8px;font-size:.85rem;">
                </div>
            </div>
            <div style="padding:.75rem 1.25rem;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                <label style="font-size:.72rem;color:#64748b;">
                    <input type="checkbox" id="payFullCheck" onchange="fillFullAmount()" style="margin-right:.3rem;accent-color:#16a34a;">
                    Pay full balance
                </label>
                <div style="display:flex;gap:.4rem;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="closePaymentModal()">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:#16a34a;color:#fff;border:none;padding:.45rem .8rem;border-radius:6px;font-weight:700;cursor:pointer;"
                        onclick="return confirm('Record this payment?')">
                        <i class="fas fa-check" style="margin-right:.2rem;"></i> Record Payment
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function toggleRow(id) {
    var r = document.getElementById(id);
    r.style.display = r.style.display === 'none' ? 'table-row' : 'none';
}

var currentBalance = 0;

function openPaymentModal(payoutId, vendorName, balance, netPayout, totalPaid) {
    currentBalance = balance;
    document.getElementById('payVendorName').textContent = vendorName;
    document.getElementById('payNetPayout').textContent = '{{ $activeCurrencySymbol }}' + netPayout.toFixed(2);
    document.getElementById('payAlreadyPaid').textContent = '{{ $activeCurrencySymbol }}' + totalPaid.toFixed(2);
    document.getElementById('payBalance').textContent = '{{ $activeCurrencySymbol }}' + balance.toFixed(2);
    document.getElementById('payAmount').max = balance;
    document.getElementById('payAmount').placeholder = balance.toFixed(2);
    document.getElementById('payAmount').value = '';
    document.getElementById('payMaxLabel').textContent = '{{ $activeCurrencySymbol }}' + balance.toFixed(2);
    document.getElementById('payFullCheck').checked = false;
    document.getElementById('paymentForm').action = '/finance/payouts/' + payoutId + '/payment';
    document.getElementById('paymentModal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function fillFullAmount() {
    var input = document.getElementById('payAmount');
    if (document.getElementById('payFullCheck').checked) {
        input.value = currentBalance.toFixed(2);
    } else {
        input.value = '';
    }
}

document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) closePaymentModal();
});
</script>
@endpush
@endsection
