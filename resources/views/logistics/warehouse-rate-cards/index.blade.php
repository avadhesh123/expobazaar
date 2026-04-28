@extends('layouts.app')
@section('title', 'Warehouse Rate Card')
@section('page-title', 'Warehouse Rate Card (Company Payable)')

@section('content')
<div style="padding:.6rem 1rem;background:#fefce8;border-radius:8px;border:1px solid #fde68a;margin-bottom:1.25rem;font-size:.78rem;color:#854d0e;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> These are rates the <strong>warehouse/3PL charges the company</strong>. Not vendor-specific. Create a rate card per warehouse, submit for approval, then use it for monthly charge calculations.
</div>

{{-- Create --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-plus" style="margin-right:.5rem;color:#16a34a;"></i> Create Warehouse Rate Card</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.warehouse-rate-cards.store') }}">@csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.75rem;">
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Warehouse *</label><select name="warehouse_id" required style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }} ({{ $w->company_code }})</option>@endforeach</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Inward / Carton *</label><input type="number" step="0.01" name="wh_inward_rate_per_carton" required placeholder="0.00" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Storage / CFT / Month *</label><input type="number" step="0.0001" name="wh_storage_rate_per_cft" required value="0.60" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Fulfillment ≤ threshold *</label><input type="number" step="0.01" name="wh_fulfillment_rate_small" required value="1.50" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Fulfillment > threshold *</label><input type="number" step="0.01" name="wh_fulfillment_rate_large" required value="2.50" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Threshold (units) *</label><input type="number" name="wh_fulfillment_qty_threshold" required value="3" min="1" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Pick & Pack / Unit *</label><input type="number" step="0.01" name="wh_pick_pack_rate_per_unit" required value="0.50" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Effective From *</label><input type="date" name="effective_from" required value="{{ date('Y-m-d') }}" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
            </div>
            <div style="margin-top:.75rem;"><button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Create</button></div>
        </form>
    </div>
</div>

{{-- List --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-file-contract" style="margin-right:.5rem;color:#1e3a5f;"></i> All Warehouse Rate Cards</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Warehouse</th><th>Inward</th><th>Storage</th><th>Fulfill ≤</th><th>Fulfill ></th><th>Threshold</th><th>P&P</th><th>Effective</th><th>Version</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($rateCards as $rc)
                @php $s = $rc->getCurrencySymbol(); @endphp
                <tr>
                    <td style="font-weight:600;">{{ $rc->warehouse->name ?? '—' }}</td>
                    <td style="font-family:monospace;">{{ $s }}{{ number_format(floatval($rc->wh_inward_rate_per_carton),2) }}</td>
                    <td style="font-family:monospace;">{{ $s }}{{ number_format(floatval($rc->wh_storage_rate_per_cft),4) }}</td>
                    <td style="font-family:monospace;">{{ $s }}{{ number_format(floatval($rc->wh_fulfillment_rate_small),2) }}</td>
                    <td style="font-family:monospace;">{{ $s }}{{ number_format(floatval($rc->wh_fulfillment_rate_large),2) }}</td>
                    <td style="text-align:center;">{{ $rc->wh_fulfillment_qty_threshold }}</td>
                    <td style="font-family:monospace;">{{ $s }}{{ number_format(floatval($rc->wh_pick_pack_rate_per_unit),2) }}</td>
                    <td style="font-size:.72rem;">{{ $rc->effective_from->format('d M Y') }}</td>
                    <td style="text-align:center;">v{{ $rc->version }}</td>
                    <td>@php $sc = ['draft'=>'badge-gray','pending_approval'=>'badge-warning','approved'=>'badge-success','expired'=>'badge-gray']; @endphp<span class="badge {{ $sc[$rc->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$rc->status)) }}</span></td>
                    <td>
                        @if($rc->status==='draft')<form method="POST" action="{{ route('logistics.warehouse-rate-cards.submit', $rc) }}" style="display:inline;">@csrf<button class="btn btn-outline btn-sm"><i class="fas fa-paper-plane"></i></button></form>@endif
                        @if($rc->status==='pending_approval')<form method="POST" action="{{ route('logistics.warehouse-rate-cards.approve', $rc) }}" style="display:inline;" onsubmit="return confirm('Approve?')">@csrf<button class="btn btn-success btn-sm"><i class="fas fa-check"></i></button></form>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" style="text-align:center;padding:3rem;color:#94a3b8;">No rate cards. Create one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rateCards->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $rateCards->links('pagination::tailwind') }}</div>@endif
</div>
@endsection
