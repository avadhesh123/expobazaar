@extends('layouts.app')
@section('title', 'Offer Sheets')
@section('page-title', 'Offer Sheet Review')

@section('content')
{{-- Process Flow --}}
<div style="padding:.65rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
    @foreach([['1. Review & Select','fas fa-check-square','#1e40af'],['2. Create Live Sheet','fas fa-clipboard-list','#e8a838'],['3. Vendor Fills Details','fas fa-edit','#7c3aed'],['4. Approve & Lock','fas fa-lock','#2d6a4f'],['5. Create Consignment','fas fa-box','#dc2626']] as [$label,$icon,$color])
    <span style="display:flex;align-items:center;gap:.2rem;padding:.25rem .5rem;background:#fff;border-radius:6px;font-size:.7rem;font-weight:600;color:{{ $color }};border:1px solid {{ $color }}30;"><i class="{{ $icon }}" style="font-size:.6rem;"></i> {{ $label }}</span>
    @if(!$loop->last)<i class="fas fa-arrow-right" style="color:#d1d5db;font-size:.5rem;"></i>@endif
    @endforeach
</div>

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('sourcing.offer-sheets') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:160px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                <select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All</option>
                    @foreach(['submitted','under_review','selection_done','live_sheet_created','converted'] as $s)
                    <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('sourcing.offer-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-alt" style="margin-right:.5rem;color:#e8a838;"></i> Offer Sheets</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Offer Sheet #</th><th>Vendor</th><th>Company</th><th>Products</th><th>Selected</th><th>Status</th><th>Date</th><th style="width:240px;">Next Action</th></tr></thead>
            <tbody>
                @forelse($sheets as $sheet)
                @php
                    $hasLiveSheet = \App\Models\LiveSheet::where('offer_sheet_id', $sheet->id)->exists();
                @endphp
                <tr>
                    <td style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $sheet->offer_sheet_number }}</td>
                    <td><div style="font-size:.82rem;">{{ $sheet->vendor->company_name ?? '—' }}</div><div style="font-size:.68rem;color:#94a3b8;">{{ $sheet->vendor->vendor_code ?? '' }}</div></td>
                    <td>@php $ccBg=['2000'=>'#dcfce7','2100'=>'#dbeafe','2200'=>'#fef3c7']; @endphp <span style="padding:.15rem .4rem;background:{{ $ccBg[$sheet->company_code]??'#f1f5f9' }};border-radius:5px;font-size:.78rem;font-weight:600;">{{ $sheet->company_code }}</span></td>
                    <td style="text-align:center;font-weight:600;">{{ $sheet->total_products }}</td>
                    <td style="text-align:center;font-weight:700;color:#166534;">{{ $sheet->selected_products }}</td>
                    <td>
                        @php $sc = ['submitted'=>'badge-warning','under_review'=>'badge-info','selection_done'=>'badge-success','live_sheet_created'=>'badge-info','converted'=>'badge-success']; @endphp
                        <span class="badge {{ $sc[$sheet->status]??'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$sheet->status)) }}</span>
                    </td>
                    <td style="font-size:.82rem;color:#64748b;">{{ $sheet->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                            {{-- Step 1: Review & Select --}}
                            @if(in_array($sheet->status, ['submitted','under_review']))
                                <a href="{{ route('sourcing.offer-sheets.review', $sheet) }}" class="btn btn-primary btn-sm"><i class="fas fa-check-square"></i> Review & Select</a>
                            @endif

                            {{-- Step 2: Create Live Sheet (after selection done) --}}
                            @if($sheet->status === 'selection_done' && !$hasLiveSheet)
                                <form method="POST" action="{{ route('sourcing.offer-sheets.create-live-sheet', $sheet) }}" style="display:inline;" onsubmit="return confirm('Create Live Sheet with {{ $sheet->selected_products }} selected products?\n\nVendor will be notified to fill detailed product information.')">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-clipboard-list"></i> Create Live Sheet</button>
                                </form>
                            @endif

                            {{-- Live sheet created — link to it --}}
                            @if($sheet->status === 'live_sheet_created' || $hasLiveSheet)
                                @php $ls = \App\Models\LiveSheet::where('offer_sheet_id', $sheet->id)->first(); @endphp
                                @if($ls)
                                    <a href="{{ route('sourcing.live-sheets.show', $ls) }}" class="btn btn-outline btn-sm"><i class="fas fa-clipboard-list"></i> View Live Sheet</a>
                                @endif
                            @endif

                            {{-- Converted --}}
                            @if($sheet->status === 'converted')
                                <span style="display:flex;align-items:center;gap:.2rem;font-size:.72rem;color:#166534;font-weight:600;"><i class="fas fa-check-circle"></i> Consignment Created</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-file-alt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No offer sheets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($sheets->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $sheets->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
