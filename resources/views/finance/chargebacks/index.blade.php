@extends('layouts.app')
@section('title', 'Chargebacks')
@section('page-title', 'Chargeback Management')

@section('content')


<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card"><div class="kpi-label">Total</div><div class="kpi-value">{{ $stats['total'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;"><div class="kpi-label">Pending Sourcing Confirmation</div><div class="kpi-value" style="color:#e8a838;">{{ $stats['pending'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Confirmed</div><div class="kpi-value" style="color:#dc2626;">{{ $stats['confirmed'] }}</div></div>
    <div class="kpi-card" style="border-left:3px solid #dc2626;"><div class="kpi-label">Confirmed Amount</div><div class="kpi-value" style="font-size:1.3rem;color:#dc2626;">{{$activeCurrencySymbol}}{{ number_format($stats['total_amount'], 0) }}</div></div>
</div> 
{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('finance.chargebacks') }}" style="display:flex;gap:.75rem;align-items:flex-end;">
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label><select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach(['pending_confirmation','confirmed','rejected','deducted'] as $s)<option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach</select></div>
            <!-- <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option><option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option><option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option><option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option></select></div> -->
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Vendor</label><select name="vendor_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:inherit;"><option value="">All</option>@foreach($vendors as $v)<option value="{{ $v->id }}" {{ request('vendor_id')==(string)$v->id?'selected':'' }}>{{ $v->company_name }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('finance.chargebacks') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <button type="button" class="btn btn-danger btn-sm" style="margin-left:auto;" onclick="document.getElementById('raisePanel').style.display=document.getElementById('raisePanel').style.display==='none'?'block':'none'"><i class="fas fa-plus"></i> Raise Chargeback</button>
        </form>
    </div>
</div>

{{-- Raise Chargeback Panel --}}
<div id="raisePanel" style="display:none;margin-bottom:1.25rem;">
   {{-- Raise Chargeback Form --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#dc2626;"></i> Raise Chargeback</h3></div>
    <div class="card-body">

        {{-- Step 1: Order Lookup --}}
        <div style="display:flex;gap:.75rem;align-items:flex-end;margin-bottom:1rem;">
            <div class="form-group" style="margin-bottom:0;">
                <label>Order Number *</label>
                <input type="text" id="cbOrderSearch" placeholder="Enter Order # or PO #" style="width:200px;">
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="lookupOrder()"><i class="fas fa-search" style="margin-right:.3rem;"></i> Lookup</button>
            <div id="cbOrderStatus" style="font-size:.78rem;"></div>
        </div>

        {{-- Step 2: Order Details (hidden until lookup) --}}
        <div id="cbOrderDetails" style="display:none;">
            {{-- Order Info --}}
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem;margin-bottom:1rem;padding:.75rem;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
                <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Order #</div><div id="cbInfoOrder" style="font-weight:700;font-family:monospace;"></div></div>
                <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Date</div><div id="cbInfoDate"></div></div>
                <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Channel</div><div id="cbInfoChannel"></div></div>
                <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Customer</div><div id="cbInfoCustomer"></div></div>
                <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Total</div><div id="cbInfoTotal" style="font-weight:700;font-family:monospace;"></div></div>
            </div>

            {{-- Warning for existing chargeback --}}
            <div id="cbExistingWarning" style="display:none;padding:.5rem 1rem;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;margin-bottom:1rem;font-size:.78rem;color:#dc2626;">
                <i class="fas fa-exclamation-circle"></i> <span id="cbExistingMsg"></span>
            </div>

            {{-- Chargeback Form with Items --}}
            <form method="POST" id="cbRaiseForm" enctype="multipart/form-data">
                @csrf

                {{-- Items Table with per-item evidence upload --}}
                <div style="margin-bottom:1rem;">
                    <div style="font-size:.72rem;font-weight:600;color:#64748b;margin-bottom:.3rem;">SELECT ITEMS & UPLOAD EVIDENCE</div>
                    <table class="data-table" style="font-size:.78rem;">
                        <thead>
                            <tr style="background:#f0f4f8;">
                                <th style="width:30px;"><input type="checkbox" id="cbSelectAll" onchange="toggleAllCbItems(this)"></th>
                                <th>SKU</th>
                                <th>Product</th>
                                <th style="text-align:center;">Qty</th>
                                <th style="text-align:right;">Unit Price</th>
                                <th style="text-align:right;">Total</th>
                                <th style="min-width:180px;">Evidence Image</th>
                                <th style="width:60px;">Preview</th>
                            </tr>
                        </thead>
                        <tbody id="cbItemsBody"></tbody>
                    </table>
                </div>

                {{-- Chargeback Details --}}
                <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Amount ({{$activeCurrencySymbol}}) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="cbAmount" required placeholder="0.00" style="width:110px;font-family:monospace;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;min-width:160px;">
                        <label>Reason *</label>
                        <select name="reason" required style="width:100%;">
                            <option value="">Select reason...</option>
                            <option value="Damaged">Damaged</option>
                            <option value="Wrong Item">Wrong Item</option>
                            <option value="Missing Item">Missing Item</option>
                            <option value="Quality Issue">Quality Issue</option>
                            <option value="Short Shipment">Short Shipment</option>
                            <option value="Late Delivery">Late Delivery</option>
                            <option value="Customer Return">Customer Return</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;min-width:200px;">
                        <label>Description</label>
                        <input type="text" name="description" placeholder="Additional details...">
                    </div>
                    <button type="submit" class="btn btn-danger" id="cbSubmitBtn" disabled onclick="return confirm('Raise chargeback? Sourcing will be notified.')">
                        <i class="fas fa-exclamation-triangle" style="margin-right:.3rem;"></i> Raise Chargeback
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

 
{{-- Chargebacks Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#dc2626;"></i> Chargebacks</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Order</th><th>Vendor</th><th>Company</th><th>Amount</th><th>Reason</th><th>Raised By</th><th>Date</th><th>Sourcing Confirmation</th><th>Status</th><th>Evidence</th></tr></thead>
            <tbody>
                @forelse($chargebacks as $cb)
                <tr style="{{ $cb->status==='confirmed'?'background:#fef2f2;':'' }}">
                    <td><div style="font-weight:600;font-family:monospace;font-size:.8rem;">{{ $cb->order->order_number ?? '—' }}</div><div style="font-size:.65rem;color:#94a3b8;">{{ $cb->order->salesChannel->name ?? '' }}</div></td>
                    <td><div style="font-size:.82rem;">{{ $cb->vendor->company_name ?? '—' }}</div><div style="font-size:.65rem;color:#94a3b8;">{{ $cb->vendor->vendor_code ?? '' }}</div></td>
                    <td>{{ $cb->company_code }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#dc2626;">{{$activeCurrencySymbol}}{{ number_format($cb->amount, 2) }}</td>
                    <td><div style="font-size:.82rem;">{{ $cb->reason }}</div>@if($cb->description)<div style="font-size:.68rem;color:#64748b;">{{ Str::limit($cb->description, 50) }}</div>@endif</td>
                    <td style="font-size:.82rem;">{{ $cb->raiser->name ?? '—' }}</td>
                    <td style="font-size:.82rem;">{{ $cb->created_at->format('d M Y') }}</td>
                    <td>
                        @if($cb->confirmed_at)
                            <div style="font-size:.78rem;font-weight:600;color:{{ $cb->status==='confirmed'?'#dc2626':'#16a34a' }};">
                                {{ $cb->status==='confirmed'?'Confirmed':'Rejected' }} by {{ $cb->confirmer->name ?? '—' }}
                            </div>
                            <div style="font-size:.65rem;color:#94a3b8;">{{ $cb->confirmed_at->format('d M Y') }}</div>
                            @if($cb->confirmation_remarks)<div style="font-size:.65rem;color:#64748b;font-style:italic;">"{{ Str::limit($cb->confirmation_remarks, 40) }}"</div>@endif
                        @else
                            <span style="display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .5rem;background:#fef3c7;border-radius:5px;font-size:.72rem;color:#92400e;font-weight:600;">
                                <i class="fas fa-clock" style="font-size:.55rem;"></i> Awaiting Sourcing
                            </span>
                        @endif
                    </td>
                    <td>
                        @php $colors = ['pending_confirmation'=>'badge-warning','confirmed'=>'badge-danger','rejected'=>'badge-gray','deducted'=>'badge-info']; @endphp
                        <span class="badge {{ $colors[$cb->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$cb->status)) }}</span>
                        @if($cb->status==='confirmed')<div style="font-size:.6rem;color:#dc2626;margin-top:.1rem;">Deducted from vendor payout</div>@endif
                    </td>
       @php
    $cbItems = is_string($cb->chargeback_items) ? json_decode($cb->chargeback_items, true) : ($cb->chargeback_items ?? []);
@endphp             <td>
                        
@if(!empty($cbItems))
<div style="margin-top:.5rem;">
    <div style="font-size:.65rem;font-weight:600;color:#64748b;margin-bottom:.3rem;">ITEM EVIDENCE</div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        

        @foreach($cbItems as $cbItem)
        @php
            $orderItem = \App\Models\OrderItem::with('product')->find($cbItem['item_id'] ?? 0);
            $evidence = $cbItem['evidence'] ?? null;
        @endphp
        <div style="border:1px solid #e2e8f0;border-radius:8px;padding:.5rem;width:140px;background:#f8fafc;">
            <div style="font-size:.68rem;font-weight:600;font-family:monospace;color:#1e3a5f;margin-bottom:.3rem;">
                {{ $orderItem->sku ?? $orderItem->product->sku ?? '—' }}
            </div>
            <div style="font-size:.6rem;color:#64748b;margin-bottom:.3rem;">
                {{ Str::limit($orderItem->product->name ?? '—', 25) }}
            </div>
            @if($evidence)
                @php
                    $ext = strtolower(pathinfo($evidence, PATHINFO_EXTENSION));
                    $url = asset('storage/' . $evidence);
                @endphp
                @if(in_array($ext, ['jpg', 'jpeg', 'png']))
                    <a href="{{ $url }}" target="_blank" style="display:block;">
                        <img src="{{ $url }}" alt="Evidence"
                            style="width:100%;height:80px;object-fit:cover;border-radius:4px;border:1px solid #d1d5db;cursor:pointer;">
                    </a>
                @else
                    <a href="{{ $url }}" target="_blank" style="display:flex;align-items:center;justify-content:center;width:100%;height:80px;background:#fef2f2;border-radius:4px;border:1px solid #fca5a5;text-decoration:none;">
                        <div style="text-align:center;">
                            <i class="fas fa-file-pdf" style="font-size:1.5rem;color:#dc2626;"></i>
                            <div style="font-size:.55rem;color:#dc2626;">View PDF</div>
                        </div>
                    </a>
                @endif
            @else
                <div style="width:100%;height:80px;background:#f0f4f8;border-radius:4px;border:1px dashed #d1d5db;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-image" style="color:#d1d5db;font-size:1rem;"></i>
                </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

                    </td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-check-circle" style="font-size:2rem;color:#16a34a;display:block;margin-bottom:.5rem;"></i>No chargebacks.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($chargebacks->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $chargebacks->links('pagination::tailwind') }}</div>@endif
</div>

<script>
var cbOrderData = null;

function lookupOrder() {
    var orderNum = $('#cbOrderSearch').val().trim();
    if (!orderNum) { alert('Enter an order number'); return; }

    $('#cbOrderStatus').html('<span style="color:#e8a838;"><i class="fas fa-spinner fa-spin"></i> Looking up...</span>');

    $.get("{{ route('finance.chargebacks') }}", { lookup_order: orderNum }, function(data) {
        if (data.found) {
            cbOrderData = data;
            $('#cbOrderStatus').html('<span style="color:#16a34a;"><i class="fas fa-check-circle"></i> Order found</span>');
            showOrderDetails(data);
        } else {
            cbOrderData = null;
            $('#cbOrderStatus').html('<span style="color:#dc2626;"><i class="fas fa-times-circle"></i> Order not found</span>');
            $('#cbOrderDetails').hide();
            $('#cbSubmitBtn').prop('disabled', true);
        }
    }).fail(function() {
        $('#cbOrderStatus').html('<span style="color:#dc2626;"><i class="fas fa-times-circle"></i> Lookup failed</span>');
    });
}

function showOrderDetails(data) {
    $('#cbInfoOrder').text(data.order_number);
    $('#cbInfoDate').text(data.date || '—');
    $('#cbInfoChannel').text(data.channel || '—');
    $('#cbInfoCustomer').text(data.customer || '—');
    $('#cbInfoTotal').text('$' + parseFloat(data.total).toFixed(2));

    if (data.has_active_chargeback) {
        $('#cbExistingMsg').text('Active chargeback exists (Status: ' + data.existing_cb_status + ', Amount: $' + parseFloat(data.existing_cb_amount).toFixed(2) + ')');
        $('#cbExistingWarning').show();
        $('#cbSubmitBtn').prop('disabled', true);
    } else {
        $('#cbExistingWarning').hide();
        $('#cbSubmitBtn').prop('disabled', false);
    }

    var html = '';
    $.each(data.items, function(i, item) {
        html += '<tr>' +
            '<td style="text-align:center;">' +
                '<input type="checkbox" class="cb-item-check" name="chargeback_items[' + i + '][item_id]" value="' + item.id + '" checked>' +
            '</td>' +
            '<td style="font-family:monospace;font-weight:600;">' + item.sku + '</td>' +
            '<td style="font-size:.72rem;">' + item.name + '</td>' +
            '<td style="text-align:center;font-weight:600;">' + item.quantity + '</td>' +
            '<td style="text-align:right;font-family:monospace;">$' + parseFloat(item.unit_price).toFixed(2) + '</td>' +
            '<td style="text-align:right;font-family:monospace;font-weight:600;">$' + parseFloat(item.total).toFixed(2) + '</td>' +
            '<td>' +
                '<input type="file" name="chargeback_items[' + i + '][evidence]" accept=".jpg,.jpeg,.png,.pdf" ' +
                    'onchange="previewEvidence(this, ' + i + ')" ' +
                    'style="font-size:.68rem;width:100%;">' +
            '</td>' +
            '<td style="text-align:center;">' +
                '<div id="cbPreview' + i + '" style="width:50px;height:50px;border:1px dashed #d1d5db;border-radius:4px;display:flex;align-items:center;justify-content:center;overflow:hidden;">' +
                    '<i class="fas fa-image" style="color:#d1d5db;font-size:.8rem;"></i>' +
                '</div>' +
            '</td>' +
            '</tr>';
    });
    $('#cbItemsBody').html(html);
    $('#cbRaiseForm').attr('action', '/finance/orders/' + encodeURIComponent(data.order_number) + '/chargeback');
    $('#cbAmount').val(parseFloat(data.total).toFixed(2)).attr('max', data.total);
    $('#cbOrderDetails').show();
}

function toggleAllCbItems(master) {
    $('.cb-item-check').prop('checked', master.checked);
}

function previewEvidence(input, idx) {
    var preview = $('#cbPreview' + idx);
    if (input.files && input.files[0]) {
        var file = input.files[0];
        if (file.type.startsWith('image/')) {
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.html('<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;border-radius:4px;">');
            };
            reader.readAsDataURL(file);
        } else {
            preview.html('<i class="fas fa-file-pdf" style="color:#dc2626;font-size:1.2rem;"></i>');
        }
    }
}

$('#cbOrderSearch').on('keypress', function(e) {
    if (e.which === 13) { e.preventDefault(); lookupOrder(); }
});
</script>
@endsection
