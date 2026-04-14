@extends('layouts.app')
@section('title', 'Offer Sheets')
@section('page-title', 'My Offer Sheets')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
    <div style="display:flex;gap:.5rem;">
        <a href="{{ route('vendor.offer-sheets.template') }}" class="btn btn-primary"><i class="fas fa-file-excel"></i> Download Template</a>
    </div>
    <a href="{{ route('vendor.offer-sheets.create') }}" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Offer Sheet</a>
</div>

<div style="padding:.65rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
    <strong>Steps:</strong> 1) Download the template → 2) Fill in your product details → 3) Upload the completed file using "Upload Offer Sheet"
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-alt" style="margin-right:.5rem;color:#e8a838;"></i> Offer Sheets</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Offer Sheet #</th><th>Products</th><th>Selected</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($sheets as $s)
                <tr>
                    <td style="font-weight:700;font-family:monospace;">{{ $s->offer_sheet_number }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $s->total_products }}</td>
                    <td style="text-align:center;font-weight:600;color:#166534;">{{ $s->selected_products }}</td>
                    <td>
                        @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-warning','under_review'=>'badge-info','selection_done'=>'badge-success','live_sheet_created'=>'badge-info','converted'=>'badge-success']; @endphp
                        <span class="badge {{ $sc[$s->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$s->status)) }}</span>
                    </td>
                    <td style="font-size:.82rem;color:#64748b;">{{ $s->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:.25rem;">
                            <a href="{{ route('vendor.offer-sheets.show', $s) }}" class="btn btn-outline btn-sm" title="View"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('vendor.offer-sheets.download', $s) }}" class="btn btn-outline btn-sm" title="Download CSV"><i class="fas fa-download"></i></a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;padding:3rem;color:#94a3b8;">
                    <i class="fas fa-file-alt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                    No offer sheets yet.<br>
                    <a href="{{ route('vendor.offer-sheets.template') }}" style="color:#16a34a;font-weight:600;">Download template</a> and
                    <a href="{{ route('vendor.offer-sheets.create') }}" style="color:#1e3a5f;font-weight:600;">upload your first offer sheet →</a>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($sheets->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $sheets->links('pagination::tailwind') }}</div>@endif
</div>
@endsection