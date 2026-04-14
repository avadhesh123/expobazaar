@extends('layouts.app')
@section('title', 'Inspection Reports')
@section('page-title', 'Quality Inspection Reports')

@section('content')
{{-- KPI Cards --}}
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card"><div class="kpi-label">Total Reports</div><div class="kpi-value">{{ $stats['total'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;"><div class="kpi-label">Passed</div><div class="kpi-value" style="color:#16a34a;">{{ $stats['passed'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Failed</div><div class="kpi-value" style="color:#dc2626;">{{ $stats['failed'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Conditional</div><div class="kpi-value" style="color:#e8a838;">{{ $stats['conditional'] }}</div></div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('vendor.inspections.index') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="min-width:140px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Consignment</label>
                <select name="consignment_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All Consignments</option>
                    @foreach($consignments as $c)
                        <option value="{{ $c->id }}" {{ request('consignment_id')==(string)$c->id?'selected':'' }}>{{ $c->consignment_number }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:130px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Type</label>
                <select name="type" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    <option value="">All Types</option>
                    <option value="inline" {{ request('type')==='inline'?'selected':'' }}>Inline</option>
                    <option value="midline" {{ request('type')==='midline'?'selected':'' }}>Midline</option>
                    <option value="final" {{ request('type')==='final'?'selected':'' }}>Final</option>
                </select>
            </div>
            <div style="min-width:120px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Result</label>
                <select name="result" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;">
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

{{-- Reports Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-check" style="margin-right:.5rem;color:#1e3a5f;"></i> Inspection Reports</h3>
        <span style="font-size:.78rem;color:#64748b;">{{ $reports->total() }} report(s)</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Consignment</th>
                    <th>Type</th>
                    <th>Report</th>
                    <th>Result</th>
                    <th>Remarks</th>
                    <th>Uploaded By</th>
                    <th>Date</th>
                    <th>Download</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                <tr>
                    <td>
                        <div style="font-weight:600;font-family:monospace;font-size:.82rem;">
                            {{ $report->consignment->consignment_number ?? '—' }}
                        </div>
                    </td>
                    <td>
                        @php $typeColors = ['inline'=>'badge-info','midline'=>'badge-warning','final'=>'badge-success']; @endphp
                        <span class="badge {{ $typeColors[$report->inspection_type] ?? 'badge-gray' }}">
                            {{ ucfirst($report->inspection_type) }}
                        </span>
                    </td>
                    <td style="font-size:.82rem;">{{ $report->report_name }}</td>
                    <td>
                        @php $resultColors = ['passed'=>'badge-success','failed'=>'badge-danger','conditional'=>'badge-warning']; @endphp
                        <span class="badge {{ $resultColors[$report->result] ?? 'badge-gray' }}">
                            {{ ucfirst($report->result) }}
                        </span>
                    </td>
                    <td style="font-size:.82rem;max-width:200px;">
                        {{ $report->remarks ? Str::limit($report->remarks, 80) : '—' }}
                    </td>
                    <td style="font-size:.82rem;">{{ $report->uploader->name ?? 'Sourcing Team' }}</td>
                    <td style="font-size:.82rem;">{{ $report->created_at->format('d M Y') }}</td>
                    <td>
                        @if($report->report_file)
                            <a href="{{ asset('storage/' . $report->report_file) }}" target="_blank" class="btn btn-outline btn-sm">
                                <i class="fas fa-download"></i> View
                            </a>
                        @else
                            <span style="color:#94a3b8;font-size:.78rem;">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;">
                        <i class="fas fa-clipboard-check" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                        No inspection reports found for your consignments yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($reports->hasPages())
        <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $reports->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection