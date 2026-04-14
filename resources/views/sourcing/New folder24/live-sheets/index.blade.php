@extends('layouts.app')
@section('title', 'Live Sheets')
@section('page-title', 'Live Sheet Approval')

@section('content')
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('sourcing.live-sheets') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:140px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @foreach(['draft','submitted','approved','locked','unlocked'] as $s)
                        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('sourcing.live-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

{{-- Info Banner --}}
<div style="padding:.75rem 1.2rem;background:#eff6ff;border-radius:10px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.82rem;color:#1e40af;display:flex;align-items:center;gap:.5rem;">
    <i class="fas fa-info-circle"></i>
    <span>Approving a live sheet will <strong>lock</strong> it and move it to the Logistics team for container planning. Only Admin can unlock.</span>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-clipboard-list" style="margin-right:.5rem;color:#1e3a5f;"></i> Live Sheets</h3><span style="font-size:.78rem;color:#64748b;">{{ $liveSheets->total() }} total</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Live Sheet #</th><th>Consignment</th><th>Vendor</th><th>Total CBM</th><th>Items</th><th>Status</th><th>Locked</th><th style="width:180px;">Actions</th></tr></thead>
            <tbody>
                @forelse($liveSheets as $ls)
                <tr style="{{ $ls->is_locked ? 'background:#f0fdf4;' : '' }}">
                    <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $ls->live_sheet_number }}</td>
                    <td>
                        <div style="font-size:.82rem;font-weight:600;">{{ $ls->consignment->consignment_number ?? '—' }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $ls->consignment->destination_country ?? '' }} · {{ $ls->consignment->company_code ?? '' }}</div>
                    </td>
                    <td>
                        <div style="font-size:.82rem;">{{ $ls->consignment->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $ls->consignment->vendor->vendor_code ?? '' }}</div>
                    </td>
                    <td>
                        <span style="font-family:monospace;font-weight:700;font-size:.9rem;">{{ number_format($ls->total_cbm, 2) }}</span>
                        <span style="font-size:.68rem;color:#94a3b8;">CBM</span>
                    </td>
                    <td style="text-align:center;font-weight:600;">{{ $ls->items()->count() }}</td>
                    <td>
                        @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-warning','approved'=>'badge-info','locked'=>'badge-success','unlocked'=>'badge-warning']; @endphp
                        <span class="badge {{ $sc[$ls->status]??'badge-gray' }}">{{ ucfirst($ls->status) }}</span>
                    </td>
                    <td>
                        @if($ls->is_locked)
                            <div style="display:flex;align-items:center;gap:.3rem;">
                                <i class="fas fa-lock" style="color:#16a34a;font-size:.75rem;"></i>
                                <div>
                                    <div style="font-size:.72rem;color:#166534;font-weight:600;">Locked</div>
                                    <div style="font-size:.62rem;color:#94a3b8;">{{ $ls->locked_at?->format('d M Y H:i') }}</div>
                                    <div style="font-size:.62rem;color:#94a3b8;">by {{ $ls->lockedByUser->name ?? '—' }}</div>
                                </div>
                            </div>
                        @else
                            <i class="fas fa-unlock" style="color:#94a3b8;"></i>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                            @if($ls->status === 'submitted')
                                <form method="POST" action="{{ route('sourcing.live-sheets.approve', $ls) }}" style="display:inline;"
                                    onsubmit="return confirm('Approve and LOCK this live sheet? It will be sent to Logistics for container planning.\n\nTotal CBM: {{ number_format($ls->total_cbm, 2) }}\nItems: {{ $ls->items()->count() }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-lock"></i> Approve & Lock</button>
                                </form>
                            @endif

                            @if($ls->is_locked)
                                <span style="display:flex;align-items:center;gap:.2rem;padding:.3rem .6rem;background:#dcfce7;border-radius:6px;font-size:.72rem;color:#166534;font-weight:600;">
                                    <i class="fas fa-truck"></i> Sent to Logistics
                                </span>
                            @endif

                            @if($ls->status === 'draft')
                                <span style="font-size:.72rem;color:#94a3b8;padding:.3rem;">Waiting for vendor submission</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-clipboard-list" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No live sheets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($liveSheets->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $liveSheets->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection
