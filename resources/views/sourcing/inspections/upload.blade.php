@extends('layouts.app')
@section('title', 'Upload Inspection — ' . $consignment->consignment_number)
@section('page-title', 'Quality Inspection — ' . $consignment->consignment_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('sourcing.inspections') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Inspections</a>
    <a href="{{ route('sourcing.consignments') }}" class="btn btn-outline btn-sm"><i class="fas fa-box"></i> Consignments</a>
</div>

{{-- Consignment Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:1.1rem;font-weight:800;font-family:monospace;color:#0d1b2a;">{{ $consignment->consignment_number }}</div>
                <div style="font-size:.82rem;color:#64748b;">Vendor: <strong>{{ $consignment->vendor->company_name ?? '—' }}</strong> · Company: {{ $consignment->company_code }} · {{ $consignment->destination_country }}</div>
            </div>
            <span class="badge {{ ['created'=>'badge-gray','in_shipment'=>'badge-info','shipped'=>'badge-success'][$consignment->status] ?? 'badge-gray' }}" style="font-size:.85rem;padding:.3rem .8rem;">{{ ucfirst(str_replace('_',' ',$consignment->status)) }}</span>
        </div>
    </div>
</div>

{{-- Inspection Status Cards --}}
@php
    $reports = $consignment->inspectionReports->groupBy('inspection_type');
    $types = [
        'inline'  => ['Inline Inspection', '#3b82f6', '#dbeafe', 'fa-search', 'During production — checks materials, processes, initial quality.'],
        'midline' => ['Midline Inspection', '#e8a838', '#fef3c7', 'fa-tasks', 'Mid-production — verifies progress, dimensions, workmanship.'],
        'final'   => ['Final Inspection', '#7c3aed', '#ede9fe', 'fa-check-double', 'Post-production — full quality check before shipment.'],
    ];
@endphp

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.25rem;">
    @foreach($types as $type => [$label, $color, $bg, $icon, $desc])
    @php $typeReports = $reports->get($type, collect()); @endphp
    <div class="card" style="border-top:3px solid {{ $color }};">
        <div class="card-body" style="padding:1rem;">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
                <div style="width:36px;height:36px;border-radius:8px;background:{{ $bg }};display:flex;align-items:center;justify-content:center;"><i class="fas {{ $icon }}" style="color:{{ $color }};"></i></div>
                <div>
                    <div style="font-weight:700;font-size:.88rem;color:#0d1b2a;">{{ $label }}</div>
                    <div style="font-size:.65rem;color:#94a3b8;">{{ $typeReports->count() }} report(s)</div>
                </div>
            </div>
            <div style="font-size:.72rem;color:#64748b;margin-bottom:.75rem;">{{ $desc }}</div>
            @if($typeReports->count() > 0)
                @foreach($typeReports as $r)
                <div style="display:flex;align-items:center;gap:.5rem;padding:.4rem .5rem;background:#f8fafc;border-radius:6px;margin-bottom:.3rem;font-size:.78rem;">
                    <i class="fas fa-file-pdf" style="color:{{ $color }};"></i>
                    <a href="{{ asset('storage/app/public/' . $r->report_file) }}" target="_blank" style="flex:1;color:#0d1b2a;font-weight:500;">{{ Str::limit($r->report_name, 30) }}</a>
                    <span class="badge {{ ['passed'=>'badge-success','failed'=>'badge-danger','conditional'=>'badge-warning'][$r->result] ?? 'badge-gray' }}" style="font-size:.6rem;">{{ ucfirst($r->result) }}</span>
                    <form method="POST" action="{{ route('sourcing.inspections.delete', $r) }}" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button type="submit" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:.7rem;"><i class="fas fa-times"></i></button></form>
                </div>
                @endforeach
            @else
                <div style="text-align:center;padding:.5rem;color:#d1d5db;font-size:.75rem;"><i class="fas fa-cloud-upload-alt" style="display:block;font-size:1.2rem;margin-bottom:.2rem;"></i>No report uploaded</div>
            @endif
        </div>
    </div>
    @endforeach
</div>

{{-- Upload Form --}}
<div class="card" style="border-color:#e8a838;">
    <div class="card-header" style="background:#fffbeb;"><h3 style="color:#92400e;"><i class="fas fa-upload" style="margin-right:.5rem;"></i> Upload New Inspection Report</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('sourcing.inspections.store', $consignment) }}" enctype="multipart/form-data">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label>Inspection Type <span style="color:#dc2626;">*</span></label>
                    <select name="inspection_type" required style="font-family:inherit;">
                        <option value="">Select type...</option>
                        <option value="inline">Inline Inspection (During Production)</option>
                        <option value="midline">Midline Inspection (Mid-Production)</option>
                        <option value="final">Final Inspection (Pre-Shipment)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Result <span style="color:#dc2626;">*</span></label>
                    <select name="result" required style="font-family:inherit;">
                        <option value="">Select result...</option>
                        <option value="passed">Passed</option>
                        <option value="conditional">Conditional Pass</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Report File <span style="color:#dc2626;">*</span></label>
                    <input type="file" name="report_file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx" style="font-size:.82rem;">
                    <div style="font-size:.65rem;color:#94a3b8;margin-top:.2rem;">PDF, JPG, PNG, DOC, XLSX — Max 20MB</div>
                </div>
            </div>
            <div class="form-group" style="margin-top:.5rem;">
                <label>Product (Optional — for product-specific inspection)</label>
                <select name="product_id" style="font-family:inherit;">
                    <option value="">All products in consignment</option>
                    @if($consignment->liveSheet)
                        @foreach($consignment->liveSheet->items as $item)
                            <option value="{{ $item->product_id }}">{{ $item->product->sku ?? '' }} — {{ $item->product->name ?? '' }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="form-group" style="margin-top:.5rem;">
                <label>Remarks / Findings</label>
                <textarea name="remarks" rows="3" placeholder="Describe inspection findings, defects found, corrective actions needed..." style="font-family:inherit;font-size:.82rem;width:100%;padding:.5rem .65rem;border:1px solid #d1d5db;border-radius:8px;resize:vertical;"></textarea>
            </div>
            <div style="display:flex;gap:.5rem;margin-top:.75rem;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload" style="margin-right:.3rem;"></i> Upload Report</button>
                <a href="{{ route('sourcing.inspections') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

{{-- Timeline --}}
@if($consignment->inspectionReports->count() > 0)
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-history" style="margin-right:.5rem;color:#64748b;"></i> Inspection Timeline</h3></div>
    <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:.75rem;">
            @foreach($consignment->inspectionReports->sortByDesc('created_at') as $r)
            @php $tc = ['inline'=>'#3b82f6','midline'=>'#e8a838','final'=>'#7c3aed']; @endphp
            <div style="display:flex;align-items:start;gap:.75rem;">
                <div style="width:12px;height:12px;border-radius:50%;background:{{ $tc[$r->inspection_type] ?? '#94a3b8' }};flex-shrink:0;margin-top:.25rem;"></div>
                <div>
                    <div style="font-size:.85rem;font-weight:600;">{{ ucfirst($r->inspection_type) }} Inspection — <span class="badge {{ ['passed'=>'badge-success','failed'=>'badge-danger','conditional'=>'badge-warning'][$r->result] ?? 'badge-gray' }}" style="font-size:.65rem;">{{ ucfirst($r->result) }}</span></div>
                    <div style="font-size:.72rem;color:#64748b;">{{ $r->created_at->format('d M Y H:i') }} · By {{ $r->uploader->name ?? '—' }}</div>
                    @if($r->remarks)<div style="font-size:.78rem;color:#475569;margin-top:.2rem;">{{ $r->remarks }}</div>@endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif
@endsection
