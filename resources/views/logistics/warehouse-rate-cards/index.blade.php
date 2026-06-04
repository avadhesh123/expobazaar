@extends('layouts.app')
@section('title', 'Warehouse Rate Card')
@section('page-title', 'Warehouse Rate Card (Company Payable)')

@section('content')
<div style="padding:.6rem 1rem;background:#fefce8;border-radius:8px;border:1px solid #fde68a;margin-bottom:1.25rem;font-size:.78rem;color:#854d0e;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> These are rates the <strong>warehouse/3PL charges the company</strong>. Not vendor-specific. Create a rate card per warehouse, submit for approval, then use it for monthly charge calculations.
</div>

{{-- Create --}}
<div class="card" style="margin-bottom:1.25rem;">

    <div class="card-header">
        <h3>
            <i class="fas fa-plus" style="margin-right:.5rem;color:#16a34a;"></i>
            Create EU Warehouse Rate Card
        </h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.warehouse-rate-cards.store') }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.75rem;">

                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Warehouse *</label>
                    <select name="warehouse_id" required>
                        @foreach($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }} ({{ $w->company_code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Unloading(FCL)</label>
                    <input type="number" step="0.01" name="unloading_fcl" placeholder="0.00">
                </div>

                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Unloading(LCL Palletize)</label>
                    <input type="number" step="0.01" name="unloading_lcl_palletize" placeholder="0.00">
                </div>

                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Unloading(Carton)</label>
                    <input type="number" step="0.01" name="unloading_carton" placeholder="0.00">
                </div>

                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">PutAway(Per carton)</label>
                    <input type="number" step="0.01" name="put_away_per_carton" placeholder="0.00">
                </div>

                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">CheckIn(Per QTY)</label>
                    <input type="number" step="0.01" name="checkin_per_qty" placeholder="0.00">
                </div>

                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">CheckIn(Labor Per Hours) </label>
                    <input type="number" step="0.01" name="checkin_per_hours" placeholder="0.00">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Storage(Per Pallet) </label>
                    <input type="number" step="0.01" name="storage_per_pallet" placeholder="0.00">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Storage(Per CFT)</label>
                    <input type="number" step="0.01" name="storage_per_cft" placeholder="0.00">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Order Processing(Palletize)</label>
                    <input type="number" step="0.01" name="order_processing_palletize" placeholder="0.00">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Order Processing(Non-Palletize)</label>
                    <input type="number" step="0.01" name="order_processing_non_palletize" placeholder="0.00">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Pick-Pack</label>
                    <input type="number" step="0.01" name="pick_pack" placeholder="0.00">
                </div>

                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Fulfillment ≤ threshold</label>
                    <input type="number" step="0.01" name="fulfillment_rate_small" placeholder="0.00">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Fulfillment > threshold</label>
                    <input type="number" step="0.01" name="fulfillment_rate_large" placeholder="0.00">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Threshold (units)</label>
                    <input type="number" step="0.01" name="fulfillment_qty_threshold" placeholder="0.00">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Manpower Cost(Per hours)</label>
                    <input type="number" step="0.01" name="manpower_cost" placeholder="0.00">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Return Inward(Per qty)</label>
                    <input type="number" step="0.01" name="return_inward_per_qty" placeholder="0.00">
                </div>
                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Return Inward(Per carton)</label>
                    <input type="number" step="0.01" name="return_inward_per_carton" placeholder="0.00">
                </div>

                <div>
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;">Effective From *</label>
                    <input type="date" name="effective_from" required value="{{ date('Y-m-d') }}">
                </div>

            </div>

            <div style="margin-top:.75rem;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Create
                </button>
            </div>
        </form>
    </div>
</div>
{{-- List --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-contract" style="margin-right:.5rem;color:#1e3a5f;"></i> All Warehouse Rate Cards</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Warehouse</th>
                    <th>Unloading (FCL)</th>
                    <th>Unloading (LCL Palletize)</th>
                    <th>Unloading (Carton)</th>
                    <th>PutAway (per carton)</th>
                    <th>CheckIn (Per QTY)</th>
                    <th>CheckIn (Labor Per Hours)</th>
                    <th>Storage (Per pallet)</th>
                    <th>Storage (Per CFT)</th>
                    <th>Order Processing (Palletize)</th>
                    <th>Order Processing (Non Palletize)</th>
                    <th>Pick-Pack</th>
                    <th>Fulfillment ≤ threshold</th>
                    <th>Fulfillment > threshold</th>
                    <th>Threshold (units)</th>
                    <th>Manpower Cost(Per hours)</th>
                    <!-- <th>Return Inward (Per carton)</th> -->
                    <th>Return Inward (Per qty)</th>
                    <th>Effective</th>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rateCards as $rc)
                <tr>
                    <td style="font-weight:600;">{{ $rc->warehouse->name ?? '—' }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->unloading_fcl),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->unloading_lcl_palletize),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->unloading_carton),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->put_away_per_carton),2) }}</td>

                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->checkin_per_qty),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->checkin_per_hours),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->storage_per_pallet),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->storage_per_cft),2) }}</td>


                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->order_processing_palletize),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->order_processing_non_palletize),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->pick_pack),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->fulfillment_rate_small),2) }}</td> 

                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->fulfillment_rate_large),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->fulfillment_qty_threshold),2) }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->manpower_cost),2) }}</td>
                    <!-- <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($rc->return_inward_per_carton),2) }}</td> -->
                    <td style="text-align:center;font-family:monospace;">{{ $activeCurrencySymbol }}{{ $rc->return_inward_per_qty }}</td>

                    <td style="font-size:.72rem;">{{ $rc->effective_from->format('d M Y') }}</td>
                    <td style="text-align:center;">v{{ $rc->version }}</td>
                    <td>@php $sc = ['draft'=>'badge-gray','pending_approval'=>'badge-warning','approved'=>'badge-success','expired'=>'badge-gray']; @endphp<span class="badge {{ $sc[$rc->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$rc->status)) }}</span></td>
                    <td>
                        @if($rc->status==='draft')<form method="POST" action="{{ route('logistics.warehouse-rate-cards.submit', $rc) }}" style="display:inline;">@csrf<button class="btn btn-outline btn-sm"><i class="fas fa-paper-plane"></i></button></form>@endif
                        @if($rc->status==='pending_approval')<form method="POST" action="{{ route('logistics.warehouse-rate-cards.approve', $rc) }}" style="display:inline;" onsubmit="return confirm('Approve?')">@csrf<button class="btn btn-success btn-sm"><i class="fas fa-check"></i></button></form>@endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" style="text-align:center;padding:3rem;color:#94a3b8;">No rate cards. Create one above.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rateCards->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $rateCards->links('pagination::tailwind') }}</div>@endif
</div>
<style>
    input[type="number"],
    input[type="date"],
    select {
        width: 100%;
        padding: .4rem .5rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: .82rem;
        font-family: monospace;

    }
</style>
@endsection