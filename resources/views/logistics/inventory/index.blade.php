@extends('layouts.app')
@section('title', 'Inventory')
@section('page-title', 'Inventory Management')

@section('content')
{{-- Stats --}}
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card">
        <div class="kpi-label">Total SKUs</div>
        <div class="kpi-value">{{ number_format($stats['total_skus']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total Units</div>
        <div class="kpi-value">{{ number_format($stats['total_units']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label" style="color:#166534;">Available</div>
        <div class="kpi-value" style="color:#166534;">{{ number_format($stats['available']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label" style="color:#e8a838;">Reserved</div>
        <div class="kpi-value" style="color:#e8a838;">{{ number_format($stats['reserved']) }}</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('logistics.inventory') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="flex:1;min-width:180px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="SKU or product name..." style="width:100%;padding:.4rem .65rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option>
                    <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option>
                    <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option>
                </select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Warehouse</label><select name="warehouse_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>@foreach($warehouses as $wh)<option value="{{ $wh->id }}" {{ request('warehouse_id')==(string)$wh->id?'selected':'' }}>{{ $wh->name }}</option>@endforeach
                </select></div>
            <div style="min-width:120px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Vendor</label><select name="vendor_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>@foreach($vendors as $v)<option value="{{ $v->id }}" {{ request('vendor_id')==(string)$v->id?'selected':'' }}>{{ $v->company_name }}</option>@endforeach
                </select></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Ageing</label><select name="ageing" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    <option value="0_30" {{ request('ageing')==='0_30'?'selected':'' }}>0-30 days</option>
                    <option value="31_60" {{ request('ageing')==='31_60'?'selected':'' }}>31-60 days</option>
                    <option value="61_90" {{ request('ageing')==='61_90'?'selected':'' }}>61-90 days</option>
                    <option value="91_120" {{ request('ageing')==='91_120'?'selected':'' }}>91-120 days</option>
                    <option value="120_plus" {{ request('ageing')==='120_plus'?'selected':'' }}>120+ days</option>
                </select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('logistics.inventory') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <div style="margin-left:auto;display:flex;gap:.4rem;">
                <a href="{{ route('logistics.inventory.download', request()->query()) }}" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> CSV</a>
                <a href="{{ route('logistics.inventory.ageing') }}" class="btn btn-outline btn-sm"><i class="fas fa-clock"></i> Ageing</a>
                <a href="{{ route('logistics.inventory.allocation') }}" class="btn btn-outline btn-sm"><i class="fas fa-exchange-alt"></i> Transfer</a>
            </div>
        </form>
    </div>
</div>

{{-- Inventory Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-boxes" style="margin-right:.5rem;color:#1e3a5f;"></i> Inventory</h3><span style="font-size:.78rem;color:#64748b;">{{ $inventory->total() }} records</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Vendor</th>
                    <th>Category</th>
                    <th>Warehouse</th>
                    <th>Sub-Location</th>
                    <th>Company</th>
                    <th>Qty</th>
                    <th>Available</th>
                    <th>Reserved</th>
                    <th>Received</th>
                    <th>Ageing</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inventory as $inv)
                @php
                $days = $inv->received_date
                ? round(abs(now()->diffInRealHours($inv->received_date) / 24), 1)
                : 0;
                @endphp

                <tr>
                    <td style="font-family:monospace;font-weight:600;font-size:.8rem;">{{ $inv->product->sku ?? '—' }}</td>
                    <td style="font-size:.82rem;font-weight:500;">{{ $inv->product->name ?? '—' }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $inv->product->vendor->company_name ?? '—' }}</td>
                    <td style="font-size:.78rem;">{{ $inv->product->category->name ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $inv->warehouse->name ?? '—' }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $inv->subLocation->name ?? '—' }}</td>
                    <td>{{ $inv->company_code }}</td>
                    <td style="text-align:center;font-weight:700;font-family:monospace;">{{ $inv->quantity }}</td>
                    <td style="text-align:center;font-weight:700;font-family:monospace;color:#166534;">{{ $inv->available_quantity }}</td>
                    <td style="text-align:center;font-family:monospace;color:#e8a838;">{{ $inv->reserved_quantity }}</td>
                    <td style="font-size:.78rem;">{{ $inv->received_date?->format('d M Y') ?? '—' }}</td>
                    <td> <span style="padding:.15rem .4rem;border-radius:5px;font-size:.75rem;font-weight:700;background:{{ $days>90?'#fee2e2':($days>60?'#fef3c7':($days>30?'#fefce8':'#dcfce7')) }};color:{{ $days>90?'#dc2626':($days>60?'#e8a838':($days>30?'#854d0e':'#166534')) }};">{{ $days }}d</span></td>
                </tr>
                @empty
                <tr>
                    <td colspan="12" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-boxes" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No inventory found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($inventory->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;display:flex;justify-content:space-between;align-items:center;"><span style="font-size:.78rem;color:#64748b;">{{ $inventory->firstItem() }}–{{ $inventory->lastItem() }} of {{ $inventory->total() }}</span>{{ $inventory->links('pagination::tailwind') }}</div>@endif
</div>
@endsection