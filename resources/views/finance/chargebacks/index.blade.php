@extends('layouts.app')
@section('title', 'Chargebacks')
@section('page-title', 'Chargeback Management')

@section('content')
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card"><div class="kpi-label">Total</div><div class="kpi-value">{{ $stats['total'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Pending Sourcing Confirmation</div><div class="kpi-value" style="color:#e8a838;">{{ $stats['pending'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Confirmed</div><div class="kpi-value" style="color:#dc2626;">{{ $stats['confirmed'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Confirmed Amount</div><div class="kpi-value" style="font-size:1.3rem;color:#dc2626;">${{ number_format($stats['total_amount'], 0) }}</div></div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('finance.chargebacks') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label><select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach(['pending_confirmation','confirmed','rejected','deducted'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option></select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Vendor</label><select name="vendor_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($vendors as $v)<option value="{{ $v->id }}" {{ request('vendor_id')==(string)$v->id?'selected':'' }}>{{ $v->company_name }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('finance.chargebacks') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <button type="button" class="btn btn-danger btn-sm" style="margin-left:auto;" onclick="document.getElementById('raisePanel').style.display=document.getElementById('raisePanel').style.display==='none'?'block':'none'"><i class="fas fa-plus"></i> Raise Chargeback</button>
        </form>
    </div>
</div>

{{-- Raise Chargeback Panel --}}
<div id="raisePanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#dc2626;">
        <div class="card-body">
            <div style="font-size:.88rem;font-weight:700;color:#dc2626;margin-bottom:.75rem;"><i class="fas fa-exclamation-triangle" style="margin-right:.3rem;"></i> Raise New Chargeback</div>
            <div style="padding:.5rem .75rem;background:#fef2f2;border-radius:6px;margin-bottom:.75rem;font-size:.78rem;color:#991b1b;"><i class="fas fa-info-circle" style="margin-right:.2rem;"></i> On raising a chargeback, a notification will be sent to the Sourcing team for confirmation. Once confirmed, it will be reflected in the vendor's login and deducted from their monthly payout.</div>
            <form method="POST" action="" id="chargebackForm" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
                @csrf
                <div class="form-group" style="margin-bottom:0;"><label>Order ID *</label><input type="text" id="cbOrderId" required placeholder="Enter order ID" style="width:120px;"></div>
                <div class="form-group" style="margin-bottom:0;"><label>Amount ($) *</label><input type="number" step="0.01" min="0.01" name="amount" required placeholder="0.00" style="width:100px;font-family:monospace;"></div>
                <div class="form-group" style="margin-bottom:0;min-width:180px;"><label>Reason *</label><input type="text" name="reason" required placeholder="e.g. Damaged, Wrong item, Missing..."></div>
                <div class="form-group" style="margin-bottom:0;min-width:200px;"><label>Description</label><input type="text" name="description" placeholder="Additional details..."></div>
                <button type="submit" class="btn btn-danger" onclick="this.form.action='/finance/orders/'+document.getElementById('cbOrderId').value+'/chargeback'; return confirm('Raise chargeback? Sourcing will be notified for confirmation.')"><i class="fas fa-exclamation-triangle" style="margin-right:.3rem;"></i> Raise</button>
            </form>
        </div>
    </div>
</div>

{{-- Chargebacks Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#dc2626;"></i> Chargebacks</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Order</th><th>Vendor</th><th>Company</th><th>Amount</th><th>Reason</th><th>Raised By</th><th>Date</th><th>Sourcing Confirmation</th><th>Status</th></tr></thead>
            <tbody>
                @forelse($chargebacks as $cb)
                <tr style="{{ $cb->status==='confirmed'?'background:#fef2f2;':'' }}">
                    <td><div style="font-weight:600;font-family:monospace;font-size:.8rem;">{{ $cb->order->order_number ?? '—' }}</div><div style="font-size:.65rem;color:#94a3b8;">{{ $cb->order->salesChannel->name ?? '' }}</div></td>
                    <td><div style="font-size:.82rem;">{{ $cb->vendor->company_name ?? '—' }}</div><div style="font-size:.65rem;color:#94a3b8;">{{ $cb->vendor->vendor_code ?? '' }}</div></td>
                    <td>{{ $cb->company_code }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#dc2626;">${{ number_format($cb->amount, 2) }}</td>
                    <td><div style="font-size:.82rem;">{{ $cb->reason }}</div>@if($cb->description)<div style="font-size:.68rem;color:#64748b;">{{ Str::limit($cb->description, 50) }}</div>@endif</td>
                    <td style="font-size:.82rem;">{{ $cb->raiser->name ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $cb->created_at->format('d M Y') }}</td>
                    <td>
                        @if($cb->confirmed_at)
                            <div style="font-size:.78rem;font-weight:600;color:{{ $cb->status==='confirmed'?'#dc2626':'#16a34a' }};">
                                {{ $cb->status==='confirmed'?'Confirmed':'Rejected' }} by {{ $cb->confirmer->name ?? '—' }}
                            </div>
                            <div style="font-size:.65rem;color:#94a3b8;">{{ $cb->confirmed_at->format('d M Y') }}</div>
                            @if($cb->confirmation_remarks)<div style="font-size:.65rem;color:#64748b;font-style:italic;">"{{ Str::limit($cb->confirmation_remarks, 40) }}"</div>@endif
                        @else
                            <span style="display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .5rem;background:#fef3c7;border-radius:5px;font-size:.72rem;color:#92400e;font-weight:600;">
                                <i class="fas fa-clock" style="font-size:.55rem;"></i> Awaiting Sourcing
                            </span>
                        @endif
                    </td>
                    <td>
                        @php $colors = ['pending_confirmation'=>'badge-warning','confirmed'=>'badge-danger','rejected'=>'badge-gray','deducted'=>'badge-info']; @endphp
                        <span class="badge {{ $colors[$cb->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$cb->status)) }}</span>
                        @if($cb->status==='confirmed')<div style="font-size:.6rem;color:#dc2626;margin-top:.1rem;">Deducted from vendor payout</div>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-check-circle" style="font-size:2rem;color:#16a34a;display:block;margin-bottom:.5rem;"></i>No chargebacks.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($chargebacks->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $chargebacks->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
