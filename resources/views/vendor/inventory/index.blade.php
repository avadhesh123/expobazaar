@extends('layouts.app')
@section('title', 'My Inventory')
@section('page-title', 'My Inventory')

@section('content')
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card"><div class="kpi-label">Total SKUs</div><div class="kpi-value">{{ $stats['total_skus'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #1e40af;"><div class="kpi-label">Total Quantity</div><div class="kpi-value" style="color:#1e40af;">{{ number_format($stats['total_qty']) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;"><div class="kpi-label">Available</div><div class="kpi-value" style="color:#16a34a;">{{ number_format($stats['available_qty']) }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Reserved</div><div class="kpi-value" style="color:#e8a838;">{{ number_format($stats['reserved_qty']) }}</div></div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('vendor.inventory') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="min-width:180px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Warehouse</label>
                <select name="warehouse_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All Warehouses</option>
                    @foreach($warehouses as $w)<option value="{{ $w->id }}" {{ request('warehouse_id')==(string)$w->id?'selected':'' }}>{{ $w->name }} ({{ $w->location }})</option>@endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('vendor.inventory') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-boxes" style="margin-right:.5rem;color:#1e3a5f;"></i> Inventory</h3><span style="font-size:.78rem;color:#64748b;">{{ $inventory->total() }} records</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>SKU</th><th>Product</th><th>Warehouse</th><th>GRN</th><th>Received</th><th style="text-align:center;">Total Qty</th><th style="text-align:center;">Available</th><th style="text-align:center;">Reserved</th><th>Ageing</th></tr></thead>
            <tbody>
                @forelse($inventory as $inv)
                @php $age = $inv->getAgeingDays(); @endphp
                <tr>
                    <td style="font-family:monospace;font-weight:600;font-size:.82rem;">{{ $inv->product->sku ?? '—' }}</td>
                    <td>
                        <div style="font-size:.82rem;font-weight:500;">{{ Str::limit($inv->product->name ?? '—', 30) }}</div>
                        <div style="font-size:.62rem;color:#94a3b8;">{{ $inv->product->category->name ?? '' }}</div>
                    </td>
                    <td style="font-size:.78rem;">{{ $inv->warehouse->name ?? '—' }}<div style="font-size:.62rem;color:#94a3b8;">{{ $inv->warehouse->location ?? '' }}</div></td>
                    <td style="font-family:monospace;font-size:.72rem;color:#64748b;">{{ $inv->grn->grn_number ?? '—' }}</td>
                    <td style="font-size:.78rem;">{{ $inv->received_date ? $inv->received_date->format('d M Y') : '—' }}</td>
                    <td style="text-align:center;font-weight:700;">{{ $inv->quantity }}</td>
                    <td style="text-align:center;font-weight:700;color:#16a34a;">{{ $inv->available_quantity }}</td>
                    <td style="text-align:center;font-weight:600;color:{{ $inv->reserved_quantity > 0 ? '#e8a838' : '#94a3b8' }};">{{ $inv->reserved_quantity }}</td>
                    <td>
                        <span style="font-weight:700;font-size:.82rem;color:{{ $age > 90 ? '#dc2626' : ($age > 60 ? '#e8a838' : ($age > 30 ? '#1e40af' : '#16a34a')) }};">{{ $age }}d</span>
                        <div style="font-size:.55rem;color:#94a3b8;text-transform:uppercase;">
                            @if($age > 90) critical
                            @elseif($age > 60) slow
                            @elseif($age > 30) normal
                            @else fresh
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-boxes" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No inventory found. Inventory appears after your shipments are received via GRN.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($inventory->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $inventory->links('pagination::tailwind') }}</div>@endif
</div>

{{-- Ageing Legend --}}
<div style="margin-top:.75rem;display:flex;gap:1.5rem;font-size:.72rem;color:#64748b;">
    <span><span style="color:#16a34a;font-weight:700;">●</span> Fresh (0-30d)</span>
    <span><span style="color:#1e40af;font-weight:700;">●</span> Normal (31-60d)</span>
    <span><span style="color:#e8a838;font-weight:700;">●</span> Slow (61-90d)</span>
    <span><span style="color:#dc2626;font-weight:700;">●</span> Critical (90d+)</span>
</div>
@endsection
