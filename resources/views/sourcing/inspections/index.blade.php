@extends('layouts.app')
@section('title', 'Quality Inspections')
@section('page-title', 'Quality Inspection Reports')

@section('content')
{{-- Stats --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;">
        <div class="kpi-label">Total Reports</div>
        <div class="kpi-value">{{ $stats['total'] }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #3b82f6;">
        <div class="kpi-label">Inline</div>
        <div class="kpi-value" style="color:#3b82f6;">{{ $stats['inline'] }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #e8a838;">
        <div class="kpi-label">Midline</div>
        <div class="kpi-value" style="color:#e8a838;">{{ $stats['midline'] }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #7c3aed;">
        <div class="kpi-label">Final</div>
        <div class="kpi-value" style="color:#7c3aed;">{{ $stats['final'] }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #16a34a;">
        <div class="kpi-label">Passed</div>
        <div class="kpi-value" style="color:#16a34a;">{{ $stats['passed'] }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #dc2626;">
        <div class="kpi-label">Failed</div>
        <div class="kpi-value" style="color:#dc2626;">{{ $stats['failed'] }}</div>
    </div>
</div>

{{-- Quick Upload --}}
<div class="card" style="margin-bottom:1.25rem;border-color:#e8a838;">
    <div class="card-header" style="background:#fffbeb;">
        <h3 style="color:#92400e;"><i class="fas fa-upload" style="margin-right:.5rem;"></i> Upload Inspection Report</h3>
    </div>
    <div class="card-body">
        <div style="font-size:.78rem;color:#64748b;margin-bottom:.75rem;">Select a consignment to upload Inline, Midline, or Final inspection reports.</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:.75rem;">
            @forelse($consignments as $con)
            <a href="{{ route('sourcing.inspections.upload', $con) }}" style="display:flex;align-items:center;gap:.65rem;padding:.75rem;background:#f8fafc;border-radius:10px;border:1px solid #e8ecf1;text-decoration:none;transition:all .2s;" onmouseover="this.style.borderColor='#e8a838'" onmouseout="this.style.borderColor='#e8ecf1'">
                <div style="width:40px;height:40px;border-radius:8px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-box" style="color:#e8a838;"></i></div>
                <div>
                    <div style="font-weight:700;font-family:monospace;font-size:.82rem;color:#0d1b2a;">{{ $con->consignment_number }}</div>
                    <div style="font-size:.7rem;color:#64748b;">{{ $con->vendor->company_name ?? '—' }} · {{ $con->company_code }}</div>
                </div>
                @php $ic = $con->inspectionReports->count() ?? 0; @endphp
                @if($ic > 0)<span class="badge badge-info" style="margin-left:auto;">{{ $ic }} report(s)</span>@endif
            </a>
            @empty
            <div style="color:#94a3b8;font-size:.82rem;">No active consignments available.</div>
            @endforelse
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('sourcing.inspections') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:130px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Type</label><select name="type" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All</option>
                    <option value="inline" {{ request('type')==='inline'?'selected':'' }}>Inline</option>
                    <option value="midline" {{ request('type')==='midline'?'selected':'' }}>Midline</option>
                    <option value="final" {{ request('type')==='final'?'selected':'' }}>Final</option>
                </select></div>
            <div style="min-width:130px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Result</label><select name="result" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All</option>
                    <option value="passed" {{ request('result')==='passed'?'selected':'' }}>Passed</option>
                    <option value="failed" {{ request('result')==='failed'?'selected':'' }}>Failed</option>
                    <option value="conditional" {{ request('result')==='conditional'?'selected':'' }}>Conditional</option>
                </select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            <a href="{{ route('sourcing.inspections') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>
</div>

{{-- All Reports --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-check" style="margin-right:.5rem;color:#1e3a5f;"></i> All Inspection Reports</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Consignment</th>
                    <th>Vendor</th>
                    <th>Type</th>
                    <th>Report</th>
                    <th>Result</th>
                    <th>Remarks</th>
                    <th>Commercial Invoice</th>
                    <th>Packing List</th>
                    <th>Uploaded By</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inspections as $ins)
                @php
                $typeBg = ['inline'=>'#dbeafe','midline'=>'#fef3c7','final'=>'#ede9fe'];
                $typeColor = ['inline'=>'#1e40af','midline'=>'#92400e','final'=>'#7c3aed'];
                $resBg = ['passed'=>'badge-success','failed'=>'badge-danger','conditional'=>'badge-warning'];
                @endphp
                <tr>
                    <td style="font-family:monospace;font-weight:600;font-size:.82rem;">{{ $ins->consignment->consignment_number ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $ins->consignment->vendor->company_name ?? '—' }}</td>
                    <td><span style="padding:.2rem .5rem;background:{{ $typeBg[$ins->inspection_type] ?? '#f1f5f9' }};color:{{ $typeColor[$ins->inspection_type] ?? '#475569' }};border-radius:6px;font-size:.75rem;font-weight:700;text-transform:uppercase;">{{ $ins->inspection_type }}</span></td>
                    <td><a href="{{ asset('storage/app/public/' . $ins->report_file) }}" target="_blank" style="font-size:.82rem;color:#1e40af;"><i class="fas fa-file-pdf" style="margin-right:.2rem;"></i>{{ Str::limit($ins->report_name, 25) }}</a></td>
                    <td><span class="badge {{ $resBg[$ins->result] ?? 'badge-gray' }}">{{ ucfirst($ins->result) }}</span></td>
                    <td style="font-size:.78rem;color:#64748b;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $ins->remarks }}">{{ $ins->remarks ?? '—' }}</td>

                    <td>
                        @if($ins->commercial_invoice_file)
                        <a href="{{ Storage::url($ins->commercial_invoice_file) }}" target="_blank" style="font-size:.82rem;color:#1e40af;">
                            <i class="fas fa-file-pdf" style="margin-right:.2rem;"></i>
                            {{ Str::limit($ins->commercial_invoice_name, 25) }}
                        </a>
                        @else
                        —
                        @endif
                    </td>
                    <td>
                        @if($ins->packing_list_file)
                        <a href="{{ Storage::url($ins->packing_list_file) }}" target="_blank" style="font-size:.82rem;color:#1e40af;">
                            <i class="fas fa-file-pdf" style="margin-right:.2rem;"></i>
                            {{ Str::limit($ins->packing_list_name, 25) }}
                        </a>
                        @else
                        —
                        @endif
                    </td>
                    <td style="font-size:.78rem;">{{ $ins->uploader->name ?? '—' }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $ins->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:.25rem;">
                            <a href="{{ asset('storage/app/public/' . $ins->report_file) }}" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-download"></i></a>
                            <form method="POST" action="{{ route('sourcing.inspections.delete', $ins) }}" onsubmit="return confirm('Delete this report?')">@csrf @method('DELETE')<button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626;"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-clipboard-check" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No inspection reports yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($inspections->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $inspections->links('pagination::tailwind') }}</div>@endif
</div>
@endsection