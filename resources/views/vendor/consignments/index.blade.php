@extends('layouts.app')
@section('title', 'Consignments')
@section('page-title', 'My Consignments')

@section('content')
<div class="card">
    <div class="card-header"><h3><i class="fas fa-box" style="margin-right:.5rem;color:#2d6a4f;"></i> Consignments</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Consignment #</th><th>Country</th><th>Items</th><th>CBM</th><th>Live Sheet</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($consignments as $c)
                <tr>
                    <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $c->consignment_number }}</td>
                    <td>@php $fl=['US'=>'🇺🇸','NL'=>'🇳🇱','IN'=>'🇮🇳']; @endphp {{ $fl[$c->destination_country]??'' }} {{ $c->destination_country }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $c->total_items }}</td>
                    <td style="font-family:monospace;">{{ number_format($c->total_cbm, 2) }}</td>
                    <td>
                        @if($c->liveSheet)
                            @if($c->liveSheet->is_locked)<span class="badge badge-success"><i class="fas fa-lock" style="margin-right:.15rem;font-size:.55rem;"></i> Locked</span>
                            @elseif($c->liveSheet->status==='submitted')<span class="badge badge-warning">Submitted</span>
                            @else<span class="badge badge-gray">{{ ucfirst($c->liveSheet->status) }}</span>@endif
                        @else<span class="badge badge-gray">Pending</span>@endif
                    </td>
                    
                    <td><span class="badge badge-info">{{ ucfirst(str_replace('_',' ',$c->status)) }}</span></td>
                    <td>
                        <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
                            @if($c->liveSheet && !$c->liveSheet->is_locked && in_array($c->liveSheet->status, ['draft','unlocked']))
                                <a href="{{ route('vendor.live-sheets.edit', $c->liveSheet) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Live Sheet</a>
                            @endif
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('insp{{ $c->id }}').style.display=document.getElementById('insp{{ $c->id }}').style.display==='none'?'table-row':'none'"><i class="fas fa-file-upload"></i> Inspection</button>
                        </div>
                    </td>
                </tr>
                {{-- Inspection Upload --}}
                <tr id="insp{{ $c->id }}" style="display:none;background:#eff6ff;">
                    <td colspan="8" style="padding:.75rem;">
                        <form method="POST" action="{{ route('vendor.inspections.upload', $c) }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">@csrf
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Type *</label><select name="inspection_type" required style="padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:inherit;"><option value="inline">Inline</option><option value="midline">Midline</option><option value="final">Final</option></select></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Report File *</label><input type="file" name="report" required style="font-size:.78rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Result</label><select name="result" style="padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;font-family:inherit;"><option value="">Select...</option><option value="passed">Pass</option><option value="fail">Fail</option><option value="conditional">Conditional</option></select></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Remarks</label><input type="text" name="remarks" placeholder="Notes..." style="width:150px;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-box" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No consignments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($consignments->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $consignments->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
