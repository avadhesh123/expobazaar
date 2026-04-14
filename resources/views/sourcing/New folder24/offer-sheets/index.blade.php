@extends('layouts.app')
@section('title', 'Offer Sheets')
@section('page-title', 'Offer Sheet Review')

@section('content')
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('sourcing.offer-sheets') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:150px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All Statuses</option>
                    @foreach(['draft','submitted','under_review','selection_done','converted'] as $s)
                        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('sourcing.offer-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-alt" style="margin-right:.5rem;color:#e8a838;"></i> Offer Sheets</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Offer Sheet #</th><th>Vendor</th><th>Company</th><th>Total Products</th><th>Selected</th><th>Status</th><th>Date</th><th style="width:180px;">Actions</th></tr></thead>
            <tbody>
                @forelse($sheets as $sheet)
                <tr>
                    <td style="font-weight:600;">{{ $sheet->offer_sheet_number }}</td>
                    <td>
                        <div style="font-size:.82rem;">{{ $sheet->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.68rem;color:#94a3b8;">{{ $sheet->vendor->vendor_code ?? '' }}</div>
                    </td>
                    <td>
                        @php $cc = ['2000'=>'🇮🇳','2100'=>'🇺🇸','2200'=>'🇳🇱']; @endphp
                        <span style="font-size:.82rem;">{{ $cc[$sheet->company_code]??'' }} {{ $sheet->company_code }}</span>
                    </td>
                    <td style="font-weight:600;text-align:center;">{{ $sheet->total_products }}</td>
                    <td style="font-weight:600;text-align:center;color:#166534;">{{ $sheet->selected_products }}</td>
                    <td>
                        @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-warning','under_review'=>'badge-info','selection_done'=>'badge-success','converted'=>'badge-success']; @endphp
                        <span class="badge {{ $sc[$sheet->status]??'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$sheet->status)) }}</span>
                    </td>
                    <td style="font-size:.82rem;color:#64748b;">{{ $sheet->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                            @if(in_array($sheet->status, ['submitted','under_review']))
                                <a href="{{ route('sourcing.offer-sheets.review', $sheet) }}" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Review & Select</a>
                            @endif
                            @if($sheet->status === 'selection_done')
                                <form method="POST" action="{{ route('sourcing.offer-sheets.convert', $sheet) }}" style="display:inline;" onsubmit="return confirm('Convert this offer sheet into a consignment?')">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-box"></i> Create Consignment</button>
                                </form>
                            @endif
                            @if($sheet->status === 'converted')
                                <span style="font-size:.75rem;color:#166534;display:flex;align-items:center;gap:.2rem;"><i class="fas fa-check-circle"></i> Converted</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-file-alt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No offer sheets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($sheets->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $sheets->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection
