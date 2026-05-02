@extends('layouts.app')
@section('title', 'Pricing Sheets')
@section('page-title', 'Platform Pricing Sheets')

@section('content')
{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('cataloguing.pricing-sheets') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="flex:1;min-width:160px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="SKU or product name..." style="width:100%;padding:.4rem .55rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>🇮🇳 2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>🇺🇸 2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>🇳🇱 2200</option></select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Platform</label><select name="channel_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($channels as $ch)<option value="{{ $ch->id }}" {{ request('channel_id')==(string)$ch->id?'selected':'' }}>{{ $ch->name }}</option>@endforeach</select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">ASN</label><select name="asn_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($asns as $a)<option value="{{ $a->id }}" {{ request('asn_id')==(string)$a->id?'selected':'' }}>{{ $a->asn_number }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('cataloguing.pricing-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('cataloguing.pricing-sheets.download', request()->query()) }}" class="btn btn-secondary btn-sm" style="margin-left:auto;"><i class="fas fa-download"></i> Download Excel</a>
        </form>
    </div>
</div>

{{-- Pricing Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-invoice-dollar" style="margin-right:.5rem;color:#e8a838;"></i> Approved Pricing Sheets</h3><span style="font-size:.78rem;color:#64748b;">{{ $pricings->total() }} items</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.75rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="min-width:30px;">S.no</th>
                    <th style="min-width:100px;">Vendor SKU</th>
                    <th style="min-width:80px;">SAP Code</th>
                    <th style="min-width:130px;">Product Name</th>
                    <th style="min-width:90px;">Vendor</th>
                    <th style="min-width:70px;">Category</th>
                    <th>Material</th>
                    <th>Color</th>
                    <th style="text-align:center;">L</th>
                    <th style="text-align:center;">W</th>
                    <th style="text-align:center;">H</th>
                    <th style="text-align:center;">Wt</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">FOB</th>
                    <th style="text-align:right;background:#fefce8;">WSP</th>
                    <th>Platform</th>
                    <th style="text-align:right;background:#f0fdf4;">Selling</th>
                    <th style="text-align:center;">Margin</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pricings as $idx => $p)
                @php
                    $d = $p->pd ?? [];
                    $catalogue = $p->product ? $p->product->catalogues->where('sales_channel_id', $p->sales_channel_id)->first() : null;
                @endphp
                <tr>
                    <td style="text-align:center;color:#94a3b8;">{{ $pricings->firstItem() + $idx }}</td>
                    <td style="font-family:monospace;font-weight:600;">{{ $p->product->sku ?? '—' }}</td>
                    <td style="font-family:monospace;font-size:.72rem;color:#64748b;">{{ $p->product->sap_code ?? '—' }}</td>
                    <td>
                        <div style="font-weight:500;">{{ Str::limit($p->product->name ?? '—', 25) }}</div>
                        <div style="font-size:.6rem;color:#94a3b8;">{{ $d['barcode'] ?? '' }}</div>
                    </td>
                    <td style="font-size:.72rem;">{{ Str::limit($p->product->vendor->company_name ?? '—', 15) }}</td>
                    <td style="font-size:.72rem;">{{ $p->product->category->name ?? $d['category'] ?? '—' }}</td>
                    <td style="font-size:.72rem;">{{ $d['material'] ?? '—' }}</td>
                    <td style="font-size:.72rem;">{{ $d['color'] ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-size:.72rem;">{{ $d['product_length'] ?? $d['length'] ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-size:.72rem;">{{ $d['product_width'] ?? $d['width'] ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-size:.72rem;">{{ $d['product_height'] ?? $d['height'] ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-size:.72rem;">{{ $d['product_weight'] ?? $d['weight_per_unit'] ?? '—' }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $d['final_qty'] ?? '—' }}</td>
                    <td style="text-align:right;font-family:monospace;">${{ number_format(floatval($p->cost_price ?? $p->fob_price ?? 0), 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:700;background:#fefce8;">${{ number_format(floatval($p->wsp_price ?? $d['wsp'] ?? 0), 2) }}</td>
                    <td><span class="badge badge-info" style="font-size:.62rem;">{{ $p->salesChannel->name ?? '—' }}</span></td>
                    <td style="text-align:right;font-family:monospace;font-weight:700;color:#166534;background:#f0fdf4;">${{ number_format(floatval($p->selling_price ?? 0), 2) }}</td>
                    <td style="text-align:center;"><span style="font-weight:700;font-size:.78rem;color:{{ floatval($p->margin_percent) >= 30 ? '#16a34a' : (floatval($p->margin_percent) >= 15 ? '#e8a838' : '#dc2626') }};">{{ number_format(floatval($p->margin_percent), 1) }}%</span></td>
                    <td>
                        @if($catalogue)
                            <span class="badge {{ $catalogue->listing_status==='listed'?'badge-success':($catalogue->listing_status==='pending'?'badge-warning':'badge-gray') }}" style="font-size:.58rem;">{{ ucfirst($catalogue->listing_status) }}</span>
                        @else
                            <span class="badge badge-gray" style="font-size:.58rem;">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="19" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-file-invoice-dollar" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No approved pricing sheets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pricings->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $pricings->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
