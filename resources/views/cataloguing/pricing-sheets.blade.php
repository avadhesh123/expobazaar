@extends('layouts.app')
@section('title', 'Pricing Sheets')
@section('page-title', 'Platform Pricing Sheets')

@section('content')
{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('cataloguing.pricing-sheets') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="flex:1;min-width:160px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="SKU or product name..." style="width:100%;padding:.4rem .55rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
            </div>

            <div style="min-width:140px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Platform</label>
                <select name="channel_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All Platforms</option>
                    @foreach($channels as $ch)
                    <option value="{{ $ch->id }}" {{ request('channel_id')==(string)$ch->id?'selected':'' }}>{{ $ch->name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="min-width:140px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">ASN</label>
                <select name="asn_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All ASN</option>
                    @foreach($asns as $a)
                    <option value="{{ $a->id }}" {{ request('asn_id')==(string)$a->id?'selected':'' }}>{{ $a->asn_number }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('cataloguing.pricing-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('cataloguing.pricing-sheets.download', request()->query()) }}" class="btn btn-secondary btn-sm" style="margin-left:auto;"><i class="fas fa-download"></i> Download Excel</a>
        </form>
    </div>
</div>

{{-- Pricing Table - One Row Per Product --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-invoice-dollar" style="margin-right:.5rem;color:#e8a838;"></i> Approved Pricing Sheets</h3>
        <span style="font-size:.78rem;color:#64748b;">{{ $pricings->total() }} products</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.75rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="min-width:40px;">S.no</th>
                    <th style="min-width:110px;">Vendor SKU</th>
                    <th style="min-width:80px;">SAP</th>
                    <th style="min-width:180px;">Product Name</th>
                    <th style="min-width:100px;">Vendor</th>
                    <th style="min-width:70px;text-align:right;">FOB</th>
                    <th style="min-width:70px;text-align:right;">WSP</th>
                    <th style="min-width:70px;text-align:right;">Last Mile</th>
                    <th style="min-width:80px;text-align:right;background:#f0fdf4;">Retail</th>

                    @foreach($channels as $ch)
                    <th style="min-width:110px;text-align:right;background:#fefce8;">
                        {{ $ch->name }}
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($pricings as $productId => $productGroup)
                @php
                $first = $productGroup->first();
                $product = $first->product ?? null;
                @endphp
                <tr>
                    <td style="text-align:center;color:#94a3b8;">{{ $pricings->firstItem() + $loop->index }}</td>
                    <td style="font-family:monospace;font-weight:600;">{{ $product?->sku ?? '—' }}</td>
                    <td style="font-family:monospace;color:#64748b;">{{ $product?->sap_code ?? '—' }}</td>
                    <td>{{ Str::limit($product?->name ?? '—', 45) }}</td>
                    <td>{{ Str::limit($product?->vendor?->company_name ?? '—', 18) }}</td>
                    <td style="text-align:right;">{{$activeCurrencySymbol}}{{ number_format($first->fob_price ?? 0, 2) }}</td>
                    <td style="text-align:right;font-weight:600;">{{$activeCurrencySymbol}}{{ number_format($first->wsp_price ?? 0, 2) }}</td>
                    <td style="text-align:right;">{{$activeCurrencySymbol}}{{ number_format($first->last_mile ?? 0, 2) }}</td>
                    <td style="text-align:right;font-weight:700;background:#f0fdf4;">{{$activeCurrencySymbol}}{{ number_format($first->retail_price ?? 0, 2) }}</td>

                    {{-- Channel Prices --}}
                    @foreach($channels as $ch)
                    @php
                    $pricing = $productGroup->first(fn($p) => $p->sales_channel_id == $ch->id); 
                     @endphp

                    <td style="text-align:right;font-family:monospace;font-weight:600;">
                        @if($pricing)
                        {{ $activeCurrencySymbol }}{{ number_format($pricing->platform_price  ?? $pricing->channel_price ?? 0, 2) }}
                        @else
                        <span style="color:#94a3b8;">—</span>
                        @endif
                    </td>
                    @endforeach
                </tr>
                @empty
                <tr>
                    <td colspan="20" style="text-align:center;padding:3rem;color:#94a3b8;">
                        No approved pricing sheets found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pricings->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">
        {{ $pricings->links() }}
    </div>
    @endif
</div>
@endsection