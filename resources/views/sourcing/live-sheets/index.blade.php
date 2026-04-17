@extends('layouts.app')
@section('title', 'Live Sheets')
@section('page-title', 'Live Sheet Management')

@section('content')
<div style="padding:.65rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.82rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Approving a live sheet <strong>locks</strong> it. After locking, use <strong>"Create Consignment"</strong> to move it to Logistics. Only Admin can unlock.
</div>

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('sourcing.live-sheets') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @foreach(['draft','submitted','locked','unlocked'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('sourcing.live-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
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
                    <th>CBM</th>
                    <th>Status</th>
                    <th>Consignment</th>
                    <th style="width:240px;">Next Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($liveSheets as $ls)
                <tr style="{{ $ls->is_locked ? 'background:#f0fdf4;' : '' }}">
                    <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $ls->live_sheet_number }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $ls->offerSheet->offer_sheet_number ?? '—' }}</td>
                    <td>
                        <div style="font-size:.82rem;">{{ $ls->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $ls->vendor->vendor_code ?? '' }}</div>
                    </td>
                    <td>{{ $ls->company_code }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $ls->items->count() }}</td>
                    <td><span style="font-family:monospace;font-weight:700;">{{ number_format($ls->total_cbm, 2) }}</span> <span style="font-size:.68rem;color:#94a3b8;">CBM</span></td>
                    <td>
                        @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-warning','locked'=>'badge-success','unlocked'=>'badge-warning']; @endphp
                        <span class="badge {{ $sc[$ls->status]??'badge-gray' }}">{{ ucfirst($ls->status) }}</span>
                        @if($ls->is_locked)<div style="font-size:.62rem;color:#166534;"><i class="fas fa-lock" style="font-size:.5rem;"></i> {{ $ls->locked_at?->format('d M') }}</div>@endif
                    </td>
                    <td>
                        @if($ls->consignment)
                        <span style="font-family:monospace;font-size:.78rem;font-weight:600;color:#166534;">{{ $ls->consignment->consignment_number }}</span>
                        @else
                        <span style="font-size:.72rem;color:#94a3b8;">—</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                            {{-- View Detail --}}
                            <a href="{{ route('sourcing.live-sheets.show', $ls) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i></a>

                            {{-- Waiting for vendor --}}
                            @if($ls->status === 'draft')
                            <span style="font-size:.72rem;color:#94a3b8;padding:.3rem;display:flex;align-items:center;gap:.2rem;"><i class="fas fa-clock"></i> Waiting for vendor</span>
                            @endif

                            {{-- Approve & Lock --}}
                            <!-- @if($ls->status === 'submitted')
                                <form method="POST" action="{{ route('sourcing.live-sheets.approve', $ls) }}" style="display:inline;" onsubmit="return confirm('Approve and LOCK this live sheet?\n\nCBM: {{ number_format($ls->total_cbm, 2) }}\nItems: {{ $ls->items->count() }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-lock"></i> Approve & Lock</button>
                                </form>
                            @endif -->

                            @if($ls->status === 'submitted' && !$ls->is_locked)
                            @if($ls->canBeLocked())
                            <form method="POST" action="{{ route('sourcing.live-sheets.approve', $ls) }}" style="display:inline;" onsubmit="return confirm('Approve and lock this live sheet?')">
                                @csrf<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-lock"></i> Approve & Lock</button>
                            </form>
                            @else
                            <span style="font-size:.72rem;color:#F5B027;padding:.3rem;display:flex;align-items:center;gap:.2rem;font-weight:600;"><i class="fas fa-clock"></i> Waiting for SAP Codes</span>
                            @endif
                            @endif

                            {{-- Already has consignment --}}
                            @if($ls->is_locked)
                            <span style="display:flex;align-items:center;gap:.2rem;font-size:.72rem;color:#166534;font-weight:600;"><i class="fas fa-check-circle"></i> Sent to Vendor</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-clipboard-list" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No live sheets.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($liveSheets->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $liveSheets->links('pagination::tailwind') }}</div>@endif
</div>
@endsection