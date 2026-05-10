@extends('layouts.app')
@section('title', 'Vendor Rate Cards')
@section('page-title', 'Vendor Rate Cards')

@section('content')
<!-- <div style="padding:.6rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
    Below are the <strong>approved warehouse rate cards</strong>. Edit any rate, select a vendor from the dropdown, and click <strong>Assign to Vendor</strong> to create a vendor-specific rate card. Assigned rates appear in the section below.
</div> -->

{{-- Warehouse Rate Cards as Editable Grid --}}
<!-- <div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><h3><i class="fas fa-warehouse" style="margin-right:.5rem;color:#1e3a5f;"></i> Warehouse Rate Cards — Assign to Vendor</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th>Warehouse</th>
                    <th style="text-align:right;">Inward / Carton</th>
                    <th style="text-align:right;">Storage / CFT</th>
                    <th style="text-align:right;">Fulfill ≤ Threshold</th>
                    <th style="text-align:right;">Fulfill > Threshold</th>
                    <th style="text-align:center;">Threshold</th>
                    <th style="text-align:right;">Pick & Pack / Unit</th>
                    <th style="min-width:160px;">Assign to Vendor</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($warehouseRateCards as $wrc)
                @php $s = $wrc->getCurrencySymbol(); @endphp
                <form method="POST" action="{{ route('logistics.vendor-rate-cards.store') }}">
                    @csrf
                    <input type="hidden" name="warehouse_id" value="{{ $wrc->warehouse_id }}">
                    <tr>
                        <td style="font-weight:600;">
                            {{ $wrc->warehouse->name ?? '—' }}
                            <div style="font-size:.6rem;color:#94a3b8;">v{{ $wrc->version }} · {{ $wrc->currency }}</div>
                        </td>
                        <td><input type="number" step="0.01" name="inward_rate_per_carton" value="{{ $wrc->wh_inward_rate_per_carton }}" style="width:80px;padding:.25rem .35rem;border:1px solid #d1d5db;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;"></td>
                        <td><input type="number" step="0.0001" name="storage_rate_per_cft" value="{{ $wrc->wh_storage_rate_per_cft }}" style="width:80px;padding:.25rem .35rem;border:1px solid #d1d5db;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;"></td>
                        <td><input type="number" step="0.01" name="fulfillment_rate_small" value="{{ $wrc->wh_fulfillment_rate_small }}" style="width:80px;padding:.25rem .35rem;border:1px solid #d1d5db;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;"></td>
                        <td><input type="number" step="0.01" name="fulfillment_rate_large" value="{{ $wrc->wh_fulfillment_rate_large }}" style="width:80px;padding:.25rem .35rem;border:1px solid #d1d5db;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;"></td>
                        <td><input type="number" name="fulfillment_qty_threshold" value="{{ $wrc->wh_fulfillment_qty_threshold }}" min="1" style="width:50px;padding:.25rem .35rem;border:1px solid #d1d5db;border-radius:4px;font-size:.78rem;text-align:center;"></td>
                        <td><input type="number" step="0.01" name="pick_pack_rate_per_unit" value="{{ $wrc->wh_pick_pack_rate_per_unit }}" style="width:80px;padding:.25rem .35rem;border:1px solid #d1d5db;border-radius:4px;font-size:.78rem;font-family:monospace;text-align:right;"></td>
                        <td>
                            <select name="vendor_id" required style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                                <option value="">Select vendor...</option>
                                @foreach($vendors as $v)
                                <option value="{{ $v->id }}">{{ $v->company_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Assign these rates to the selected vendor?')"><i class="fas fa-user-plus"></i> Assign</button>
                        </td>
                    </tr>
                </form>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8;">No approved warehouse rate cards found. Create one under <strong>WH Rate Card</strong> first.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div> -->

{{-- Existing Vendor Rate Cards --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-users-cog" style="margin-right:.5rem;color:#e8a838;"></i>Vendor Rate Cards</h3>
        <div style="display:flex;gap:.4rem;">
            <form method="GET" action="{{ route('logistics.vendor-rate-cards') }}" style="display:flex;gap:.4rem;">
                <select name="vendor_id" style="padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;" onchange="this.form.submit()">
                    <option value="">All Vendors</option>
                    @foreach($vendors as $v)<option value="{{ $v->id }}" {{ request('vendor_id')==(string)$v->id?'selected':'' }}>{{ $v->company_name }}</option>@endforeach
                </select>
            </form>
        </div>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th>Vendor</th>
                    <th>Warehouse</th>
                    <th style="text-align:right;">Inward / Carton</th>
                    <th style="text-align:right;">Storage / CFT</th>
                    <th style="text-align:right;">Fulfill ≤</th>
                    <th style="text-align:right;">Fulfill ></th>
                    <th style="text-align:center;">Threshold</th>
                    <th style="text-align:right;">P&P / Unit</th>
                    <th>Effective</th>
                    <th>Version</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendorRateCards as $rc)
                @php $s = $rc->getCurrencySymbol(); @endphp
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ $rc->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.6rem;color:#94a3b8;">{{ $rc->vendor->vendor_code ?? '' }} · {{ $rc->currency }}</div>
                    </td>
                    <td style="font-size:.78rem;">
                        @php $wh = $warehouses->firstWhere('company_code', $rc->company_code); @endphp
                        {{ $wh->name ?? $rc->company_code }}
                    </td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($rc->inward_rate_per_carton), 2) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($rc->storage_rate_per_cft), 4) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($rc->fulfillment_rate_small), 2) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($rc->fulfillment_rate_large), 2) }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $rc->fulfillment_qty_threshold }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $s }}{{ number_format(floatval($rc->pick_pack_rate_per_unit), 2) }}</td>
                    <td style="font-size:.72rem;">{{ $rc->effective_from?->format('d M Y') ?? '—' }}</td>
                    <td style="text-align:center;">v{{ $rc->version }}</td>
                    <td>
                        @php $sc = ['draft'=>'badge-gray','pending_approval'=>'badge-warning','approved'=>'badge-success','expired'=>'badge-gray']; @endphp
                        <span class="badge {{ $sc[$rc->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$rc->status)) }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" style="text-align:center;padding:2rem;color:#94a3b8;">No vendor rate cards assigned yet. Use the grid above to assign.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($vendorRateCards->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $vendorRateCards->links('pagination::tailwind') }}</div>@endif
</div>
@endsection