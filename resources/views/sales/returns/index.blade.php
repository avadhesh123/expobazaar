@extends('layouts.app')
@section('title', 'Order Returns')
@section('page-title', 'Order Returns')

@section('content')
<div style="display:flex;gap:.75rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;border-left:3px solid #1e40af;"><div class="kpi-label">Total Returns</div><div class="kpi-value" style="color:#1e40af;">{{ number_format($stats['total']) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #e8a838;"><div class="kpi-label">Initiated</div><div class="kpi-value" style="color:#e8a838;">{{ number_format($stats['initiated']) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #7c3aed;"><div class="kpi-label">Received</div><div class="kpi-value" style="color:#7c3aed;">{{ number_format($stats['received']) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #dc2626;"><div class="kpi-label">Total Refunded</div><div class="kpi-value" style="color:#dc2626;">{{ $activeCurrencySymbol }}{{ number_format($stats['refunded'], 2) }}</div></div>
</div>

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.75rem 1.4rem;">
        <form method="GET" style="display:flex;gap:.5rem;align-items:flex-end;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Return # or Order #..." style="padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;flex:1;">
            <select name="status" style="padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                <option value="">All Status</option>
                @foreach(['initiated','received','inspected','approved','rejected','refunded','restocked'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select name="reason" style="padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                <option value="">All Reasons</option>
                @foreach(['damaged','wrong_item','missing_item','quality_issue','customer_request','short_shipment','other'] as $r)
                <option value="{{ $r }}" {{ request('reason') === $r ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$r)) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('sales.returns') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('sales.returns.create') }}" class="btn btn-success btn-sm" style="margin-left:auto;"><i class="fas fa-plus"></i> New Return</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-undo-alt" style="margin-right:.5rem;color:#dc2626;"></i> Returns</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead><tr style="background:#f0f4f8;">
                <th>Return #</th><th>Order #</th><th>Date</th><th>Reason</th><th style="text-align:center;">Items</th><th style="text-align:right;">Amount</th><th>Status</th><th>Created By</th><th>Actions</th>
            </tr></thead>
            <tbody>
                @forelse($returns as $ret)
                @php
                    $statusColors = ['initiated'=>'badge-warning','received'=>'badge-info','inspected'=>'badge-primary','approved'=>'badge-success','rejected'=>'badge-gray','refunded'=>'badge-success','restocked'=>'badge-info'];
                @endphp
                <tr>
                    <td style="font-family:monospace;font-weight:700;">{{ $ret->return_number }}</td>
                    <td style="font-family:monospace;">{{ $ret->order->order_number ?? '—' }}</td>
                    <td>{{ $ret->return_date->format('d M Y') }}</td>
                    <td><span class="badge badge-gray">{{ ucwords(str_replace('_',' ',$ret->reason)) }}</span></td>
                    <td style="text-align:center;font-weight:600;">{{ $ret->items->count() }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;color:#dc2626;">{{ $activeCurrencySymbol }}{{ number_format($ret->total_return_amount, 2) }}</td>
                    <td><span class="badge {{ $statusColors[$ret->status] ?? 'badge-gray' }}">{{ ucfirst($ret->status) }}</span></td>
                    <td style="font-size:.72rem;">{{ $ret->creator->name ?? '—' }}</td>
                    <td><a href="{{ route('sales.returns.show', $ret) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i></a></td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8;">No returns found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($returns->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $returns->links() }}</div>@endif
</div>
@endsection
