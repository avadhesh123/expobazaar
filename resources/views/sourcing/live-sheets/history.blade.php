@extends('layouts.app')
@section('title', 'Change History — ' . $liveSheet->live_sheet_number)
@section('page-title', 'Change History — ' . $liveSheet->live_sheet_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('sourcing.live-sheets.show', $liveSheet) }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back to Live Sheet</a>
</div>

{{-- Summary --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;"><div class="kpi-label">Total Changes</div><div class="kpi-value">{{ $changes->total() }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #3b82f6;"><div class="kpi-label">Revisions</div><div class="kpi-value" style="color:#3b82f6;">{{ $revisions->count() }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #e8a838;"><div class="kpi-label">Sourcing Updates</div><div class="kpi-value" style="color:#e8a838;">{{ $changes->getCollection()->filter(fn($c) => str_starts_with($c->changed_by_role, 'sourcing'))->count() }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #2d6a4f;"><div class="kpi-label">Vendor Updates</div><div class="kpi-value" style="color:#2d6a4f;">{{ $changes->getCollection()->filter(fn($c) => str_starts_with($c->changed_by_role, 'vendor'))->count() }}</div></div>
</div>

{{-- Revisions Timeline --}}
@foreach($revisions as $rev)
@php
    $roleBg = ['sourcing'=>'#dbeafe','vendor'=>'#dcfce7','finance'=>'#fef3c7','admin'=>'#ede9fe'];
    $roleColor = ['sourcing'=>'#1e40af','vendor'=>'#166534','finance'=>'#92400e','admin'=>'#7c3aed'];
@endphp
<div class="card" style="margin-bottom:1rem;">
    <div class="card-header" style="background:{{ $roleBg[$rev['role']] ?? '#f8fafc' }};">
        <h3 style="color:{{ $roleColor[$rev['role']] ?? '#475569' }};">
            <i class="fas {{ $rev['role']==='vendor' ? 'fa-store' : ($rev['role']==='sourcing' ? 'fa-user-tie' : 'fa-user') }}" style="margin-right:.5rem;"></i>
            Revision #{{ $rev['revision'] }} — {{ ucfirst($rev['role']) }}
        </h3>
        <div style="text-align:right;">
            <div style="font-size:.82rem;font-weight:600;color:{{ $roleColor[$rev['role']] ?? '#475569' }};">{{ $rev['user']->name ?? '—' }}</div>
            @if(!empty($rev['email']))<div style="font-size:.68rem;color:#64748b;font-family:monospace;">{{ $rev['email'] }}</div>@endif
            <div style="font-size:.68rem;color:#64748b;">{{ $rev['date']->format('d M Y H:i') }} · {{ $rev['date']->diffForHumans() }}</div>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        @if($rev['reason'])
        <div style="padding:.5rem 1.4rem;background:#fefce8;font-size:.78rem;color:#854d0e;border-bottom:1px solid #fde68a;"><i class="fas fa-comment" style="margin-right:.3rem;"></i> {{ $rev['reason'] }}</div>
        @endif
        <table class="data-table" style="margin:0;">
            <thead><tr><th>SKU</th><th>Product</th><th>Field</th><th>Old Value</th><th style="color:#16a34a;">New Value</th></tr></thead>
            <tbody>
                @foreach($rev['changes'] as $c)
                <tr>
                    <td style="font-family:monospace;font-size:.8rem;font-weight:600;">{{ $c->product->sku ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $c->product->name ?? '—' }}</td>
                    <td>
                        @php
                            $fieldLabels = [
                                'target_fob'=>'Target FOB','final_qty'=>'Final Qty','final_fob'=>'Final FOB',
                                'freight_factor'=>'Freight Factor','wsp_factor'=>'WSP Factor','comments'=>'Comments',
                                'vendor_fob'=>'Vendor FOB','qty_offered'=>'Qty Offered','barcode'=>'Barcode','sap_code'=>'SAP Code',
                            ];
                            $fieldColor = [
                                'target_fob'=>'#1e40af','final_qty'=>'#7c3aed','final_fob'=>'#dc2626',
                                'freight_factor'=>'#e8a838','wsp_factor'=>'#2d6a4f',
                            ];
                        @endphp
                        <span style="padding:.15rem .4rem;background:#f1f5f9;border-radius:4px;font-size:.75rem;font-weight:700;color:{{ $fieldColor[$c->field_name] ?? '#475569' }};">{{ $fieldLabels[$c->field_name] ?? ucfirst(str_replace('_',' ',$c->field_name)) }}</span>
                    </td>
                    <td style="font-family:monospace;font-size:.82rem;color:#dc2626;text-decoration:line-through;">{{ $c->old_value ?? '(empty)' }}</td>
                    <td style="font-family:monospace;font-size:.82rem;font-weight:700;color:#16a34a;">{{ $c->new_value ?? '(empty)' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach

@if($revisions->isEmpty())
<div class="card"><div class="card-body" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-history" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No changes recorded yet.</div></div>
@endif

@if($changes->hasPages())<div style="margin-top:1rem;">{{ $changes->links('pagination::tailwind') }}</div>@endif
@endsection