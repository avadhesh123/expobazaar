@extends('layouts.app')
@section('title', 'Receivables')
@section('page-title', 'Finance Reconciliation & Receivables')

@section('content')
{{-- Summary --}}
<div class="grid-kpi" style="grid-template-columns:repeat(3,1fr);">
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Unpaid Orders</div><div class="kpi-value" style="color:#dc2626;">{{ $summary['unpaid_count'] }}</div><div style="font-size:.78rem;color:#dc2626;font-weight:600;">${{ number_format($summary['unpaid_total'], 0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Partial Payments</div><div class="kpi-value" style="color:#e8a838;">{{ $summary['partial_count'] }}</div><div style="font-size:.78rem;color:#e8a838;font-weight:600;">${{ number_format($summary['partial_total'], 0) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #7c3aed;"><div class="kpi-label">Total Deductions</div><div class="kpi-value" style="font-size:1.3rem;color:#7c3aed;">${{ number_format($summary['total_deductions'], 0) }}</div></div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('finance.receivables') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="flex:1;min-width:180px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Order # or Platform ID..." style="width:100%;padding:.4rem .65rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option></select></div>
            <div style="min-width:120px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label><select name="payment_status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">Unpaid Only</option><option value="unpaid" {{ request('payment_status')==='unpaid'?'selected':'' }}>Unpaid</option><option value="partial" {{ request('payment_status')==='partial'?'selected':'' }}>Partial</option><option value="paid" {{ request('payment_status')==='paid'?'selected':'' }}>Paid</option></select></div>
            <div style="min-width:120px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Channel</label><select name="channel" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($channels as $ch)<option value="{{ $ch->id }}" {{ request('channel')==(string)$ch->id?'selected':'' }}>{{ $ch->name }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('finance.receivables') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('finance.receivables.download', request()->query()) }}" class="btn btn-secondary btn-sm" style="margin-left:auto;"><i class="fas fa-download"></i> Download Template</a>
        </form>
    </div>
</div>

{{-- Receivables Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-hand-holding-usd" style="margin-right:.5rem;color:#1e3a5f;"></i> Receivables</h3><span style="font-size:.78rem;color:#64748b;">{{ $receivables->total() }} records (unpaid by default)</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Order</th><th>Platform</th><th>Company</th><th>Order Amt</th><th>Commission</th><th>Fee</th><th>Insurance</th><th>CB</th><th>Other</th><th>Net</th><th>Received</th><th>Status</th><th style="width:120px;">Actions</th></tr></thead>
            <tbody>
                @forelse($receivables as $r)
                <tr>
                    <td><div style="font-weight:600;font-family:monospace;font-size:.8rem;">{{ $r->order->order_number ?? '—' }}</div><div style="font-size:.65rem;color:#94a3b8;">{{ $r->order->platform_order_id ?? '' }}</div></td>
                    <td><span class="badge badge-info">{{ $r->order->salesChannel->name ?? '—' }}</span></td>
                    <td>{{ $r->company_code }}</td>
                    <td style="font-family:monospace;font-weight:600;">${{ number_format($r->order_amount, 2) }}</td>
                    <td style="font-family:monospace;font-size:.78rem;color:#dc2626;">${{ number_format($r->platform_commission, 2) }}</td>
                    <td style="font-family:monospace;font-size:.78rem;color:#dc2626;">${{ number_format($r->platform_fee, 2) }}</td>
                    <td style="font-family:monospace;font-size:.78rem;">${{ number_format($r->insurance_charge, 2) }}</td>
                    <td style="font-family:monospace;font-size:.78rem;color:#991b1b;">${{ number_format($r->chargeback_amount, 2) }}</td>
                    <td style="font-family:monospace;font-size:.78rem;">${{ number_format($r->other_deductions, 2) }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">${{ number_format($r->net_receivable, 2) }}</td>
                    <td style="font-family:monospace;font-weight:600;">${{ number_format($r->amount_received, 2) }}</td>
                    <td><span class="badge {{ ['unpaid'=>'badge-danger','partial'=>'badge-warning','paid'=>'badge-success'][$r->payment_status] ?? 'badge-gray' }}">{{ ucfirst($r->payment_status) }}</span></td>
                    <td>
                        <div style="display:flex;gap:.25rem;">
                            <button type="button" class="btn btn-outline btn-sm" title="Edit Deductions" onclick="toggleRow('ded{{ $r->id }}')"><i class="fas fa-edit"></i></button>
                            @if($r->payment_status !== 'paid')
                            <button type="button" class="btn btn-success btn-sm" title="Record Payment" onclick="toggleRow('pay{{ $r->id }}')"><i class="fas fa-dollar-sign"></i></button>
                            @endif
                        </div>
                    </td>
                </tr>
                {{-- Deductions Row --}}
                <tr id="ded{{ $r->id }}" style="display:none;background:#eff6ff;">
                    <td colspan="13" style="padding:.75rem;">
                        <form method="POST" action="{{ route('finance.receivables.deductions', $r) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">@csrf
                            <div><label style="font-size:.65rem;font-weight:600;color:#64748b;">Commission</label><input type="number" step="0.01" name="platform_commission" value="{{ $r->platform_commission }}" style="width:95px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;font-family:monospace;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#64748b;">Platform Fee</label><input type="number" step="0.01" name="platform_fee" value="{{ $r->platform_fee }}" style="width:95px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;font-family:monospace;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#64748b;">Insurance</label><input type="number" step="0.01" name="insurance_charge" value="{{ $r->insurance_charge }}" style="width:85px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;font-family:monospace;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#64748b;">Other</label><input type="number" step="0.01" name="other_deductions" value="{{ $r->other_deductions }}" style="width:85px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;font-family:monospace;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#64748b;">Notes</label><input type="text" name="deduction_notes" value="{{ $r->deduction_notes }}" placeholder="Notes..." style="width:150px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;"></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
                        </form>
                    </td>
                </tr>
                {{-- Payment Row --}}
                <tr id="pay{{ $r->id }}" style="display:none;background:#f0fdf4;">
                    <td colspan="13" style="padding:.75rem;">
                        <form method="POST" action="{{ route('finance.receivables.payment', $r) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">@csrf
                            <div><label style="font-size:.65rem;font-weight:600;color:#166534;">Amount *</label><input type="number" step="0.01" name="amount_received" required value="{{ $r->net_receivable }}" style="width:110px;padding:.3rem .4rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.78rem;font-family:monospace;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#166534;">Date *</label><input type="date" name="payment_date" required value="{{ date('Y-m-d') }}" style="padding:.3rem .4rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.78rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#166534;">Reference</label><input type="text" name="payment_reference" placeholder="Txn ID..." style="width:120px;padding:.3rem .4rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.78rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#166534;">Bank Ref</label><input type="text" name="bank_reference" placeholder="Bank ref..." style="width:120px;padding:.3rem .4rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.78rem;"></div>
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Record</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="13" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-check-circle" style="font-size:2rem;color:#16a34a;display:block;margin-bottom:.5rem;"></i>All receivables are paid!</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($receivables->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $receivables->links('pagination::tailwind') }}</div>@endif
</div>

@push('scripts')
<script>function toggleRow(id){var r=document.getElementById(id);r.style.display=r.style.display==='none'?'table-row':'none';}</script>
@endpush
@endsection
