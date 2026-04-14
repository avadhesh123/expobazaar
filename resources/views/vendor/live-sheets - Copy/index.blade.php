@extends('layouts.app')
@section('title', 'Live Sheets')
@section('page-title', 'My Live Sheets')

@section('content')
<div style="padding:.65rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Fill detailed product info for selected items. Once submitted, Sourcing will review and lock the sheet. Locked sheets can only be unlocked by Admin.
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-clipboard-list" style="margin-right:.5rem;color:#1e3a5f;"></i> Live Sheets</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Live Sheet #</th><th>Consignment</th><th>Items</th><th>CBM</th><th>Status</th><th>Locked</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($liveSheets as $ls)
                <tr style="{{ $ls->is_locked?'background:#f0fdf4;':'' }}">
                    <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $ls->live_sheet_number }}</td>
                    <td style="font-size:.82rem;">{{ $ls->consignment->consignment_number ?? '—' }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $ls->items->count() }}</td>
                    <td style="font-family:monospace;">{{ number_format($ls->total_cbm, 2) }} CBM</td>
                    <td>@php $sc=['draft'=>'badge-gray','submitted'=>'badge-warning','approved'=>'badge-info','locked'=>'badge-success','unlocked'=>'badge-warning']; @endphp <span class="badge {{ $sc[$ls->status]??'badge-gray' }}">{{ ucfirst($ls->status) }}</span></td>
                    <td>
                        @if($ls->is_locked)<div style="color:#166534;font-size:.75rem;"><i class="fas fa-lock"></i> {{ $ls->locked_at?->format('d M Y') }}</div>
                        @else<i class="fas fa-unlock" style="color:#94a3b8;"></i>@endif
                    </td>
                    <td>
                        @if(!$ls->is_locked && in_array($ls->status, ['draft','unlocked']))
                            <a href="{{ route('vendor.live-sheets.edit', $ls) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Fill & Submit</a>
                        @elseif($ls->status === 'submitted')
                            <span style="font-size:.72rem;color:#e8a838;font-weight:600;"><i class="fas fa-clock"></i> Awaiting Approval</span>
                        @elseif($ls->is_locked)
                            <span style="font-size:.72rem;color:#166534;"><i class="fas fa-check-circle"></i> Sent to Logistics</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-clipboard-list" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No live sheets yet. They are created when consignments are finalized.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($liveSheets->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $liveSheets->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
