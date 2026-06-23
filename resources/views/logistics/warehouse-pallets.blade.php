@extends('layouts.app')
@section('title', 'Warehouse Pallet Details')
@section('page-title', 'Warehouse Pallet Details')

@section('content')

{{-- KPIs --}}
<div style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <div class="kpi-card" style="flex:1;border-left:3px solid #1e40af;"><div class="kpi-label">Warehouses</div><div class="kpi-value" style="color:#1e40af;">{{ $stats['warehouses_count'] }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #16a34a;"><div class="kpi-label">Today's Pallets</div><div class="kpi-value" style="color:#16a34a;">{{ number_format($stats['today_pallets']) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #7c3aed;"><div class="kpi-label">Entries Today</div><div class="kpi-value" style="color:#7c3aed;">{{ $stats['today_entries'] }} / {{ $stats['warehouses_count'] }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #64748b;"><div class="kpi-label">Total Entries</div><div class="kpi-value">{{ number_format($stats['total_entries']) }}</div></div>
</div>

{{-- Entry Form --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-plus-circle" style="margin-right:.5rem;color:#16a34a;"></i> Record Daily Pallet Count</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.warehouse-pallets.store') }}" id="palletForm">
            @csrf
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Warehouse *</label>
                    <select name="warehouse_id" required id="palletWarehouse" style="padding:.4rem .6rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;min-width:180px;">
                        <option value="">Select Warehouse...</option>
                        @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ old('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Date *</label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', date('Y-m-d')) }}" required style="padding:.4rem .6rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">No. of Pallets *</label>
                    <input type="number" name="no_of_pallets" min="0" max="99999" required placeholder="0" id="palletCount" value="{{ old('no_of_pallets') }}" style="width:100px;padding:.4rem .6rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;text-align:center;">
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Remarks</label>
                    <input type="text" name="remarks" placeholder="Optional notes..." value="{{ old('remarks') }}" style="width:100%;padding:.4rem .6rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right:.3rem;"></i> Save</button>
                <span id="palletSaveStatus" style="font-size:.75rem;"></span>
            </div>
        </form>

        {{-- Today's Quick View --}}
        @if($todayEntries->isNotEmpty())
        <div style="margin-top:.75rem;padding:.5rem .8rem;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0;">
            <div style="font-size:.65rem;font-weight:700;color:#16a34a;margin-bottom:.3rem;">TODAY'S ENTRIES</div>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                @foreach($todayEntries as $te)
                <div style="font-size:.78rem;color:#334155;">
                    <span style="font-weight:600;">{{ $te->warehouse->name ?? '—' }}:</span>
                    <span style="font-family:monospace;font-weight:700;color:#16a34a;">{{ $te->no_of_pallets }}</span>
                    @if($te->remarks)<span style="font-size:.65rem;color:#94a3b8;">— {{ $te->remarks }}</span>@endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

{{-- History / Filter --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history" style="margin-right:.5rem;color:#1e3a5f;"></i> Pallet History</h3>
        <form method="GET" action="{{ route('logistics.warehouse-pallets') }}" style="display:flex;gap:.5rem;align-items:flex-end;">
            <select name="warehouse_id" style="padding:.3rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $wh)
                <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ request('date_from') }}" placeholder="From" style="padding:.3rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
            <input type="date" name="date_to" value="{{ request('date_to') }}" placeholder="To" style="padding:.3rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('logistics.warehouse-pallets') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th>Date</th>
                    <th>Warehouse</th>
                    <th style="text-align:center;">No. of Pallets</th>
                    <th>Remarks</th>
                    <th>Recorded By</th>
                    <th>Recorded At</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td style="font-weight:600;">{{ $log->entry_date->format('d M Y') }}</td>
                    <td>{{ $log->warehouse->name ?? '—' }}</td>
                    <td style="text-align:center;font-family:monospace;font-weight:700;font-size:.9rem;color:#1e40af;">{{ number_format($log->no_of_pallets) }}</td>
                    <td style="font-size:.72rem;color:#64748b;">{{ $log->remarks ?? '—' }}</td>
                    <td style="font-size:.72rem;">{{ $log->creator->name ?? '—' }}</td>
                    <td style="font-size:.72rem;color:#94a3b8;">{{ $log->created_at->format('d M Y H:i') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;padding:2rem;color:#94a3b8;">No pallet entries yet. Use the form above to record daily counts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $logs->links() }}</div>
    @endif
</div>

<script>
// Auto-fill existing pallet count when warehouse + date selected
document.getElementById('palletWarehouse').addEventListener('change', loadExisting);
document.querySelector('[name="entry_date"]').addEventListener('change', loadExisting);

function loadExisting() {
    var whId = document.getElementById('palletWarehouse').value;
    var date = document.querySelector('[name="entry_date"]').value;
    if (!whId || !date) return;

    fetch("{{ route('logistics.warehouse-pallets') }}?lookup=1&warehouse_id=" + whId + "&entry_date=" + date, {
        headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.found) {
            document.getElementById('palletCount').value = data.no_of_pallets;
            document.getElementById('palletSaveStatus').innerHTML =
                '<span style="color:#e8a838;"><i class="fas fa-info-circle"></i> Entry exists — saving will update it.</span>';
        } else {
            document.getElementById('palletCount').value = '';
            document.getElementById('palletSaveStatus').innerHTML = '';
        }
    })
    .catch(() => {});
}
</script>
@endsection
