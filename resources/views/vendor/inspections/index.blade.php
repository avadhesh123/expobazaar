@extends('layouts.app')
@section('title', 'Inspection Reports')
@section('page-title', 'Quality Inspection Reports')

@section('content')
{{-- KPI Cards --}}
<div style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <div class="kpi-card" style="flex:1;border-left:3px solid #1e40af;"><div class="kpi-label">Total Reports</div><div class="kpi-value" style="color:#1e40af;">{{ $stats['total'] }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #16a34a;"><div class="kpi-label">Passed</div><div class="kpi-value" style="color:#16a34a;">{{ $stats['passed'] }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #dc2626;"><div class="kpi-label">Failed</div><div class="kpi-value" style="color:#dc2626;">{{ $stats['failed'] }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #e8a838;"><div class="kpi-label">Conditional</div><div class="kpi-value" style="color:#e8a838;">{{ $stats['conditional'] }}</div></div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('vendor.inspections.index') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="min-width:140px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Consignment</label>
                <select name="consignment_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All Consignments</option>
                    @foreach($consignments as $c)
                    <option value="{{ $c->id }}" {{ request('consignment_id')==(string)$c->id?'selected':'' }}>{{ $c->consignment_number }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:130px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Type</label>
                <select name="type" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All Types</option>
                    <option value="inline" {{ request('type')==='inline'?'selected':'' }}>Inline</option>
                    <option value="midline" {{ request('type')==='midline'?'selected':'' }}>Midline</option>
                    <option value="final" {{ request('type')==='final'?'selected':'' }}>Final</option>
                </select>
            </div>
            <div style="min-width:120px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Result</label>
                <select name="result" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All Results</option>
                    <option value="passed" {{ request('result')==='passed'?'selected':'' }}>Passed</option>
                    <option value="failed" {{ request('result')==='failed'?'selected':'' }}>Failed</option>
                    <option value="conditional" {{ request('result')==='conditional'?'selected':'' }}>Conditional</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('vendor.inspections.index') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

{{-- Grouped by Consignment --}}
@php $grouped = $reports->getCollection()->groupBy(fn($r) => $r->consignment_id); @endphp

@forelse($grouped as $conId => $conReports)
@php
    $consignment = $conReports->first()->consignment;
    $conNumber = $consignment->consignment_number ?? 'Unknown';
    $passCount = $conReports->where('result', 'passed')->count();
    $failCount = $conReports->where('result', 'failed')->count();
    $condCount = $conReports->where('result', 'conditional')->count();
    $latestResult = $conReports->sortByDesc('created_at')->first()->result ?? '—';
    $resultColors = ['passed'=>'#16a34a','failed'=>'#dc2626','conditional'=>'#e8a838'];
@endphp
<div class="card" style="margin-bottom:1.25rem;border-left:3px solid {{ $resultColors[$latestResult] ?? '#d1d5db' }};">
    {{-- Consignment Header --}}
    <div class="card-header" style="padding:.75rem 1.4rem;">
        <div>
            <h3 style="margin:0;display:flex;align-items:center;gap:.5rem;">
                <i class="fas fa-box" style="color:#1e3a5f;"></i>
                <span style="font-family:monospace;">{{ $conNumber }}</span>
            </h3>
            <div style="display:flex;gap:.75rem;margin-top:.3rem;font-size:.7rem;color:#64748b;">
                @if($consignment->liveSheet)<span><i class="fas fa-clipboard-list" style="margin-right:.2rem;"></i>{{ $consignment->liveSheet->live_sheet_number ?? '' }}</span>@endif
                <span><i class="fas fa-file-alt" style="margin-right:.2rem;"></i>{{ $conReports->count() }} report(s)</span>
            </div>
        </div>
        <div style="display:flex;gap:.3rem;">
            @if($passCount > 0)<span class="badge badge-success" style="font-size:.6rem;">{{ $passCount }} Passed</span>@endif
            @if($failCount > 0)<span class="badge badge-danger" style="font-size:.6rem;">{{ $failCount }} Failed</span>@endif
            @if($condCount > 0)<span class="badge badge-warning" style="font-size:.6rem;">{{ $condCount }} Conditional</span>@endif
        </div>
    </div>

    {{-- Reports for this consignment --}}
    <div class="card-body" style="padding:0;">
        @foreach($conReports->sortBy('inspection_type') as $report)
        @php
            $typeColors = ['inline'=>'#3b82f6','midline'=>'#e8a838','final'=>'#16a34a'];
            $typeIcons = ['inline'=>'fa-play-circle','midline'=>'fa-pause-circle','final'=>'fa-check-circle'];
            $rColor = $resultColors[$report->result] ?? '#64748b';
        @endphp
        <div style="padding:.75rem 1.4rem;border-top:1px solid #f1f5f9;{{ $report->result === 'failed' ? 'background:#fef2f2;' : '' }}">
            {{-- Report Header Row --}}
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;">
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <i class="fas {{ $typeIcons[$report->inspection_type] ?? 'fa-file' }}" style="color:{{ $typeColors[$report->inspection_type] ?? '#64748b' }};font-size:.9rem;"></i>
                    <span style="font-weight:700;font-size:.85rem;">{{ ucfirst($report->inspection_type) }} Inspection</span>
                    <span class="badge" style="font-size:.58rem;background:{{ $rColor }};color:#fff;">{{ ucfirst($report->result) }}</span>
                </div>
                <div style="font-size:.7rem;color:#94a3b8;">
                    {{ $report->created_at->format('d M Y') }} · {{ $report->uploader->name ?? 'Sourcing Team' }}
                </div>
            </div>

            {{-- Report Details Grid --}}
            <div style="display:flex;gap:1.5rem;flex-wrap:wrap;font-size:.78rem;">
                {{-- Report Name --}}
                <div style="min-width:180px;">
                    <div style="font-size:.6rem;color:#94a3b8;font-weight:600;text-transform:uppercase;margin-bottom:.1rem;">Report</div>
                    <div style="font-weight:600;">{{ $report->report_name }}</div>
                </div>

                {{-- Documents --}}
                <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                    {{-- Inspection Report File --}}
                    @if($report->report_file)
                    <div>
                        <div style="font-size:.6rem;color:#94a3b8;font-weight:600;text-transform:uppercase;margin-bottom:.1rem;">Report PDF</div>
                        <a href="{{ \App\Helpers\FileStorage::url($report->report_file) }}" target="_blank" style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .6rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;color:#1e40af;text-decoration:none;font-size:.75rem;">
                            <i class="fas fa-file-pdf"></i> Download
                        </a>
                    </div>
                    @endif

                    {{-- Commercial Invoice --}}
                    @if($report->commercial_invoice_file)
                    <div>
                        <div style="font-size:.6rem;color:#94a3b8;font-weight:600;text-transform:uppercase;margin-bottom:.1rem;">Commercial Invoice</div>
                        <a href="{{ \App\Helpers\FileStorage::url($report->commercial_invoice_file) }}" target="_blank" style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .6rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;color:#166534;text-decoration:none;font-size:.75rem;">
                            <i class="fas fa-file-invoice"></i> {{ Str::limit($report->commercial_invoice_name ?? 'View', 20) }}
                        </a>
                    </div>
                    @endif

                    {{-- Packing List --}}
                    @if($report->packing_list_file)
                    <div>
                        <div style="font-size:.6rem;color:#94a3b8;font-weight:600;text-transform:uppercase;margin-bottom:.1rem;">Packing List</div>
                        <a href="{{ \App\Helpers\FileStorage::url($report->packing_list_file) }}" target="_blank" style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .6rem;background:#fefce8;border:1px solid #fde68a;border-radius:6px;color:#854d0e;text-decoration:none;font-size:.75rem;">
                            <i class="fas fa-list-ol"></i> {{ Str::limit($report->packing_list_name ?? 'View', 20) }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Remarks --}}
            @if($report->remarks)
            <div style="margin-top:.4rem;padding:.35rem .6rem;background:#f8fafc;border-radius:6px;font-size:.75rem;color:#475569;">
                <i class="fas fa-comment" style="color:#94a3b8;margin-right:.3rem;"></i> {{ $report->remarks }}
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@empty
<div class="card">
    <div class="card-body" style="text-align:center;padding:3rem;color:#94a3b8;">
        <i class="fas fa-clipboard-check" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
        No inspection reports found for your consignments yet.
    </div>
</div>
@endforelse

@if($reports->hasPages())
<div style="padding:1rem 0;">{{ $reports->links('pagination::tailwind') }}</div>
@endif
@endsection