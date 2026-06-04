@extends('layouts.app')
@section('title', 'Warehouse Master')
@section('page-title', 'Warehouse Master')

@section('content')
{{-- CREATE WAREHOUSE --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-plus-circle" style="margin-right:.5rem;color:#2d6a4f;"></i> Add Warehouse</h3>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('warehouseForm').style.display=document.getElementById('warehouseForm').style.display==='none'?'block':'none'">
            <i class="fas fa-chevron-down"></i> Toggle Form
        </button>
    </div>
    <div id="warehouseForm" style="display:none;">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.warehouses.store') }}">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;">
                    <div class="form-group"><label>Warehouse Name <span style="color:#dc2626;">*</span></label><input type="text" name="name" required placeholder="USA Main Warehouse"></div>
                    <div class="form-group"><label>Code <span style="color:#dc2626;">*</span></label><input type="text" name="code" required placeholder="WH-US-001">@error('code')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror</div>
                    <div class="form-group"><label>Company Code <span style="color:#dc2626;">*</span></label>
                        <select name="company_code" required><option value="">Select...</option><option value="2400">2400 – UK</option><option value="2100">2100 – USA</option><option value="2200">2200 – EU</option></select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:.75rem;">
                    <div class="form-group"><label>Country <span style="color:#dc2626;">*</span></label><input type="text" name="country" required></div>
                    <div class="form-group"><label>City</label><input type="text" name="city"></div>
                    <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person"></div>
                    <div class="form-group"><label>Contact Phone</label><input type="text" name="contact_phone"></div>
                </div>
                <div class="form-group"><label>Address</label><textarea name="address" rows="2"></textarea></div>             
                <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right:.3rem;"></i> Create Warehouse</button>
            </form>
        </div>
    </div>
</div>

{{-- WAREHOUSE LIST --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-warehouse" style="margin-right:.5rem;color:#e8a838;"></i> All Warehouses ({{ $warehouses->count() }})</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr><th>Warehouse</th><th>Company</th><th>Location</th><th>Contact</th><th>Sub-Locations</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($warehouses as $wh)
                <tr>
                    <td>
                        <div style="font-weight:600;color:#0d1b2a;">{{ $wh->name }}</div>
                        <div style="font-size:.7rem;color:#94a3b8;font-family:monospace;">{{ $wh->code }}</div>
                    </td>
                    <td>
                        @php $cc = ['2400'=>['uk','#dcfce7'],'2100'=>['🇺🇸','#dbeafe'],'2200'=>['eu','#fef3c7']]; @endphp
                        <span style="padding:.2rem .45rem;background:{{ $cc[$wh->company_code][1]??'#f1f5f9' }};border-radius:5px;font-size:.78rem;font-weight:600;">
                            {{ $cc[$wh->company_code][0]??'' }} {{ $wh->company_code }}
                        </span>
                    </td>
                    <td>
                        <div style="font-size:.82rem;">{{ $wh->city }}{{ $wh->city && $wh->country ? ', ' : '' }}{{ $wh->country }}</div>
                        @if($wh->address)<div style="font-size:.68rem;color:#94a3b8;">{{ Str::limit($wh->address, 30) }}</div>@endif
                    </td>
                    <td>
                        <div style="font-size:.8rem;">{{ $wh->contact_person ?? '—' }}</div>
                        @if($wh->contact_phone)<div style="font-size:.68rem;color:#94a3b8;">{{ $wh->contact_phone }}</div>@endif
                        <td>
                        @if($wh->subWarehouses->count() > 0)
                            <div style="font-size:.78rem;font-weight:600;">{{ $wh->subWarehouses->count() }} sub-WH</div>
                        @endif
                        @if($wh->subLocations->count() > 0)
                            <div style="font-size:.72rem;color:#64748b;">{{ $wh->subLocations->count() }} locations</div>
                        @endif
                        @if($wh->subWarehouses->count() === 0 && $wh->subLocations->count() === 0)
                            <span style="color:#94a3b8;font-size:.78rem;">—</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $wh->is_active?'badge-success':'badge-gray' }}">{{ $wh->is_active?'Active':'Inactive' }}</span></td>
                </tr>

                {{-- Sub-warehouses --}}
                @foreach($wh->subWarehouses as $sub)
                <tr style="background:#f8fafc;">
                    <td style="padding-left:2.5rem;">
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <i class="fas fa-level-up-alt fa-rotate-90" style="color:#d1d5db;font-size:.7rem;"></i>
                            <div>
                                <div style="font-weight:500;color:#475569;font-size:.82rem;">{{ $sub->name }}</div>
                                <div style="font-size:.68rem;color:#94a3b8;font-family:monospace;">{{ $sub->code }}</div>
                            </div>
                        </div>
                    </td>
                    <td colspan="8" style="font-size:.78rem;color:#64748b;">Sub-warehouse of {{ $wh->name }}</td>
                    <td><span class="badge {{ $sub->is_active?'badge-success':'badge-gray' }}" style="font-size:.6rem;">{{ $sub->is_active?'Active':'Off' }}</span></td>
                </tr>
                @endforeach
                @empty
                <tr><td colspan="10" style="text-align:center;padding:2rem;color:#94a3b8;">No warehouses configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
