@extends('layouts.app')
@section('title', 'SKU Dashboard')
@section('page-title', 'SKU Listing Dashboard')

@section('content')
{{-- Totals --}}
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card"><div class="kpi-label">Total Products</div><div class="kpi-value">{{ $totals['total_products'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;"><div class="kpi-label">SKUs Listed</div><div class="kpi-value" style="color:#16a34a;">{{ $totals['total_listed'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #1e40af;"><div class="kpi-label">Platforms Active</div><div class="kpi-value" style="color:#1e40af;">{{ $totals['platforms_covered'] }} / {{ $totals['total_platforms'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Coverage</div><div class="kpi-value" style="color:#e8a838;">{{ $totals['total_products'] > 0 ? round(($totals['total_listed'] / $totals['total_products']) * 100) : 0 }}%</div></div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('cataloguing.sku-dashboard') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                <option value="2000" {{ $companyCode==='2000'?'selected':'' }}>🇮🇳 2000</option><option value="2100" {{ $companyCode==='2100'?'selected':'' }}>🇺🇸 2100</option><option value="2200" {{ $companyCode==='2200'?'selected':'' }}>🇳🇱 2200</option>
            </select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Platform</label><select name="channel_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All Platforms</option>@foreach($channels as $ch)<option value="{{ $ch->id }}" {{ $channelId==(string)$ch->id?'selected':'' }}>{{ $ch->name }}</option>@endforeach</select></div>
            <div style="min-width:130px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Category</label><select name="category_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All Categories</option>@foreach($categories as $cat)<option value="{{ $cat->id }}" {{ $categoryId==(string)$cat->id?'selected':'' }}>{{ $cat->name }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('cataloguing.sku-dashboard') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

{{-- Per-Platform Stats --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-chart-bar" style="margin-right:.5rem;color:#1e3a5f;"></i> SKUs per Platform</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Platform</th><th style="width:40%;">Listing Progress</th><th style="text-align:center;">Listed</th><th style="text-align:center;">Pending</th><th style="text-align:center;">Not Listed</th><th style="text-align:center;">Total</th><th style="text-align:center;">Coverage</th></tr></thead>
            <tbody>
                @foreach($channelStats as $cs)
                @php $pct = $cs['total'] > 0 ? round(($cs['listed'] / $cs['total']) * 100) : 0; @endphp
                <tr>
                    <td>
                        @php $icons = ['Amazon'=>'fab fa-amazon','Wayfair'=>'fas fa-couch','Shopify'=>'fab fa-shopify','Faire'=>'fas fa-store','GIGA'=>'fas fa-globe','TICA'=>'fas fa-store-alt','Coons'=>'fas fa-shopping-bag']; @endphp
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <i class="{{ $icons[$cs['channel']->name] ?? 'fas fa-store' }}" style="color:#e8a838;font-size:.9rem;width:20px;text-align:center;"></i>
                            <span style="font-weight:600;">{{ $cs['channel']->name }}</span>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <div style="flex:1;height:24px;background:#f1f5f9;border-radius:5px;overflow:hidden;position:relative;">
                                @if($cs['total'] > 0)
                                @php $listedPct = round(($cs['listed'] / $cs['total']) * 100); $pendingPct = round(($cs['pending'] / $cs['total']) * 100); @endphp
                                <div style="position:absolute;left:0;top:0;height:100%;width:{{ $listedPct }}%;background:#16a34a;"></div>
                                <div style="position:absolute;left:{{ $listedPct }}%;top:0;height:100%;width:{{ $pendingPct }}%;background:#e8a838;"></div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td style="text-align:center;font-weight:700;color:#16a34a;">{{ $cs['listed'] }}</td>
                    <td style="text-align:center;font-weight:600;color:#e8a838;">{{ $cs['pending'] }}</td>
                    <td style="text-align:center;color:#94a3b8;">{{ $cs['not_listed'] }}</td>
                    <td style="text-align:center;font-weight:700;">{{ $cs['total'] }}</td>
                    <td style="text-align:center;"><span style="font-weight:800;font-size:.9rem;color:{{ $pct >= 80 ? '#16a34a' : ($pct >= 50 ? '#e8a838' : '#dc2626') }};">{{ $pct }}%</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Per-Category Breakdown (when platform filter selected) --}}
@if($channelId && $categoryStats->count() > 0)
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-tags" style="margin-right:.5rem;color:#7c3aed;"></i> Category Breakdown — {{ $channels->find($channelId)->name ?? 'Selected Platform' }}</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Category</th><th style="width:40%;">Progress</th><th style="text-align:center;">Listed</th><th style="text-align:center;">Pending</th><th style="text-align:center;">Total</th></tr></thead>
            <tbody>
                @foreach($categoryStats as $cs)
                @php $pct = $cs['total'] > 0 ? round(($cs['listed'] / $cs['total']) * 100) : 0; @endphp
                <tr>
                    <td style="font-weight:600;">{{ $cs['category']->name }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <div style="flex:1;height:16px;background:#f1f5f9;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;width:{{ $pct }}%;background:#16a34a;border-radius:4px;"></div>
                            </div>
                            <span style="font-size:.72rem;font-weight:600;color:#64748b;">{{ $pct }}%</span>
                        </div>
                    </td>
                    <td style="text-align:center;font-weight:700;color:#16a34a;">{{ $cs['listed'] }}</td>
                    <td style="text-align:center;color:#e8a838;">{{ $cs['pending'] }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $cs['total'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@elseif(!$channelId)
<div style="padding:.75rem 1rem;background:#fefce8;border-radius:8px;border:1px solid #fde68a;font-size:.82rem;color:#854d0e;"><i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Select a platform in the filter above to see category-level breakdown.</div>
@endif

{{-- Legend --}}
<div style="margin-top:.75rem;display:flex;gap:1.5rem;font-size:.75rem;color:#64748b;">
    <span><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#16a34a;margin-right:.3rem;vertical-align:middle;"></span> Listed</span>
    <span><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#e8a838;margin-right:.3rem;vertical-align:middle;"></span> Pending</span>
    <span><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#f1f5f9;margin-right:.3rem;vertical-align:middle;"></span> Not Listed</span>
</div>
@endsection
