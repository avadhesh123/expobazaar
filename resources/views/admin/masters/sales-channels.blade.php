@extends('layouts.app')
@section('title', 'Sales Channel Master')
@section('page-title', 'Sales Channel Master')

@section('content')
<div class="grid-2">
    {{-- CREATE --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-plus-circle" style="margin-right:.5rem;color:#2d6a4f;"></i> Add Sales Channel</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.sales-channels.store') }}">
                @csrf
                <div class="form-group">
                    <label>Channel Name <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Amazon, Wayfair">
                    @error('name')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Channel Type <span style="color:#dc2626;">*</span></label>
                    <select name="type" required>
                        <option value="">Select...</option>
                        <option value="marketplace" {{ old('type')==='marketplace'?'selected':'' }}>Marketplace</option>
                        <option value="offline" {{ old('type')==='offline'?'selected':'' }}>Offline</option>
                        <option value="direct" {{ old('type')==='direct'?'selected':'' }}>Direct</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Platform URL</label>
                    <input type="url" name="platform_url" value="{{ old('platform_url') }}" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label>Company Codes</label>
                    <div style="display:flex;gap:1rem;">
                        @foreach(['2000'=>'India','2100'=>'USA','2200'=>'NL'] as $code=>$name)
                        <label style="display:flex;align-items:center;gap:.3rem;font-size:.82rem;cursor:pointer;">
                            <input type="checkbox" name="company_codes[]" value="{{ $code }}" checked style="accent-color:#1e3a5f;">{{ $code }}
                        </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right:.3rem;"></i> Add Channel</button>
            </form>
        </div>
    </div>

    {{-- LIST --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-store" style="margin-right:.5rem;color:#e8a838;"></i> All Channels ({{ $channels->count() }})</h3></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Channel</th><th>Type</th><th>Companies</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($channels as $ch)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                @php
                                    $icons = ['amazon'=>'fab fa-amazon','shopify'=>'fab fa-shopify','wayfair'=>'fas fa-couch','faire'=>'fas fa-handshake','giga'=>'fas fa-bolt'];
                                    $icon = $icons[strtolower($ch->slug)] ?? ($ch->type==='offline'?'fas fa-store-alt':'fas fa-globe');
                                    $colors = ['amazon'=>'#ff9900','shopify'=>'#96bf48','wayfair'=>'#7b2d8e','faire'=>'#1a1a2e','giga'=>'#e8a838'];
                                    $color = $colors[strtolower($ch->slug)] ?? '#64748b';
                                @endphp
                                <div style="width:36px;height:36px;border-radius:8px;background:{{ $color }}15;display:flex;align-items:center;justify-content:center;">
                                    <i class="{{ $icon }}" style="color:{{ $color }};font-size:.9rem;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600;color:#0d1b2a;">{{ $ch->name }}</div>
                                    @if($ch->platform_url)<div style="font-size:.68rem;color:#94a3b8;">{{ parse_url($ch->platform_url, PHP_URL_HOST) }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td><span class="badge {{ $ch->type==='marketplace'?'badge-info':($ch->type==='offline'?'badge-warning':'badge-gray') }}">{{ ucfirst($ch->type) }}</span></td>
                        <td>@foreach($ch->company_codes??[] as $c)<span style="display:inline-block;padding:.1rem .3rem;background:#f1f5f9;border-radius:3px;font-size:.68rem;font-weight:600;margin:.05rem;">{{ $c }}</span>@endforeach</td>
                        <td><span class="badge {{ $ch->is_active?'badge-success':'badge-gray' }}">{{ $ch->is_active?'Active':'Inactive' }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;padding:2rem;color:#94a3b8;">No sales channels yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
