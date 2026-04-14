@extends('layouts.app')
@section('title', 'Chargeback Confirmation')
@section('page-title', 'Chargeback Confirmation')

@section('content')
<div style="padding:.75rem 1.2rem;background:#fef2f2;border-radius:10px;border:1px solid #fecaca;margin-bottom:1.25rem;font-size:.82rem;color:#991b1b;display:flex;align-items:center;gap:.5rem;">
    <i class="fas fa-exclamation-triangle"></i>
    <span>These chargebacks require your verification. Confirmed chargebacks will be deducted from vendor payouts.</span>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#dc2626;"></i> Pending Chargebacks</h3>
        <span class="badge badge-danger" style="font-size:.78rem;">{{ $chargebacks->total() }} pending</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Order</th><th>Vendor</th><th>Amount</th><th>Reason</th><th>Raised By</th><th>Date</th><th style="width:280px;">Action</th></tr></thead>
            <tbody>
                @forelse($chargebacks as $cb)
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:.82rem;">{{ $cb->order->order_number ?? '—' }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $cb->company_code }}</div>
                    </td>
                    <td>
                        <div style="font-size:.82rem;">{{ $cb->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $cb->vendor->vendor_code ?? '' }}</div>
                    </td>
                    <td style="font-family:monospace;font-weight:700;color:#dc2626;font-size:.9rem;">${{ number_format($cb->amount, 2) }}</td>
                    <td>
                        <div style="font-size:.82rem;font-weight:500;">{{ $cb->reason }}</div>
                        @if($cb->description)<div style="font-size:.72rem;color:#64748b;margin-top:.15rem;">{{ Str::limit($cb->description, 80) }}</div>@endif
                    </td>
                    <td style="font-size:.82rem;">{{ $cb->raiser->name ?? '—' }}</td>
                    <td style="font-size:.82rem;color:#64748b;">{{ $cb->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:.3rem;flex-wrap:wrap;" id="actions{{ $cb->id }}">
                            {{-- Confirm --}}
                            <form method="POST" action="{{ route('sourcing.chargebacks.confirm', $cb) }}" style="display:inline;"
                                onsubmit="return confirm('CONFIRM chargeback of ${{ number_format($cb->amount,2) }} against {{ $cb->vendor->company_name ?? "vendor" }}?\n\nThis will be deducted from their payout.')">
                                @csrf
                                <input type="hidden" name="approved" value="1">
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Confirm</button>
                            </form>

                            {{-- Reject Toggle --}}
                            <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('rejectBox{{ $cb->id }}').style.display='block';document.getElementById('actions{{ $cb->id }}').style.display='none';">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>

                        {{-- Reject with remarks --}}
                        <div id="rejectBox{{ $cb->id }}" style="display:none;margin-top:.4rem;">
                            <form method="POST" action="{{ route('sourcing.chargebacks.confirm', $cb) }}">
                                @csrf
                                <input type="hidden" name="approved" value="0">
                                <textarea name="remarks" placeholder="Rejection reason..." required style="width:100%;padding:.35rem .5rem;border:1px solid #fca5a5;border-radius:6px;font-size:.78rem;font-family:inherit;min-height:50px;margin-bottom:.4rem;"></textarea>
                                <div style="display:flex;gap:.3rem;">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Confirm Reject</button>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('rejectBox{{ $cb->id }}').style.display='none';document.getElementById('actions{{ $cb->id }}').style.display='flex';">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;">
                        <i class="fas fa-check-circle" style="font-size:2rem;color:#16a34a;display:block;margin-bottom:.5rem;"></i>
                        <div style="font-size:.9rem;font-weight:600;">All clear!</div>
                        <div style="font-size:.82rem;">No chargebacks pending confirmation.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($chargebacks->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $chargebacks->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection
