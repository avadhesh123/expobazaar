@extends('layouts.app')
@section('title', 'Upload GRN')
@section('page-title', 'Upload GRN — ' . $shipment->shipment_code)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('logistics.grn') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back to GRN List</a>
</div>

{{-- Shipment Info --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;">
            <div><div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Shipment</div><div style="font-weight:700;font-family:monospace;">{{ $shipment->shipment_code }}</div></div>
            <div><div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Type</div><div><span class="badge badge-info">{{ $shipment->shipment_type }}</span></div></div>
            <div><div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Company</div><div style="font-weight:600;">{{ $shipment->company_code }}</div></div>
            <div><div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Total CBM</div><div style="font-weight:600;">{{ number_format($shipment->total_cbm, 2) }}</div></div>
            <div><div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Total Items</div><div style="font-weight:600;">{{ $shipment->total_items }}</div></div>
            <div><div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Country</div><div style="font-weight:600;">{{ $shipment->destination_country }}</div></div>
        </div>
    </div>
</div>

{{-- GRN Form --}}
<form method="POST" action="{{ route('logistics.grn.store', $shipment) }}" enctype="multipart/form-data">
    @csrf

    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header"><h3><i class="fas fa-upload" style="margin-right:.5rem;color:#e8a838;"></i> GRN Details</h3></div>
        <div class="card-body">
            <div class="grid-3">
                <div class="form-group">
                    <label>Warehouse <span style="color:#dc2626;">*</span></label>
                    <select name="warehouse_id" required>
                        <option value="">Select warehouse...</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                    @error('warehouse_id')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>Receipt Date <span style="color:#dc2626;">*</span></label>
                    <input type="date" name="receipt_date" required value="{{ date('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label>GRN Document (PDF/Excel)</label>
                    <input type="file" name="grn_file" accept=".pdf,.xlsx,.csv">
                </div>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" rows="2" placeholder="Any notes about this receipt..."></textarea>
            </div>
        </div>
    </div>

    {{-- Product Items --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-boxes" style="margin-right:.5rem;color:#1e3a5f;"></i> Product Items</h3></div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr><th>Product</th><th>SKU</th><th>Vendor</th><th>Consignment</th><th>Expected Qty <span style="color:#dc2626;">*</span></th><th>Received Qty <span style="color:#dc2626;">*</span></th><th>Damaged</th><th>Missing</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                    @php $idx = 0; @endphp
                    @foreach($shipment->consignments as $con)
                        @if($con->liveSheet)
                            @foreach($con->liveSheet->items as $item)
                            <tr>
                                <td>
                                    <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item->product_id }}">
                                    <input type="hidden" name="items[{{ $idx }}][consignment_id]" value="{{ $con->id }}">
                                    <div style="font-weight:600;font-size:.82rem;">{{ $item->product->name ?? '—' }}</div>
                                </td>
                                <td style="font-family:monospace;font-size:.8rem;">{{ $item->product->sku ?? '—' }}</td>
                                <td style="font-size:.78rem;color:#64748b;">{{ $con->vendor->company_name ?? '—' }}</td>
                                <td style="font-size:.75rem;font-family:monospace;">{{ $con->consignment_number }}</td>
                                <td><input type="number" name="items[{{ $idx }}][expected_quantity]" value="{{ $item->quantity }}" min="0" required style="width:80px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;text-align:center;font-family:monospace;"></td>
                                <td><input type="number" name="items[{{ $idx }}][received_quantity]" value="{{ $item->quantity }}" min="0" required style="width:80px;padding:.3rem .4rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.82rem;text-align:center;font-family:monospace;background:#f0fdf4;"></td>
                                <td><input type="number" name="items[{{ $idx }}][damaged_quantity]" value="0" min="0" style="width:70px;padding:.3rem .4rem;border:1px solid #fecaca;border-radius:6px;font-size:.82rem;text-align:center;font-family:monospace;"></td>
                                <td><input type="number" name="items[{{ $idx }}][missing_quantity]" value="0" min="0" style="width:70px;padding:.3rem .4rem;border:1px solid #fef3c7;border-radius:6px;font-size:.82rem;text-align:center;font-family:monospace;"></td>
                                <td><input type="text" name="items[{{ $idx }}][remarks]" placeholder="Notes..." style="width:120px;padding:.3rem .4rem;border:1px solid #e2e8f0;border-radius:6px;font-size:.78rem;"></td>
                            </tr>
                            @php $idx++; @endphp
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:1.25rem;display:flex;gap:.5rem;justify-content:flex-end;">
        <a href="{{ route('logistics.grn') }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Upload GRN? Inventory will be automatically updated.')"><i class="fas fa-upload" style="margin-right:.3rem;"></i> Upload GRN & Update Inventory</button>
    </div>
</form>
@endsection
