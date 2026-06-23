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
            <div style="min-width:140px;">
                <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Consignment</label>
                <select name="consignment_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All Consignments</option>
                    @foreach($consignments as $c)
                    <option value="{{ $c->id }}" {{ request('consignment_id')==(string)$c->id?'selected':'' }}>{{ $c->consignment_number }}</option>
                    @endforeach
                </select>
            </div><div style="min-width:130px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Type</label><select name="type" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
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

{{-- Replace the All Inspection Reports card section --}}

{{-- Replace the All Inspection Reports card section --}}

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-check" style="margin-right:.5rem;color:#1e3a5f;"></i> All Inspection Reports</h3>
        <span style="font-size:.78rem;color:#64748b;">{{ $inspections->total() }} report(s)</span>
    </div>
    <div class="card-body" style="padding:0;">

        @php $grouped = $inspections->getCollection()->groupBy(fn($r) => $r->consignment_id); @endphp

        @forelse($grouped as $conId => $conReports)
        @php
            $con = $conReports->first()->consignment;
            $vendorName = $con->vendor->company_name ?? '—';
            $passCount = $conReports->where('result','passed')->count();
            $failCount = $conReports->where('result','failed')->count();
            $condCount = $conReports->where('result','conditional')->count();
            $latestResult = $conReports->sortByDesc('created_at')->first()->result ?? '—';
            $borderColors = ['passed'=>'#16a34a','failed'=>'#dc2626','conditional'=>'#e8a838'];

            // Collect all document types from all reports in this consignment
            $docs = [];
            $docTypes = [
                ['field'=>'commercial_invoice','label'=>'Commercial Invoice','icon'=>'fa-file-invoice','color'=>'#166534','bg'=>'#f0fdf4','border'=>'#bbf7d0'],
                ['field'=>'packing_list','label'=>'Packing List','icon'=>'fa-list-ol','color'=>'#854d0e','bg'=>'#fefce8','border'=>'#fde68a'],
                ['field'=>'shipping_bill','label'=>'Shipping Bill','icon'=>'fa-ship','color'=>'#1e40af','bg'=>'#eff6ff','border'=>'#bfdbfe'],
                ['field'=>'measurement','label'=>'Measurement','icon'=>'fa-ruler-combined','color'=>'#7c3aed','bg'=>'#f5f3ff','border'=>'#ddd6fe'],
                ['field'=>'hbl','label'=>'HBL','icon'=>'fa-file-contract','color'=>'#0d9488','bg'=>'#f0fdfa','border'=>'#99f6e4'],
                ['field'=>'other_doc','label'=>'Other Document','icon'=>'fa-paperclip','color'=>'#64748b','bg'=>'#f8fafc','border'=>'#e2e8f0'],
            ];
            foreach ($conReports as $r) {
                 foreach ($docTypes as $dt) {
                    $f = $dt['field'];
                    if (!empty($con->{$f.'_file'})) {
                        $docs[$f] = [
                            'file'   => $con->{$f.'_file'},
                            'name'   => $con->{$f.'_name'} ?? $con->{$f.'_number'} ?? null,
                            'number' => $con->{$f.'_number'} ?? null,
                            'date'   => $con->{$f.'_upload_date'} ?? null,
                            'by'     => $con->{$f.'_upload_by'} ?? null,
                            'meta'   => $dt,
                        ];
                    }
                }
            }
        @endphp

        <div style="border-bottom:2px solid #e8ecf1;">
            {{-- Consignment Header --}}
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 1rem;background:#f8fafc;border-left:4px solid {{ $borderColors[$latestResult] ?? '#d1d5db' }};">
                <div style="display:flex;align-items:center;gap:.6rem;">
                    <div>
                        <span style="font-weight:700;font-family:monospace;font-size:.82rem;">{{ $con->consignment_number ?? '—' }}</span>
                        <span style="font-size:.7rem;color:#64748b;margin-left:.4rem;">{{ $vendorName }}</span>
                    </div>
                </div>
                <div style="display:flex;gap:.25rem;align-items:center;">
                    @if($passCount)<span class="badge badge-success" style="font-size:.55rem;">{{ $passCount }}P</span>@endif
                    @if($failCount)<span class="badge badge-danger" style="font-size:.55rem;">{{ $failCount }}F</span>@endif
                    @if($condCount)<span class="badge badge-warning" style="font-size:.55rem;">{{ $condCount }}C</span>@endif
                </div>
            </div>

            <div style="padding:.5rem 1rem .5rem 1.5rem;">
                {{-- Inspection Reports (compact rows) --}}
                @foreach($conReports->sortBy('inspection_type') as $ins)
                @php
                    $tColors = ['inline'=>'#1e40af','midline'=>'#92400e','final'=>'#166534'];
                    $tBg = ['inline'=>'#dbeafe','midline'=>'#fef3c7','final'=>'#f0fdf4'];
                    $rColors = ['passed'=>'#16a34a','failed'=>'#dc2626','conditional'=>'#e8a838'];
                @endphp
                <div style="display:flex;align-items:center;gap:.4rem;padding:.3rem 0;{{ !$loop->last ? 'border-bottom:1px solid #f1f5f9;' : '' }}{{ $ins->result==='failed'?'background:#fef2f2;margin:0 -.5rem;padding:.3rem .5rem;border-radius:4px;':'' }}">
                    <span style="padding:.1rem .4rem;background:{{ $tBg[$ins->inspection_type]??'#f1f5f9' }};color:{{ $tColors[$ins->inspection_type]??'#475569' }};border-radius:4px;font-size:.62rem;font-weight:700;text-transform:uppercase;min-width:45px;text-align:center;">{{ substr($ins->inspection_type,0,3) }}</span>
                    <span class="badge" style="font-size:.55rem;background:{{ $rColors[$ins->result]??'#64748b' }};color:#fff;padding:.1rem .3rem;">{{ ucfirst(substr($ins->result,0,4)) }}</span>
                    <span style="font-size:.75rem;font-weight:600;flex:1;">{{ Str::limit($ins->report_name, 30) }}</span>
                    @if($ins->remarks)<span style="font-size:.65rem;color:#64748b;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $ins->remarks }}">💬 {{ Str::limit($ins->remarks, 40) }}</span>@endif
                    <span style="font-size:.62rem;color:#94a3b8;">{{ $ins->created_at->format('d M') }} · {{ $ins->uploader->name ?? '—' }}</span>
                    @if($ins->report_file)<a href="{{ asset('storage/app/public/' . $ins->report_file) }}" target="_blank" style="color:#1e40af;font-size:.72rem;" title="Download"><i class="fas fa-download"></i></a>@endif
                    <form method="POST" action="{{ route('sourcing.inspections.delete', $ins) }}" onsubmit="return confirm('Delete?')" style="display:inline;"><@csrf @method('DELETE')<button type="submit" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:.72rem;padding:0;" title="Delete"><i class="fas fa-trash"></i></button></form>
                </div>
                @endforeach

                {{-- Vendor Documents Section --}}
                @if(!empty($docs))
                <div style="margin-top:.4rem;padding:.4rem .5rem;background:#f8fafc;border-radius:6px;">
                    <div style="font-size:.58rem;font-weight:700;color:#94a3b8;text-transform:uppercase;margin-bottom:.3rem;">Vendor Documents</div>
                    <!-- <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
                        @foreach($docs as $key => $doc)
                        @php $m = $doc['meta']; @endphp
                        <a href="{{ Storage::url($doc['file']) }}" target="_blank" style="display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .5rem;background:{{ $m['bg'] }};border:1px solid {{ $m['border'] }};border-radius:5px;color:{{ $m['color'] }};text-decoration:none;font-size:.68rem;white-space:nowrap;">
                            <i class="fas {{ $m['icon'] }}"></i>
                            <span style="font-weight:600;">{{ $m['label'] }}</span>
                            @if($doc['number'])<span style="font-family:monospace;font-size:.62rem;">#{{ $doc['number'] }}</span>@endif
                        </a>
                        @endforeach
                    </div> -->

                    {{-- Document Details (compact grid) --}}
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:.3rem;margin-top:.3rem;">
                        @foreach($docs as $key => $doc)
                        @php $m = $doc['meta']; $uploaderName = null; if ($doc['by']) { $uploaderName = \App\Models\User::find($doc['by'])?->name; } @endphp
                        <div style="display:flex;align-items:center;gap:.3rem;font-size:.62rem;color:#64748b;">
                            <!-- <span style="font-weight:600;color:{{ $m['color'] }};">{{ $m['label'] }}:</span> -->
                            <!-- @if($doc['number'])<span style="font-family:monospace;">#{{ $doc['number'] }}</span>@endif -->
                            <a href="{{ Storage::url($doc['file']) }}" target="_blank" style="display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .5rem;background:{{ $m['bg'] }};border:1px solid {{ $m['border'] }};border-radius:5px;color:{{ $m['color'] }};text-decoration:none;font-size:.68rem;white-space:nowrap;">
                            <i class="fas fa-download"></i>
                            <span style="font-weight:600;">{{ $m['label'] }}</span>
                            @if($doc['number'])<span style="font-family:monospace;font-size:.62rem;">#{{ $doc['number'] }}</span>@endif
                         </a>
                            @if($doc['date'])<span>{{ \Carbon\Carbon::parse($doc['date'])->format('d M Y') }}</span>@endif
                            @if($uploaderName)<span>· {{ $uploaderName }}</span>@endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:3rem;color:#94a3b8;">
            <i class="fas fa-clipboard-check" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
            No inspection reports yet.
        </div>
        @endforelse
    </div>
    @if($inspections->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $inspections->links('pagination::tailwind') }}</div>
    @endif
</div>
@endsection