@extends('layouts.app')
@section('title', 'Vendor Rate Cards')
@section('page-title', 'Vendor Recovery Rate Cards')

@section('content')
<div style="padding:.6rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Set vendor-specific rates for warehouse charge recovery. These rates override the default warehouse rate card when calculating monthly vendor charges.
</div>

{{-- Add New Rate --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-plus" style="margin-right:.5rem;color:#16a34a;"></i> Add Vendor Rate</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.vendor-rate-cards.store') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">@csrf
            <div style="min-width:160px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Vendor *</label><select name="vendor_id" required style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->company_name }}</option>@endforeach</select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Warehouse</label><select name="warehouse_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"><option value="">All Warehouses</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Charge Type *</label><select name="charge_key" required style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;" onchange="this.form.charge_label.value=this.options[this.selectedIndex].text">@foreach($chargeKeys as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <input type="hidden" name="charge_label" value="Unloading">
            <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">UOM</label><input type="text" name="uom" placeholder="Per Unit" style="width:100px;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
            <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Rate ($) *</label><input type="number" step="0.01" name="rate" required placeholder="0.00" style="width:90px;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;text-align:right;"></div>
            <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">From</label><input type="date" name="effective_from" style="padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
            <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">To</label><input type="date" name="effective_to" style="padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add</button>
        </form>
    </div>
</div>

{{-- Existing Rate Cards --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-invoice-dollar" style="margin-right:.5rem;color:#1e3a5f;"></i> Vendor Rate Cards</h3><span style="font-size:.78rem;color:#64748b;">{{ $rateCards->total() }} entries</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Vendor</th><th>Warehouse</th><th>Charge</th><th>UOM</th><th>Rate</th><th>Effective</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($rateCards as $rc)
                <tr>
                    <td style="font-weight:600;font-size:.82rem;">{{ $rc->vendor->company_name ?? '—' }}</td>
                    <td style="font-size:.78rem;">{{ $rc->warehouse->name ?? 'All' }}</td>
                    <td><span class="badge badge-info">{{ $rc->charge_label }}</span><div style="font-size:.6rem;color:#94a3b8;font-family:monospace;">{{ $rc->charge_key }}</div></td>
                    <td style="font-size:.78rem;">{{ $rc->uom ?? '—' }}</td>
                    <td style="font-family:monospace;font-weight:700;">${{ number_format(floatval($rc->rate), 4) }}</td>
                    <td style="font-size:.72rem;">{{ $rc->effective_from?->format('d M Y') ?? '—' }} → {{ $rc->effective_to?->format('d M Y') ?? 'ongoing' }}</td>
                    <td><span class="badge {{ $rc->is_active?'badge-success':'badge-gray' }}">{{ $rc->is_active?'Active':'Inactive' }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('logistics.vendor-rate-cards.update', $rc) }}" style="display:flex;gap:.25rem;">@csrf @method('PUT')
                            <input type="number" step="0.01" name="rate" value="{{ $rc->rate }}" style="width:70px;padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:5px;font-size:.78rem;font-family:monospace;text-align:right;">
                            <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-save"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:3rem;color:#94a3b8;">No vendor rate cards yet. Add one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rateCards->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $rateCards->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
