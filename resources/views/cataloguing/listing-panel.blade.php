@extends('layouts.app')
@section('title', 'Listing Panel')
@section('page-title', 'Platform Listing Panel')

@section('content')
{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('cataloguing.listing-panel') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="flex:1;min-width:180px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="SKU or product name..." style="width:100%;padding:.4rem .65rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"></div>
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option></select></div>
            <div style="min-width:130px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Category</label><select name="category_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($categories as $cat)<option value="{{ $cat->id }}" {{ request('category_id')==(string)$cat->id?'selected':'' }}>{{ $cat->name }}</option>@endforeach</select></div>
            <div style="min-width:130px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Listing</label><select name="listing_filter" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="fully_listed" {{ request('listing_filter')==='fully_listed'?'selected':'' }}>Fully Listed</option><option value="partially_listed" {{ request('listing_filter')==='partially_listed'?'selected':'' }}>Partially Listed</option><option value="not_listed" {{ request('listing_filter')==='not_listed'?'selected':'' }}>Not Listed</option></select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('cataloguing.listing-panel') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

{{-- Info --}}
<div style="padding:.6rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1rem;font-size:.78rem;color:#1e40af;display:flex;align-items:center;gap:.4rem;">
    <i class="fas fa-info-circle"></i> Check the platforms where each SKU is listed. Update listing status, listing URL, and Shopify store URL, then click "Save All Changes".
</div>

{{-- Listing Form --}}
<form method="POST" action="{{ route('cataloguing.listings.update') }}" id="listingForm">
    @csrf
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list" style="margin-right:.5rem;color:#1e3a5f;"></i> Platform Listing Status</h3>
            <div style="display:flex;gap:.4rem;align-items:center;">
                <span style="font-size:.78rem;color:#64748b;">{{ $products->total() }} products</span>
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Save all listing changes?')"><i class="fas fa-save"></i> Save All Changes</button>
            </div>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Vendor</th>
                        @foreach($channels as $ch)
                        <th style="text-align:center;font-size:.65rem;min-width:90px;">
                            @php $icons = ['Amazon'=>'fab fa-amazon','Wayfair'=>'fas fa-couch','Shopify'=>'fab fa-shopify','Faire'=>'fas fa-store']; @endphp
                            <i class="{{ $icons[$ch->name] ?? 'fas fa-store' }}" style="display:block;margin-bottom:.15rem;color:#e8a838;font-size:.75rem;"></i>
                            {{ $ch->name }}
                        </th>
                        @endforeach
                        <th>Shopify URL</th>
                    </tr>
                </thead>
                <tbody>
                    @php $listIdx = 0; @endphp
                    @forelse($products as $product)
                    <tr>
                        <td style="font-family:monospace;font-weight:600;font-size:.82rem;">{{ $product->sku }}</td>
                        <td>
                            <div style="font-size:.82rem;font-weight:500;">{{ Str::limit($product->name, 35) }}</div>
                            <div style="font-size:.65rem;color:#94a3b8;">{{ $product->category->name ?? '' }}</div>
                        </td>
                        <td style="font-size:.78rem;color:#64748b;">{{ $product->vendor->company_name ?? '—' }}</td>
                        @foreach($channels as $ch)
                        @php
                            $catalogue = $product->catalogues->where('sales_channel_id', $ch->id)->first();
                            $isListed = $catalogue && $catalogue->listing_status === 'listed';
                        @endphp
                        <td style="text-align:center;">
                            <input type="hidden" name="listings[{{ $listIdx }}][product_id]" value="{{ $product->id }}">
                            <input type="hidden" name="listings[{{ $listIdx }}][sales_channel_id]" value="{{ $ch->id }}">
                            <input type="hidden" name="listings[{{ $listIdx }}][company_code]" value="{{ $product->company_code }}">
                            <label style="display:flex;flex-direction:column;align-items:center;gap:.15rem;cursor:pointer;">
                                <input type="hidden" name="listings[{{ $listIdx }}][listing_status]" value="not_listed">
                                <input type="checkbox" name="listings[{{ $listIdx }}][listing_status]" value="listed"
                                    {{ $isListed ? 'checked' : '' }}
                                    style="width:18px;height:18px;accent-color:#16a34a;"
                                    onchange="this.previousElementSibling.disabled=this.checked;">
                                @if($catalogue && $catalogue->listing_url)
                                    <a href="{{ $catalogue->listing_url }}" target="_blank" style="font-size:.55rem;color:#1e40af;">view</a>
                                @endif
                            </label>
                        </td>
                        @php $listIdx++; @endphp
                        @endforeach
                        <td>
                            @php $shopifyCat = $product->catalogues->whereNotNull('shopify_url')->first(); @endphp
                            <input type="text" value="{{ $shopifyCat->shopify_url ?? '' }}" placeholder="Shopify URL..." style="width:140px;padding:.25rem .4rem;border:1px solid #e2e8f0;border-radius:5px;font-size:.72rem;">
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ 3 + $channels->count() + 1 }}" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-list" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No products found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($products->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;display:flex;justify-content:space-between;align-items:center;"><span style="font-size:.78rem;color:#64748b;">{{ $products->firstItem() }}–{{ $products->lastItem() }} of {{ $products->total() }}</span>{{ $products->links('pagination::tailwind') }}</div>@endif
    </div>

    <div style="margin-top:1rem;display:flex;justify-content:flex-end;"><button type="submit" class="btn btn-primary" onclick="return confirm('Save all listing changes?')"><i class="fas fa-save" style="margin-right:.3rem;"></i> Save All Changes</button></div>
</form>

{{-- Legend --}}
<div style="margin-top:.75rem;display:flex;gap:1.5rem;font-size:.75rem;color:#64748b;">
    <span><span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:#16a34a;margin-right:.3rem;vertical-align:middle;"></span> Listed (checked)</span>
    <span><span style="display:inline-block;width:14px;height:14px;border-radius:3px;background:#e2e8f0;margin-right:.3rem;vertical-align:middle;"></span> Not Listed (unchecked)</span>
</div>
@endsection
