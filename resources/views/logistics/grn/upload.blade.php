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
            <div>
                <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Shipment</div>
                <div style="font-weight:700;font-family:monospace;">{{ $shipment->shipment_code }}</div>
            </div>
            <div>
                <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Type</div>
                <div><span class="badge badge-info">{{ $shipment->shipment_type }}</span></div>
            </div>
            <div>
                <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Company</div>
                <div style="font-weight:600;">{{ $shipment->company_code }}</div>
            </div>
            <div>
                <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Total CBM</div>
                <div style="font-weight:600;">{{ number_format($shipment->total_cbm, 2) }}</div>
            </div>
            <div>
                <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Total Items</div>
                <div style="font-weight:600;">{{ $shipment->total_items }}</div>
            </div>
            <div>
                <div style="font-size:.68rem;color:#64748b;font-weight:600;text-transform:uppercase;">Country</div>
                <div style="font-weight:600;">{{ $shipment->destination_country }}</div>
            </div>
        </div>
    </div>
</div>

{{-- GRN Form --}}
<form method="POST" action="{{ route('logistics.grn.store', $shipment) }}" enctype="multipart/form-data" id="grnForm">
    @csrf

    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header">
            <h3><i class="fas fa-upload" style="margin-right:.5rem;color:#e8a838;"></i> GRN Details</h3>
        </div>
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
        <div class="card-header">
            <h3><i class="fas fa-boxes" style="margin-right:.5rem;color:#1e3a5f;"></i> Product Items</h3>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table" id="grnTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Vendor</th>
                        <th>Consignment</th>
                        <th>Expected Qty</th>
                        <th>Received Qty <span style="color:#dc2626;">*</span></th>
                        <th>Damaged Qty</th>
                        <th>Missing Qty</th>
                        <th>Excess Qty</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @php $idx = 0; @endphp
                    @foreach($shipment->consignments as $con)
                    @if($con->liveSheet)
                    @foreach($con->liveSheet->items as $item)
                    <tr class="item-row" data-expected="{{ $item->quantity }}">
                        <td>
                            <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $item->product_id }}">
                            <input type="hidden" name="items[{{ $idx }}][consignment_id]" value="{{ $con->id }}">
                            <div style="font-weight:600;font-size:.82rem;">{{ $item->product->name ?? '—' }}</div>
                        </td>
                        <td style="font-family:monospace;font-size:.8rem;">{{ $item->product->sku ?? '—' }}</td>
                        <td style="font-size:.78rem;color:#64748b;">{{ $con->vendor->company_name ?? '—' }}</td>
                        <td style="font-size:.75rem;font-family:monospace;">{{ $con->consignment_number }}</td>
                        <td class="expected-qty" style="font-weight:600;font-family:monospace;text-align:center;">

                            <input type="number"
                                readonly
                                name="items[{{ $idx }}][expected_quantity]"
                                value="{{ $item->quantity }}"
                                min="0"
                                class="expected-qty"
                                style="width:70px;padding:.3rem .4rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.82rem;text-align:center;font-family:monospace;background:#f0fdf4;">

                        </td>
                        <td>
                            <input type="number"
                                name="items[{{ $idx }}][received_quantity]"
                                value="{{ $item->quantity }}"
                                min="0"
                                class="received-qty"
                                style="width:70px;padding:.3rem .4rem;border:1px solid #bbf7d0;border-radius:6px;font-size:.82rem;text-align:center;font-family:monospace;background:#f0fdf4;">
                        </td>
                        <td>
                            <input type="number"
                                name="items[{{ $idx }}][damaged_quantity]"
                                value="0"
                                min="0"
                                class="damaged-qty"
                                style="width:70px;padding:.3rem .4rem;border:1px solid #fecaca;border-radius:6px;font-size:.82rem;text-align:center;font-family:monospace;">
                        </td>
                        <td>
                            <input type="number"
                                name="items[{{ $idx }}][missing_quantity]"
                                value="0"
                                min="0"
                                class="missing-qty"
                                style="width:70px;padding:.3rem .4rem;border:1px solid #fef3c7;border-radius:6px;font-size:.82rem;text-align:center;font-family:monospace;">
                        </td>
                        <td>
                            <input type="number"
                                name="items[{{ $idx }}][excess_quantity]"
                                value="0"
                                min="0"
                                class="excess-qty"
                                style="width:70px;padding:.3rem .4rem;border:1px solid #fef3c7;border-radius:6px;font-size:.82rem;text-align:center;font-family:monospace;">
                        </td>
                        <td>
                            <input type="text"
                                name="items[{{ $idx }}][remarks]"
                                placeholder="Notes..."
                                style="width:120px;padding:.3rem .4rem;border:1px solid #e2e8f0;border-radius:6px;font-size:.78rem;">
                        </td>
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
        <button type="submit" class="btn btn-primary" id="submitBtn">
            <i class="fas fa-upload" style="margin-right:.3rem;"></i> Upload GRN & Update Inventory
        </button>
    </div>
</form>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = document.getElementById('grnTable');
        const form = document.getElementById('grnForm');
        const submitBtn = document.getElementById('submitBtn');

        // Real-time validation on input change
        table.addEventListener('input', function(e) {
            if (e.target.matches('input[type="number"]')) {
                validateRow(e.target.closest('tr'));
            }
        });

        function validateRow(row) {
            const expectedQty = parseFloat(row.dataset.expected) || 0;

            const received = parseFloat(row.querySelector('.received-qty').value) || 0;
            const damaged = parseFloat(row.querySelector('.damaged-qty').value) || 0;
            const missing = parseFloat(row.querySelector('.missing-qty').value) || 0;
            const excess = parseFloat(row.querySelector('.excess-qty').value) || 0;

            const calculated = (received + damaged + missing) - excess;

            // Visual feedback
            const expectedCell = row.querySelector('.expected-qty');

            if (calculated !== expectedQty) {
                expectedCell.style.color = '#dc2626';
                expectedCell.style.fontWeight = '700';
                expectedCell.title = `Mismatch! Calculated: ${calculated}, Expected: ${expectedQty}`;
            } else {
                expectedCell.style.color = '';
                expectedCell.style.fontWeight = '600';
                expectedCell.title = '';
            }
        }

        // Form validation before submit
        form.addEventListener('submit', function(e) {
            let isValid = true;
            let errorMessages = [];

            const rows = table.querySelectorAll('.item-row');

            rows.forEach((row, index) => {
                const expectedQty = parseFloat(row.dataset.expected) || 0;
                const received = parseFloat(row.querySelector('.received-qty').value) || 0;
                const damaged = parseFloat(row.querySelector('.damaged-qty').value) || 0;
                const missing = parseFloat(row.querySelector('.missing-qty').value) || 0;
                const excess = parseFloat(row.querySelector('.excess-qty').value) || 0;

                const calculated = (received + damaged + missing) - excess;

                if (calculated !== expectedQty) {
                    isValid = false;
                    errorMessages.push(`Row ${index + 1}: Expected Qty (${expectedQty}) does not match calculated value (${calculated})`);
                }

                // Additional basic validation
                if (received < 0) {
                    isValid = false;
                    errorMessages.push(`Row ${index + 1}: Received quantity cannot be negative`);
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('GRN Validation Failed:\n\n' + errorMessages.join('\n'));
                return false;
            }

            // Final confirmation
            return confirm('Upload GRN? Inventory will be automatically updated based on received quantities.');
        });

        // Initialize validation on load
        document.querySelectorAll('.item-row').forEach(row => {
            validateRow(row);
        });
    });
</script>
@endpush