@extends('layouts.app')
@section('title', 'Listing Panel')
@section('page-title', 'Platform Listing Panel')

@section('content')


{{-- Bulk Listing Upload/Download Section --}}
{{-- Add this at the top of cataloguing/listing-panel.blade.php, after the filters --}}

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-file-excel" style="margin-right:.5rem;color:#16a34a;"></i> Bulk Listing Update</h3>
        <a href="{{ route('cataloguing.listing-panel.download-template') }}" class="btn btn-outline btn-sm" style="border-color:#16a34a;color:#16a34a;">
            <i class="fas fa-download"></i> Download Template & Status Report
        </a>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:1.5rem;align-items:flex-start;">
            {{-- Instructions --}}
            <div style="flex:1;font-size:.75rem;color:#64748b;line-height:1.6;">
                <div style="font-weight:700;color:#0d1b2a;margin-bottom:.3rem;">How it works:</div>
                <div><span style="display:inline-block;padding:.1rem .4rem;background:#f0fdf4;border-radius:3px;color:#16a34a;font-weight:700;font-size:.68rem;">Yes</span> — List SKU on the channel (creates if not exists)</div>
                <div><span style="display:inline-block;padding:.1rem .4rem;background:#fef2f2;border-radius:3px;color:#dc2626;font-weight:700;font-size:.68rem;">No</span> — Unlist SKU from the channel</div>
                <div><span style="display:inline-block;padding:.1rem .4rem;background:#f1f5f9;border-radius:3px;color:#94a3b8;font-weight:700;font-size:.68rem;">Blank</span> — No change</div>
            </div>

            {{-- Upload Form --}}
            <form method="POST" action="{{ route('cataloguing.listing-panel.upload') }}" enctype="multipart/form-data" style="display:flex;gap:.5rem;align-items:flex-end;">
                @csrf
                <div>
                    <label style="font-size:.68rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Upload Completed Template</label>
                    <input type="file" name="listing_file" required accept=".xlsx,.xls,.csv" style="font-size:.78rem;">
                </div>
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Upload and update listings?')">
                    <i class="fas fa-upload"></i> Upload
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Upload Results --}}
@if(session('listing_errors') && count(session('listing_errors')) > 0)
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3 style="color:#dc2626;">Upload Errors</h3>
    </div>
    <div class="card-body" style="padding:.5rem 1rem;">
        <div style="max-height:150px;overflow-y:auto;font-size:.72rem;">
            @foreach(session('listing_errors') as $err)
            <div style="padding:.15rem 0;border-bottom:1px solid #fecaca;color:#dc2626;">{{ $err }}</div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('cataloguing.listing-panel') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="flex:1;min-width:180px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="SKU or product name..." style="width:100%;padding:.4rem .65rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"></div>
            <!-- <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option></select></div> -->
            <div style="min-width:130px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Category</label><select name="category_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>@foreach($categories as $cat)<option value="{{ $cat->id }}" {{ request('category_id')==(string)$cat->id?'selected':'' }}>{{ $cat->name }}</option>@endforeach
                </select></div>
            <div style="min-width:130px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Listing</label>
                <select name="listing_status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All Status</option>
                    <option value="listed" {{ request('listing_status') === 'listed' ? 'selected' : '' }}>Listed</option>
                    <option value="unlisted" {{ request('listing_status') === 'unlisted' ? 'selected' : '' }}>Not Listed</option>
                </select>
            </div>
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
                    @foreach($products as $p)
                    <tr>
                        <td style="font-family:monospace;font-weight:700;">{{ $p->sku }}</td>
                        <td>{{ Str::limit($p->name, 30) }}</td>
                        <td style="font-size:.72rem;">{{ $p->vendor->company_name ?? '—' }}</td>
                        @foreach($channels as $ch)
                        @php $cat = $p->catalogues->firstWhere('sales_channel_id', $ch->id); @endphp
                        <td style="text-align:center;">
                            @if($cat && $cat->listing_status === 'listed')
                            <span style="color:#16a34a;font-weight:700;" title="Listed on {{ $ch->name }}"><i class="fas fa-check-circle"></i></span>
                            @elseif($cat && $cat->listing_status === 'unlisted')
                            <span style="color:#dc2626;" title="Unlisted from {{ $ch->name }}"><i class="fas fa-times-circle"></i></span>
                            @else
                            <span style="color:#d1d5db;" title="Not configured"><i class="fas fa-minus-circle"></i></span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
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