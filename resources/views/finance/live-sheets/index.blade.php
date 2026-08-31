@extends('layouts.app')
@section('title', 'Live Sheets — SAP Codes')
@section('page-title', 'Live Sheets — SAP Code Management')

@section('content')
<div style="padding:.65rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Update SAP codes for products in each live sheet. Click "Edit SAP Codes" to enter codes.
</div>

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('finance.live-sheets') }}" style="display:flex;gap:.75rem;align-items:flex-end;">

            <input type="hidden" name="company_code" value="{{ request('company_code') ?? $activeCompany }}">
            <div style="min-width:120px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search LS number..."
                    style="padding:.3rem .6rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;width:160px;">
            </div>
            <div style="min-width:120px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Vendor</label>
                <select name="vendor_id" style="padding:.3rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;max-width:180px;"
                    onchange="this.form.submit()">
                    <option value="">All Vendors</option>
                    @foreach($vendors as $v)
                    <option value="{{ $v->id }}" {{ request('vendor_id') == $v->id ? 'selected' : '' }}>{{ $v->company_name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:120px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label><select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All</option>@foreach(['draft','submitted','locked'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach
                </select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('finance.live-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-list" style="margin-right:.5rem;color:#1e3a5f;"></i> Live Sheets</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Live Sheet #</th>
                    <th>Offer Sheet</th>
                    <th>Vendor</th>
                    <th>Company</th>
                    <th>Items</th>
                    <th>SAP Status</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($liveSheets as $ls)
                @php
                $totalItems = $ls->items->count();
                $sapFilled = $ls->items->filter(fn($i) => !empty(($i->product_details ?? [])['sap_code']))->count();
                @endphp
                <tr>
                    <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $ls->live_sheet_number }}</td>
                    <td style="font-size:.82rem;color:#64748b;">{{ $ls->offerSheet->offer_sheet_number ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $ls->vendor->company_name ?? '—' }}</td>
                    <td>{{ $ls->company_code }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $totalItems }}</td>
                    <td>
                        @if($totalItems > 0)
                        @php $pct = round(($sapFilled / $totalItems) * 100); @endphp
                        <div style="display:flex;align-items:center;gap:.4rem;">
                            <div style="flex:1;height:6px;background:#e2e8f0;border-radius:3px;">
                                <div style="height:100%;width:{{ $pct }}%;border-radius:3px;background:{{ $pct===100?'#16a34a':($pct>0?'#e8a838':'#dc2626') }};"></div>
                            </div>
                            <span style="font-size:.72rem;font-weight:600;color:{{ $pct===100?'#16a34a':'#64748b' }};">{{ $sapFilled }}/{{ $totalItems }}</span>
                        </div>
                        @endif
                    </td>
                    <td><span class="badge {{ ['draft'=>'badge-gray','submitted'=>'badge-warning','locked'=>'badge-success'][$ls->status] ?? 'badge-gray' }}">{{ ucfirst($ls->status) }}</span></td>
                    <td><a href="{{ route('finance.live-sheets.show', $ls) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Edit SAP Codes</a></td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;">No live sheets found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($liveSheets->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $liveSheets->links('pagination::tailwind') }}</div>@endif
</div>
@endsection