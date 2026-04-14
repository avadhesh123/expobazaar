@extends('layouts.app')
@section('title', 'Sourcing Dashboard')
@section('page-title', 'Sourcing Dashboard')

@section('content')
{{-- KPIs --}}
<div class="grid-kpi">
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Vendors This Month</div><div class="kpi-value">{{ $data['kpis']['vendors_this_month'] ?? 0 }}</div></div>
            <div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Offer Sheets Pending</div><div class="kpi-value" style="color:#e8a838;">{{ $data['kpis']['offer_sheets_pending'] ?? 0 }}</div></div>
            <div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-file-alt"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Live Sheets Pending</div><div class="kpi-value" style="color:#dc2626;">{{ $data['kpis']['live_sheets_pending'] ?? 0 }}</div></div>
            <div class="kpi-icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-clipboard-list"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="kpi-label">Products Selected</div><div class="kpi-value" style="color:#166534;">{{ $data['kpis']['products_selected'] ?? 0 }}</div></div>
            <div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-check-circle"></i></div>
        </div>
    </div>
</div>

<div class="grid-2">
    {{-- Vendor Onboarding Status --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-user-plus" style="margin-right:.5rem;color:#e8a838;"></i> Vendor Onboarding</h3><a href="{{ route('sourcing.vendors.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Vendor</a></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div style="padding:.85rem;background:#fef3c7;border-radius:10px;text-align:center;border-left:3px solid #e8a838;">
                    <div style="font-size:1.4rem;font-weight:800;color:#92400e;">{{ $data['vendor_onboarding']['pending_approval'] ?? 0 }}</div>
                    <div style="font-size:.7rem;color:#92400e;font-weight:600;">Pending Admin Approval</div>
                </div>
                <div style="padding:.85rem;background:#dbeafe;border-radius:10px;text-align:center;border-left:3px solid #1e40af;">
                    <div style="font-size:1.4rem;font-weight:800;color:#1e40af;">{{ $data['vendor_onboarding']['pending_kyc'] ?? 0 }}</div>
                    <div style="font-size:.7rem;color:#1e40af;font-weight:600;">Pending KYC Review</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Offer Sheets Pending --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-file-alt" style="margin-right:.5rem;color:#1e3a5f;"></i> Recent Offer Sheets</h3><a href="{{ route('sourcing.offer-sheets') }}" class="btn btn-outline btn-sm">View All</a></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead><tr><th>Offer Sheet</th><th>Vendor</th><th>Products</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse(($data['offer_sheets'] ?? collect())->take(5) as $sheet)
                    <tr>
                        <td style="font-weight:600;font-size:.82rem;">{{ $sheet->offer_sheet_number }}</td>
                        <td style="font-size:.82rem;">{{ $sheet->vendor->company_name ?? '—' }}</td>
                        <td style="font-size:.82rem;">{{ $sheet->total_products }}</td>
                        <td>
                            @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-warning','under_review'=>'badge-info','selection_done'=>'badge-success','converted'=>'badge-success']; @endphp
                            <span class="badge {{ $sc[$sheet->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$sheet->status)) }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem;">No offer sheets pending.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Consignment Pipeline --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-box" style="margin-right:.5rem;color:#2d6a4f;"></i> Consignment Pipeline</h3><a href="{{ route('sourcing.consignments') }}" class="btn btn-outline btn-sm">View All</a></div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Consignment #</th><th>Vendor</th><th>Country</th><th>Items</th><th>CBM</th><th>Live Sheet</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @forelse(($data['consignment_pipeline'] ?? collect())->take(10) as $con)
                <tr>
                    <td style="font-weight:600;">{{ $con->consignment_number }}</td>
                    <td>{{ $con->vendor->company_name ?? '—' }}</td>
                    <td>{{ $con->destination_country }}</td>
                    <td>{{ $con->total_items }}</td>
                    <td>{{ number_format($con->total_cbm, 2) }}</td>
                    <td>
                        @if($con->liveSheet)
                            <span class="badge {{ $con->liveSheet->is_locked ? 'badge-success' : ($con->liveSheet->status === 'submitted' ? 'badge-warning' : 'badge-gray') }}">
                                {{ $con->liveSheet->is_locked ? 'Locked' : ucfirst($con->liveSheet->status) }}
                            </span>
                        @else
                            <span class="badge badge-gray">Not Created</span>
                        @endif
                    </td>
                    <td><span class="badge badge-info">{{ ucfirst(str_replace('_',' ',$con->status)) }}</span></td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $con->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:1.5rem;">No consignments in pipeline.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Process Flow Reference --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-project-diagram" style="margin-right:.5rem;color:#7c3aed;"></i> Sourcing Process Flow</h3></div>
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
            @foreach([
                ['Vendor Request', 'fas fa-user-plus', '#dbeafe', '#1e40af'],
                ['Admin Approval', 'fas fa-check-circle', '#dcfce7', '#166534'],
                ['KYC Upload', 'fas fa-id-card', '#fef3c7', '#92400e'],
                ['Finance KYC Review', 'fas fa-file-invoice', '#ede9fe', '#6d28d9'],
                ['Contract (DocuSign)', 'fas fa-file-signature', '#fce4ec', '#c62828'],
                ['Vendor Active', 'fas fa-store', '#dcfce7', '#166534'],
                ['Offer Sheet', 'fas fa-file-alt', '#dbeafe', '#1e40af'],
                ['Product Selection', 'fas fa-check-square', '#fef3c7', '#92400e'],
                ['Consignment', 'fas fa-box', '#e0e7ff', '#3730a3'],
                ['Live Sheet', 'fas fa-clipboard-list', '#fce4ec', '#c62828'],
                ['Lock & Ship', 'fas fa-lock', '#dcfce7', '#166534'],
            ] as [$label, $icon, $bg, $color])
                <div style="display:flex;align-items:center;gap:.4rem;padding:.4rem .7rem;background:{{ $bg }};border-radius:8px;">
                    <i class="{{ $icon }}" style="color:{{ $color }};font-size:.75rem;"></i>
                    <span style="font-size:.72rem;font-weight:600;color:{{ $color }};">{{ $label }}</span>
                </div>
                @if(!$loop->last)<i class="fas fa-arrow-right" style="color:#d1d5db;font-size:.6rem;"></i>@endif
            @endforeach
        </div>
    </div>
</div>
@endsection
