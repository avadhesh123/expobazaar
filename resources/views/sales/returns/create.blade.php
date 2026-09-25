@extends('layouts.app')
@section('title', 'Create Return')
@section('page-title', 'Initiate Order Return')

@section('content')
<a href="{{ route('sales.returns') }}" class="btn btn-outline btn-sm" style="margin-bottom:1rem;"><i class="fas fa-arrow-left"></i> Back to Returns</a>

{{-- Order Lookup --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-search" style="margin-right:.5rem;color:#1e40af;"></i> Find Order</h3></div>
    <div class="card-body">
        <div style="display:flex;gap:.5rem;align-items:flex-end;">
            <div class="form-group" style="margin-bottom:0;flex:1;max-width:300px;">
                <label>Order Number or PO Number *</label>
                <input type="text" id="returnOrderSearch" placeholder="Enter Order # or PO #">
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="lookupReturnOrder()"><i class="fas fa-search"></i> Lookup</button>
            <span id="returnLookupStatus" style="font-size:.78rem;"></span>
        </div>
    </div>
</div>

{{-- Order Details + Return Form (hidden until lookup) --}}
<div id="returnFormSection" style="display:none;">
    {{-- Order Info --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem;margin-bottom:1rem;padding:.75rem;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
        <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Order #</div><div id="retInfoOrder" style="font-weight:700;font-family:monospace;"></div></div>
        <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Date</div><div id="retInfoDate"></div></div>
        <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Channel</div><div id="retInfoChannel"></div></div>
        <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Customer</div><div id="retInfoCustomer"></div></div>
        <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Order Total</div><div id="retInfoTotal" style="font-weight:700;font-family:monospace;"></div></div>
    </div>

    <form method="POST" action="{{ route('sales.returns.store') }}" id="returnForm">
        @csrf
        <input type="hidden" name="order_id" id="returnOrderId">

        {{-- Items Selection --}}
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header"><h3><i class="fas fa-boxes" style="margin-right:.5rem;color:#e8a838;"></i> Select Items to Return</h3></div>
            <div class="card-body" style="padding:0;overflow-x:auto;">
                <table class="data-table" style="font-size:.78rem;">
                    <thead><tr style="background:#f0f4f8;">
                        <th style="width:30px;"><input type="checkbox" id="retSelectAll" onchange="document.querySelectorAll('.ret-item-check').forEach(c=>c.checked=this.checked)"></th>
                        <th>SKU</th><th>Product</th><th style="text-align:center;">Ordered</th><th style="text-align:center;">Shipped</th>
                        <th style="text-align:center;background:#fef2f2;">Return Qty</th><th style="text-align:right;">Unit Price</th>
                        <th style="text-align:right;background:#fef2f2;">Return Amt</th>
                        <th style="min-width:120px;">Condition</th>
                    </tr></thead>
                    <tbody id="retItemsBody"></tbody>
                    <tfoot><tr style="background:#f0f4f8;font-weight:700;">
                        <td colspan="7" style="text-align:right;">Total Return:</td>
                        <td style="text-align:right;font-family:monospace;color:#dc2626;" id="retTotalAmount">{{ $activeCurrencySymbol }}0.00</td>
                        <td></td>
                    </tr></tfoot>
                </table>
            </div>
        </div>

        {{-- Return Details --}}
        <div class="card" style="margin-bottom:1.25rem;">
            <div class="card-header"><h3><i class="fas fa-file-alt" style="margin-right:.5rem;color:#7c3aed;"></i> Return Details</h3></div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
                   <div class="form-group" style="margin-bottom:0;">
                        <label>Return Date *</label>
                        <input type="date" 
                            name="return_date" 
                            value="{{ old('return_date', now()->toDateString()) }}" 
                            required>                    
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Reason *</label>
                        <select name="reason" required>
                            <option value="">Select reason...</option>
                            <option value="damaged">Damaged</option>
                            <option value="wrong_item">Wrong Item</option>
                            <option value="missing_item">Missing Item</option>
                            <option value="quality_issue">Quality Issue</option>
                            <option value="customer_request">Customer Request</option>
                            <option value="short_shipment">Short Shipment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Return to Warehouse</label>
                        <select name="warehouse_id">
                            <option value="">Select...</option>
                            @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Tracking ID</label>
                        <input type="text" name="tracking_id" placeholder="Return tracking...">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Carrier</label>
                        <select name="carrier">
                            <option value="">Select...</option>
                            <option value="Fedex">Fedex</option><option value="UPS">UPS</option>
                            <option value="USPS">USPS</option><option value="LTL">LTL</option><option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Additional Details</label>
                    <textarea name="reason_detail" placeholder="Describe the return reason in detail..." style="min-height:60px;"></textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:.5rem;justify-content:flex-end;">
            <a href="{{ route('sales.returns') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Initiate this return?')"><i class="fas fa-undo-alt" style="margin-right:.3rem;"></i> Create Return</button>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script>
var currency = '{{ $activeCurrencySymbol }}';

function lookupReturnOrder() {
    var num = $('#returnOrderSearch').val().trim();
    if (!num) { alert('Enter an order number.'); return; }

    $('#returnLookupStatus').html('<span style="color:#e8a838;"><i class="fas fa-spinner fa-spin"></i> Looking up...</span>');

    $.get("{{ route('sales.returns.create') }}", { lookup_order: num }, function(data) {
        if (data.found) {
            $('#returnLookupStatus').html('<span style="color:#16a34a;"><i class="fas fa-check-circle"></i> Found</span>');
            showReturnOrder(data);
        } else {
            $('#returnLookupStatus').html('<span style="color:#dc2626;"><i class="fas fa-times-circle"></i> Not found</span>');
            $('#returnFormSection').hide();
        }
    }).fail(function() {
        $('#returnLookupStatus').html('<span style="color:#dc2626;">Lookup failed</span>');
    });
}

function showReturnOrder(data) {
    $('#returnOrderId').val(data.order_id);
    $('#retInfoOrder').text(data.order_number);
    $('#retInfoDate').text(data.date || '—');
    $('#retInfoChannel').text(data.channel || '—');
    $('#retInfoCustomer').text(data.customer || '—');
    $('#retInfoTotal').text(currency + parseFloat(data.total).toFixed(2));

    var html = '';
    $.each(data.items, function(i, item) {
        html += '<tr>' +
            '<td style="text-align:center;"><input type="checkbox" class="ret-item-check" data-idx="' + i + '" checked onchange="toggleReturnItem(' + i + ')"></td>' +
            '<td style="font-family:monospace;font-weight:600;">' + item.sku + '</td>' +
            '<td style="font-size:.72rem;">' + item.name + '</td>' +
            '<td style="text-align:center;">' + item.quantity + '</td>' +
            '<td style="text-align:center;font-weight:600;">' + item.shipped_qty + '</td>' +
            '<td style="text-align:center;background:#fef2f2;">' +
                '<input type="hidden" name="items[' + i + '][product_id]" value="' + item.product_id + '">' +
                '<input type="number" name="items[' + i + '][return_qty]" value="' + item.shipped_qty + '" min="0" max="' + item.shipped_qty + '" ' +
                    'class="ret-qty" data-idx="' + i + '" data-price="' + item.unit_price + '" oninput="calcReturnTotals()" ' +
                    'style="width:55px;padding:.2rem .3rem;border:1px solid #fca5a5;border-radius:4px;font-size:.78rem;text-align:center;">' +
            '</td>' +
            '<td style="text-align:right;font-family:monospace;">' + currency + parseFloat(item.unit_price).toFixed(2) + '</td>' +
            '<td style="text-align:right;font-family:monospace;font-weight:600;color:#dc2626;background:#fef2f2;" id="retAmt' + i + '">' + currency + (item.unit_price * item.shipped_qty).toFixed(2) + '</td>' +
            '<td><select name="items[' + i + '][condition_status]" style="padding:.2rem .3rem;border:1px solid #d1d5db;border-radius:4px;font-size:.75rem;">' +
                '<option value="good">Good</option><option value="damaged">Damaged</option><option value="defective">Defective</option><option value="unsellable">Unsellable</option>' +
            '</select></td>' +
            '</tr>';
    });
    $('#retItemsBody').html(html);
    calcReturnTotals();
    $('#returnFormSection').show();
}

function toggleReturnItem(idx) {
    var checked = $('.ret-item-check[data-idx="' + idx + '"]').is(':checked');
    $('input[name="items[' + idx + '][return_qty]"]').prop('disabled', !checked).val(checked ? $('input[name="items[' + idx + '][return_qty]"]').attr('max') : 0);
    calcReturnTotals();
}

function calcReturnTotals() {
    var total = 0;
    $('.ret-qty').each(function() {
        var idx = $(this).data('idx');
        var qty = parseInt($(this).val()) || 0;
        var price = parseFloat($(this).data('price')) || 0;
        var amt = qty * price;
        $('#retAmt' + idx).text(currency + amt.toFixed(2));
        if (!$(this).prop('disabled')) total += amt;
    });
    $('#retTotalAmount').text(currency + total.toFixed(2));
}

$('#returnOrderSearch').on('keypress', function(e) { if (e.which === 13) { e.preventDefault(); lookupReturnOrder(); } });
</script>
@endsection
