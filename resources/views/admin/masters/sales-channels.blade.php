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
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group">
                        <label>Channel Type <span style="color:#dc2626;">*</span></label>
                        <select name="type" required>
                            <option value="">Select...</option>
                            <option value="b2b" {{ old('type')==='b2b'?'selected':'' }}>B2B</option>
                            <option value="b2c" {{ old('type')==='b2c'?'selected':'' }}>B2C</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Division <span style="color:#dc2626;">*</span></label>
                        <select name="division" required>
                            <option value="">Select...</option>
                            <option value="marketplace" {{ old('division')==='marketplace'?'selected':'' }}>Marketplace</option>
                            <option value="offline" {{ old('division')==='offline'?'selected':'' }}>Offline</option>
                            <option value="direct" {{ old('division')==='direct'?'selected':'' }}>Direct</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group">
                        <label>Platform URL</label>
                        <input type="url" name="platform_url" value="{{ old('platform_url') }}" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label>Commission (%)</label>
                        <input type="number" name="channel_commission" value="{{ old('channel_commission') }}" placeholder="0.00" step="0.01" min="0">
                    </div>
                </div>
                <div class="form-group">
                    <label>Company Codes</label>
                    <div style="display:flex;gap:1rem;">
                        @foreach(['2100'=>'USA','2200'=>'NL','2400'=>'EU'] as $code=>$name)
                        <label style="display:flex;align-items:center;gap:.3rem;font-size:.82rem;cursor:pointer;">
                            <input type="checkbox" name="company_codes[]" value="{{ $code }}" checked style="accent-color:#1e3a5f;">{{ $code }} {{ $name }}
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
        <div class="card-header"><h3><i class="fas fa-store" style="margin-right:.5rem;color:#e8a838;"></i> All Channels ({{ $channels->total() }})</h3></div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table" style="font-size:.78rem;">
                <thead><tr style="background:#f0f4f8;"><th>Channel</th><th>Type</th><th>Division</th><th>Commission</th><th>Companies</th><th>Status</th><th>Actions</th></tr></thead>
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
                                <div style="width:32px;height:32px;border-radius:6px;background:{{ $color }}15;display:flex;align-items:center;justify-content:center;">
                                    <i class="{{ $icon }}" style="color:{{ $color }};font-size:.8rem;"></i>
                                </div>
                                <div>
                                    <div style="font-weight:600;">{{ $ch->name }}</div>
                                    @if($ch->platform_url)<div style="font-size:.6rem;color:#94a3b8;">{{ parse_url($ch->platform_url, PHP_URL_HOST) }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td><span class="badge {{ $ch->type==='b2b'?'badge-info':'badge-warning' }}">{{ strtoupper($ch->type) }}</span></td>
                        <td><span class="badge badge-gray">{{ ucfirst($ch->division ?? '—') }}</span></td>
                        <td style="text-align:center;font-family:monospace;font-weight:600;">{{ $ch->channel_commission ? $ch->channel_commission.'%' : '—' }}</td>
                        <td>@foreach($ch->company_codes??[] as $c)<span style="display:inline-block;padding:.1rem .3rem;background:#f1f5f9;border-radius:3px;font-size:.65rem;font-weight:600;margin:.05rem;">{{ $c }}</span>@endforeach</td>
                        <td><span class="badge {{ $ch->is_active?'badge-success':'badge-gray' }}">{{ $ch->is_active?'Active':'Inactive' }}</span></td>
                        <td>
                            <button type="button" class="btn btn-outline btn-sm" onclick="editChannel({{ $ch->id }})" title="Edit"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>

                    {{-- Inline Edit Row (hidden by default) --}}
                    <tr id="edit-row-{{ $ch->id }}" style="display:none;background:#eff6ff;">
                        <td colspan="7" style="padding:.75rem;">
                            <form method="POST" action="{{ route('admin.sales-channels.update', $ch) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">
                                @csrf @method('PUT')
                                <div>
                                    <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;">Name</label>
                                    <input type="text" name="name" value="{{ $ch->name }}" required style="width:130px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;">
                                </div>
                                <div>
                                    <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;">Type</label>
                                    <select name="type" required style="padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;">
                                        <option value="b2b" {{ $ch->type==='b2b'?'selected':'' }}>B2B</option>
                                        <option value="b2c" {{ $ch->type==='b2c'?'selected':'' }}>B2C</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;">Division</label>
                                    <select name="division" required style="padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;">
                                        <option value="marketplace" {{ $ch->division==='marketplace'?'selected':'' }}>Marketplace</option>
                                        <option value="offline" {{ $ch->division==='offline'?'selected':'' }}>Offline</option>
                                        <option value="direct" {{ $ch->division==='direct'?'selected':'' }}>Direct</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;">URL</label>
                                    <input type="url" name="platform_url" value="{{ $ch->platform_url }}" placeholder="https://..." style="width:140px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;">
                                </div>
                                <div>
                                    <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;">Commission %</label>
                                    <input type="number" step="0.01" name="channel_commission" value="{{ $ch->channel_commission }}" placeholder="0" style="width:70px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;text-align:center;">
                                </div>
                                <div>
                                    <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;">Companies</label>
                                    <div style="display:flex;gap:.4rem;">
                                        @foreach(['2100','2200','2400'] as $code)
                                        <label style="display:flex;align-items:center;gap:.15rem;font-size:.72rem;cursor:pointer;">
                                            <input type="checkbox" name="company_codes[]" value="{{ $code }}" {{ in_array($code, $ch->company_codes ?? []) ? 'checked' : '' }} style="accent-color:#1e3a5f;">{{ $code }}
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;">Active</label>
                                    <label style="display:flex;align-items:center;gap:.3rem;cursor:pointer;">
                                        <input type="checkbox" name="is_active" value="1" {{ $ch->is_active ? 'checked' : '' }} style="accent-color:#16a34a;">
                                        <span style="font-size:.72rem;">{{ $ch->is_active ? 'Yes' : 'No' }}</span>
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save"></i> Save</button>
                                <button type="button" class="btn btn-outline btn-sm" onclick="editChannel({{ $ch->id }})"><i class="fas fa-times"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;">No sales channels yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($channels->hasPages())
            <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $channels->links() }}</div>
            @endif
        </div>
    </div>
</div>

<script>
function editChannel(id) {
    var row = document.getElementById('edit-row-' + id);
    row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>
@endsection
