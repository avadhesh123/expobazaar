@extends('layouts.app')
@section('title', 'Chargebacks')
@section('page-title', 'Chargebacks')

@section('content')
<div class="card">
    <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#dc2626;"></i> Chargebacks Against Your Orders</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Order</th><th>Platform</th><th>Amount</th><th>Reason</th><th>Status</th><th>Date</th><th>Remarks</th></tr></thead>
            <tbody>
                @forelse($chargebacks as $cb)
                <tr style="{{ $cb->status==='confirmed'?'background:#fef2f2;':'' }}">
                    <td style="font-weight:600;font-family:monospace;font-size:.82rem;">{{ $cb->order->order_number ?? '—' }}</td>
                    <td><span class="badge badge-info">{{ $cb->order->salesChannel->name ?? '—' }}</span></td>
                    <td style="font-family:monospace;font-weight:700;color:#dc2626;">${{ number_format($cb->amount, 2) }}</td>
                    <td style="font-size:.82rem;">{{ $cb->reason }}</td>
                    <td>@php $sc=['pending_confirmation'=>'badge-warning','confirmed'=>'badge-danger','rejected'=>'badge-gray','deducted'=>'badge-info']; @endphp <span class="badge {{ $sc[$cb->status]??'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$cb->status)) }}</span>
                        @if($cb->status==='confirmed')<div style="font-size:.6rem;color:#dc2626;margin-top:.1rem;">Will be deducted from payout</div>@endif</td>
                    <td style="font-size:.82rem;">{{ $cb->created_at->format('d M Y') }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $cb->confirmation_remarks ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-check-circle" style="font-size:2rem;color:#16a34a;display:block;margin-bottom:.5rem;"></i>No chargebacks.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($chargebacks->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $chargebacks->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
