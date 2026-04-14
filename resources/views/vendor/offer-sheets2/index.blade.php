@extends('layouts.app')
@section('title', 'Offer Sheets')
@section('page-title', 'My Offer Sheets')

@section('content')
<div style="display:flex;justify-content:flex-end;margin-bottom:1.25rem;">
    <a href="{{ route('vendor.offer-sheets.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> New Offer Sheet</a>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-alt" style="margin-right:.5rem;color:#e8a838;"></i> Offer Sheets</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Offer Sheet #</th><th>Products</th><th>Selected</th><th>Status</th><th>Submitted</th></tr></thead>
            <tbody>
                @forelse($sheets as $s)
                <tr>
                    <td style="font-weight:700;font-family:monospace;">{{ $s->offer_sheet_number }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $s->total_products }}</td>
                    <td style="text-align:center;font-weight:600;color:#166534;">{{ $s->selected_products }}</td>
                    <td>
                        @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-warning','under_review'=>'badge-info','selection_done'=>'badge-success','converted'=>'badge-success']; @endphp
                        <span class="badge {{ $sc[$s->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$s->status)) }}</span>
                    </td>
                    <td style="font-size:.82rem;color:#64748b;">{{ $s->created_at->format('d M Y') }}</td>
                </tr>
                @if($s->status === 'selection_done' && $s->selectedItems->count() > 0)
                <tr style="background:#f0fdf4;"><td colspan="5" style="padding:.5rem 1rem;">
                    <div style="font-size:.72rem;font-weight:600;color:#166534;margin-bottom:.3rem;">Selected Products:</div>
                    <div style="display:flex;flex-wrap:wrap;gap:.3rem;">
                        @foreach($s->selectedItems->take(8) as $item)<span style="padding:.15rem .4rem;background:#dcfce7;border-radius:4px;font-size:.7rem;color:#166534;">{{ $item->product_name }}</span>@endforeach
                        @if($s->selectedItems->count() > 8)<span style="font-size:.7rem;color:#94a3b8;">+{{ $s->selectedItems->count()-8 }} more</span>@endif
                    </div>
                </td></tr>
                @endif
                @empty
                <tr><td colspan="5" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-file-alt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No offer sheets yet.<br><a href="{{ route('vendor.offer-sheets.create') }}" style="color:#1e3a5f;">Submit your first offer sheet →</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($sheets->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $sheets->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
