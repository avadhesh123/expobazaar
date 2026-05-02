@extends('layouts.app')
@section('title', 'Pricing Sheets')
@section('page-title', 'Platform Pricing Sheets')

@section('content')
{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('cataloguing.pricing-sheets') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>🇮🇳 2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>🇺🇸 2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>🇳🇱 2200</option></select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Platform</label><select name="channel_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($channels as $ch)<option value="{{ $ch->id }}" {{ request('channel_id')==(string)$ch->id?'selected':'' }}>{{ $ch->name }}</option>@endforeach</select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">ASN</label><select name="asn_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($asns as $a)<option value="{{ $a->id }}" {{ request('asn_id')==(string)$a->id?'selected':'' }}>{{ $a->asn_number }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('cataloguing.pricing-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <div style="margin-left:auto;display:flex;gap:.4rem;">
                <a href="{{ route('cataloguing.pricing-sheets.download', request()->query()) }}" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Download CSV</a>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('uploadPanel').style.display=document.getElementById('uploadPanel').style.display==='none'?'block':'none'"><i class="fas fa-upload"></i> Upload Catalogue</button>
            </div>
        </form>
    </div>
</div>

{{-- Upload Panel --}}
<div id="uploadPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#e8a838;">
        <div class="card-body">
            <div style="font-size:.88rem;font-weight:700;color:#0d1b2a;margin-bottom:.75rem;">Upload Completed Catalogue Sheet</div>
            <form method="POST" action="{{ route('cataloguing.catalogue.upload') }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
                @csrf
                <div class="form-group" style="margin-bottom:0;"><label>Company Code *</label><select name="company_code" required><option value="2000">🇮🇳 2000 – India</option><option value="2100" selected>🇺🇸 2100 – USA</option><option value="2200">🇳🇱 2200 – NL</option></select></div>
                <div class="form-group" style="margin-bottom:0;"><label>Catalogue File (CSV/XLSX) *</label><input type="file" name="catalogue_file" required accept=".csv,.xlsx"></div>
                <button type="submit" class="btn btn-secondary"><i class="fas fa-upload" style="margin-right:.3rem;"></i> Upload</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('uploadPanel').style.display='none'">Cancel</button>
            </form>
            <div style="margin-top:.5rem;font-size:.72rem;color:#64748b;"><i class="fas fa-info-circle"></i> Download the pricing sheet first, update catalogue details (listing SKU, listing URL, Shopify URL), then upload the completed file.</div>
        </div>
    </div>
</div>

{{-- Pricing Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-invoice-dollar" style="margin-right:.5rem;color:#e8a838;"></i> Approved Pricing Sheets</h3><span style="font-size:.78rem;color:#64748b;">{{ $pricings->total() }} items</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>SKU</th><th>Product</th><th>Vendor</th><th>Platform</th><th>ASN</th><th>Company</th><th>Cost</th><th>Selling</th><th>Margin</th><th>Listing Status</th></tr></thead>
            <tbody>
                @forelse($pricings as $p)
                @php $catalogue = $p->product->catalogues->where('sales_channel_id', $p->sales_channel_id)->first(); @endphp
                <tr>
                    <td style="font-family:monospace;font-weight:600;font-size:.82rem;">{{ $p->product->sku ?? '—' }}</td>
                    <td><div style="font-size:.82rem;font-weight:500;">{{ $p->product->name ?? '—' }}</div><div style="font-size:.65rem;color:#94a3b8;">{{ $p->product->category->name ?? '' }}</div></td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $p->product->vendor->company_name ?? '—' }}</td>
                    <td><span class="badge badge-info">{{ $p->salesChannel->name ?? '—' }}</span></td>
                    <td style="font-size:.75rem;font-family:monospace;">{{ $p->asn->asn_number ?? '—' }}</td>
                    <td>{{ $p->company_code }}</td>
                    <td style="font-family:monospace;">${{ number_format($p->cost_price, 2) }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">${{ number_format($p->selling_price, 2) }}</td>
                    <td><span style="font-weight:700;color:{{ $p->margin_percent >= 30 ? '#16a34a' : ($p->margin_percent >= 15 ? '#e8a838' : '#dc2626') }};">{{ number_format($p->margin_percent, 1) }}%</span></td>
                    <td>
                        @if($catalogue)
                            <span class="badge {{ $catalogue->listing_status==='listed'?'badge-success':($catalogue->listing_status==='pending'?'badge-warning':'badge-gray') }}">{{ ucfirst($catalogue->listing_status) }}</span>
                            @if($catalogue->listing_url)<a href="{{ $catalogue->listing_url }}" target="_blank" style="font-size:.62rem;color:#1e40af;display:block;margin-top:.1rem;">View Listing</a>@endif
                        @else
                            <span class="badge badge-gray">Not Catalogued</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-file-invoice-dollar" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No approved pricing sheets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pricings->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $pricings->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
