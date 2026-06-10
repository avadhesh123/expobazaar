@extends('layouts.app')
@section('title', 'Upload Sales')
@section('page-title', 'Upload Sales Data')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('sales.orders') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Orders</a>
    <a href="{{ route('sales.download-template') }}" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Download Template</a>
</div>

{{-- Upload Errors from previous submission --}}
@if(session('upload_errors') && count(session('upload_errors')) > 0)
<div class="card" style="margin-bottom:1.25rem;border-color:#fca5a5;">
    <div class="card-header" style="background:#fef2f2;">
        <h3 style="color:#dc2626;"><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;"></i> Upload Errors ({{ count(session('upload_errors')) }})</h3>
    </div>
    <div class="card-body" style="padding:.75rem 1.4rem;max-height:200px;overflow-y:auto;">
        @foreach(session('upload_errors') as $err)
        <div style="font-size:.78rem;color:#991b1b;padding:.2rem 0;border-bottom:1px solid #fee2e2;">{{ $err }}</div>
        @endforeach
    </div>
</div>
@endif

{{-- Info --}}
<div style="padding:.75rem 1.2rem;background:#eff6ff;border-radius:10px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.82rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
    <strong>Important:</strong> Sales channel must exist in the Sales Channel Master. Orders with unrecognized channels will be rejected.
    Available channels: @foreach($channels as $ch)<span style="padding:.1rem .3rem;background:#dbeafe;border-radius:3px;font-size:.72rem;margin:.1rem;">{{ $ch->name }}</span>@endforeach
</div>
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-upload" style="margin-right:.5rem;color:#e8a838;"></i> Upload Sales File</h3>
        <a href="{{ route('sales.upload.template') }}" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Download Template</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('sales.upload.store') }}" enctype="multipart/form-data">
            @csrf
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
                <!-- <div style="min-width:120px;">
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company Code *</label>
                    <select name="company_code" required style="padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                        <option value="2000">🇮🇳 2000</option>
                        <option value="2100" selected>🇺🇸 2100</option>
                        <option value="2200">🇳🇱 2200</option>
                    </select>
                </div> -->
                <input type="hidden" name="company_code" value="{{$activeCompany}}">
                <div style="flex:1;min-width:250px;">
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Sales File (CSV / XLSX) *</label>
                    <input type="file" name="sales_file" required accept=".csv,.xlsx,.xls" style="font-size:.82rem;">
                </div>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Upload and process sales data?')"><i class="fas fa-upload" style="margin-right:.3rem;"></i> Upload & Process</button>
            </div>
        </form>
    </div>
</div>
{{-- Manual Entry Form --}}
<form method="POST" action="{{ route('sales.upload.manual') }}" id="salesForm">
    @csrf

    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header">
            <h3><i class="fas fa-keyboard" style="margin-right:.5rem;color:#1e3a5f;"></i> Enter Sales Data</h3>
            <!-- <div style="display:flex;gap:.5rem;align-items:center;">
                <select name="company_code" required style="padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-weight:600;font-family:inherit;">
                    <option value="2000">🇮🇳 2000 – India</option>
                    <option value="2100" selected>🇺🇸 2100 – USA</option>
                    <option value="2200">🇳🇱 2200 – NL</option>
                </select>
            </div> -->
            <input type="hidden" name="company_code" value="{{$activeCompany}}">
        </div>
        <div class="card-body" style="padding:1rem 1.4rem;" id="ordersContainer">

            {{-- Order 0 --}}
            <div class="order-block" data-order-idx="0" style="border:1px solid #e2e8f0;border-radius:8px;padding:1rem;margin-bottom:1rem;background:#fafbfc;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                    <h4 style="margin:0;font-size:.88rem;color:#1e3a5f;"><i class="fas fa-file-invoice" style="margin-right:.3rem;"></i> Order #1</h4>
                    <button type="button" class="btn btn-outline btn-sm" onclick="removeOrder(this)" style="color:#dc2626;border-color:#dc2626;display:none;"><i class="fas fa-trash"></i></button>
                </div>

                {{-- Order Header Row 1 --}}
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr 1fr;gap:.6rem;margin-bottom:.6rem;">
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Sales Channel *</label>
                        <select name="orders[0][sales_channel]" required style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                            <option value="">Select Channel...</option>
                            @foreach($channels as $ch)<option value="{{ $ch->name }}">{{ $ch->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Warehouse *</label>
                        <select name="orders[0][warehouse_id_number]" required style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                            <option value="">Select Warehouse...</option>
                            @foreach($warehouses as $wh)<option value="{{ $wh->code }}">{{ $wh->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">PO / Order ID *</label>
                        <input type="text" name="orders[0][platform_order_id]" required placeholder="AMZ-12345" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Order Date *</label>
                        <input type="date" name="orders[0][order_date]" required value="{{ date('Y-m-d') }}" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Shipping Method</label>
                        <select name="orders[0][shipping_method]" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                            <option value="">Select...</option>
                            <option value="SP">SP - Store Pickup</option>
                            <option value="MPL">MPL - Marketplace Label</option>
                            <option value="EBL">EBL - EB Label</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Payment Status</label>
                        <select name="orders[0][payment_status]" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                            <option value="unpaid">Unpaid</option>
                            <option value="paid">Paid</option>
                            <option value="partial">Partial</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>

                </div>

                {{-- Order Header Row 2 — Customer --}}
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr 1fr;gap:.6rem;margin-bottom:.6rem;">
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Currency</label>
                        <select name="orders[0][currency]" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                        </select>

                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Customer Name</label>
                        <input type="text" name="orders[0][customer_name]" placeholder="Name..." style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Customer Email</label>
                        <input type="email" name="orders[0][customer_email]" placeholder="email..." style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Customer Phone</label>
                        <input type="text" name="orders[0][customer_phone]" placeholder="Phone..." style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Customer Type</label>
                        <select name="orders[0][customer_type]" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                            <option value="">Select...</option>
                            <option value="CFL">CFL</option>
                            <option value="B2B">B2B</option>
                            <option value="D2C">D2C</option>
                            <option value="Wholesale">Wholesale</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Company Name</label>
                        <input type="text" name="orders[0][company_name]" placeholder="Company..." style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                </div>

                {{-- Order Header Row 3 — Shipping Address --}}
                <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:.6rem;margin-bottom:.75rem;">
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Shipping Address</label>
                        <input type="text" name="orders[0][shipping_address]" placeholder="Address..." style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">City</label>
                        <input type="text" name="orders[0][shipping_city]" placeholder="City..." style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">State</label>
                        <input type="text" name="orders[0][shipping_state]" placeholder="State..." style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Zip Code</label>
                        <input type="text" name="orders[0][shipping_pincode]" placeholder="Zip..." style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                    <div>
                        <label style="font-size:.65rem;font-weight:600;color:#64748b;display:block;margin-bottom:.2rem;">Country</label>
                        <input type="text" name="orders[0][shipping_country]" placeholder="US" value="US" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
                    </div>
                </div>

                {{-- Items Table --}}
                <table class="data-table" style="font-size:.78rem;margin-bottom:.5rem;">
                    <thead>
                        <tr style="background:#f0f4f8;">
                            <th style="width:30px;">#</th>
                            <th style="width:200px;min-width:130px;">SKU *</th>
                            <th style="width:200px;">Product Name</th>
                            <th style="width:70px;">Qty *</th>
                            <th style="width:90px;">Unit Price *</th>
                            <th style="width:100px;text-align: right;">Line Total</th>
                            <th style="width:10px;"></th>
                        </tr>
                    </thead>
                    <tbody class="items-body">
                        <tr class="item-row">
                            <td style="text-align:center;color:#94a3b8;font-weight:600;">1</td>
                            <td>
                                <input type="text" name="orders[0][items][0][sku]" required placeholder="Type SKU..."
                                    class="sku-input" data-order="0" data-item="0"
                                    style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:monospace;">
                                <div class="sku-status" style="font-size:.6rem;margin-top:.15rem;"></div>
                            </td>
                            <td class="product-name" style="font-size:.72rem;color:#64748b;">—</td>
                            <td><input type="number" name="orders[0][items][0][quantity]" value="1" min="1" required
                                    class="qty-input" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;text-align:center;"></td>
                            <td><input type="number" step="0.01" name="orders[0][items][0][unit_price]" placeholder="0.00" required
                                    class="price-input" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:monospace;text-align:right;"></td>
                            <td class="line-total" style="text-align:right;font-family:monospace;font-weight:600;">{{$activeCurrencySymbol}}0.00</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="addItemRow(this)"><i class="fas fa-plus"></i> Add Item</button>
                    <div style="font-size:.85rem;font-weight:700;color:#1e3a5f;">
                        Order Total: <span class="order-total" style="font-family:monospace;">{{$activeCurrencySymbol}}0.00</span>
                        <input type="hidden" name="orders[0][total_amount]" class="order-total-input" value="0">
                    </div>
                </div>
            </div>

        </div>

        <div style="padding:0 1.4rem 1rem;">
            <button type="button" class="btn btn-outline" onclick="addOrder()"><i class="fas fa-plus-circle" style="margin-right:.3rem;"></i> Add Another Order</button>
        </div>
    </div>

    <div style="display:flex;gap:.5rem;justify-content:flex-end;">
        <a href="{{ route('sales.orders') }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" onclick="return validateSales()"><i class="fas fa-upload" style="margin-right:.3rem;"></i> Create Orders</button>
    </div>
</form>

@push('scripts')
<script>
    var orderCount = 1;
    var channelOptions = '{!! $channels->map(fn($c) => "<option value=\"" . e($c->name) . "\">" . e($c->name) . "</option>")->implode("") !!}';
    var skuCache = {};
    var skuTimer = null;

    // ── SKU Validation (debounced) ──
    $(document).on('input', '.sku-input', function() {
        var input = $(this);
        var sku = input.val().trim();
        var statusDiv = input.closest('td').find('.sku-status');
        var nameCell = input.closest('tr').find('.product-name');

        if (sku.length < 2) {
            statusDiv.html('');
            nameCell.text('—');
            input.css('border-color', '#d1d5db');
            return;
        }

        clearTimeout(skuTimer);
        skuTimer = setTimeout(function() {
            if (skuCache[sku] !== undefined) {
                applySkuResult(input, skuCache[sku]);
                return;
            }

            statusDiv.html('<span style="color:#e8a838;"><i class="fas fa-spinner fa-spin"></i> Checking...</span>');

            $.get("{{ route('sales.upload') }}", {
                check_sku: sku
            }, function(data) {
                skuCache[sku] = data;
                applySkuResult(input, data);
            }).fail(function() {
                statusDiv.html('<span style="color:#dc2626;"><i class="fas fa-times-circle"></i> Error checking SKU</span>');
            });
        }, 400);
    });

    function applySkuResult(input, data) {
        var statusDiv = input.closest('td').find('.sku-status');
        var nameCell = input.closest('tr').find('.product-name');

        if (data.found) {
            input.css('border-color', '#16a34a');
            statusDiv.html('<span style="color:#16a34a;"><i class="fas fa-check-circle"></i> ' + (data.sap_code || 'No SAP') + ' · Stock: ' + data.stock + '</span>');
            nameCell.text(data.name || '—');
        } else {
            input.css('border-color', '#dc2626');
            statusDiv.html('<span style="color:#dc2626;"><i class="fas fa-times-circle"></i> SKU not found</span>');
            nameCell.text('—');
        }
    }

    // ── Auto-calculate line totals ──
    $(document).on('input', '.qty-input, .price-input', function() {
        var row = $(this).closest('tr');
        var qty = parseFloat(row.find('.qty-input').val()) || 0;
        var price = parseFloat(row.find('.price-input').val()) || 0;
        var total = (qty * price).toFixed(2);
        row.find('.line-total').text('$' + total);
        recalcOrderTotal($(this).closest('.order-block'));
    });

    function recalcOrderTotal(block) {
        var total = 0;
        block.find('.line-total').each(function() {
            total += parseFloat($(this).text().replace('$', '')) || 0;
        });
        block.find('.order-total').text('$' + total.toFixed(2));
        block.find('.order-total-input').val(total.toFixed(2));
    }

    // ── Add Item Row to an Order ──
    function addItemRow(btn) {
        var block = $(btn).closest('.order-block');
        var orderIdx = block.data('order-idx');
        var itemCount = block.find('.item-row').length;

        var row = $('<tr class="item-row">').html(
            '<td style="text-align:center;color:#94a3b8;font-weight:600;">' + (itemCount + 1) + '</td>' +
            '<td>' +
            '<input type="text" name="orders[' + orderIdx + '][items][' + itemCount + '][sku]" required placeholder="Type SKU..." class="sku-input" data-order="' + orderIdx + '" data-item="' + itemCount + '" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:monospace;">' +
            '<div class="sku-status" style="font-size:.6rem;margin-top:.15rem;"></div>' +
            '</td>' +
            '<td class="product-name" style="font-size:.72rem;color:#64748b;">—</td>' +
            '<td><input type="number" name="orders[' + orderIdx + '][items][' + itemCount + '][quantity]" value="1" min="1" required class="qty-input" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;text-align:center;"></td>' +
            '<td><input type="number" step="0.01" name="orders[' + orderIdx + '][items][' + itemCount + '][unit_price]" placeholder="0.00" required class="price-input" style="width:100%;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:monospace;text-align:right;"></td>' +
            '<td class="line-total" style="text-align:right;font-family:monospace;font-weight:600;">$0.00</td>' +
            '<td><button type="button" class="btn btn-outline btn-sm" onclick="removeItemRow(this)" style="color:#dc2626;border-color:#dc2626;padding:.15rem .3rem;"><i class="fas fa-times"></i></button></td>'
        );

        block.find('.items-body').append(row);
    }

    function removeItemRow(btn) {
        var block = $(btn).closest('.order-block');
        if (block.find('.item-row').length > 1) {
            $(btn).closest('tr').remove();
            renumberItems(block);
            recalcOrderTotal(block);
        }
    }

    function renumberItems(block) {
        block.find('.item-row').each(function(i) {
            $(this).find('td:first').text(i + 1);
        });
    }

    // ── Add Another Order ──
    function addOrder() {
        var idx = orderCount;
        var html = $('.order-block:first')[0].outerHTML;

        // Replace all [0] with [idx]
        html = html.replace(/orders\[0\]/g, 'orders[' + idx + ']');
        html = html.replace(/data-order-idx="0"/g, 'data-order-idx="' + idx + '"');
        html = html.replace(/data-order="0"/g, 'data-order="' + idx + '"');
        html = html.replace('Order #1', 'Order #' + (idx + 1));

        var newBlock = $(html);
        // Clear values
        newBlock.find('input[type="text"], input[type="email"], input[type="number"]').val('');
        newBlock.find('input[type="date"]').val('{{ date("Y-m-d") }}');
        newBlock.find('.qty-input').val('1');
        newBlock.find('select').prop('selectedIndex', 0);
        newBlock.find('.sku-status').html('');
        newBlock.find('.product-name').text('—');
        newBlock.find('.line-total').text('{{$activeCurrencySymbol}}0.00');
        newBlock.find('.order-total').text('{{$activeCurrencySymbol}}0.00');
        newBlock.find('.order-total-input').val('0');
        // Show remove button
        newBlock.find('h4').next('button').show();
        // Remove extra item rows (keep only first)
        newBlock.find('.item-row:not(:first)').remove();

        $('#ordersContainer').append(newBlock);
        orderCount++;
    }

    function removeOrder(btn) {
        if ($('.order-block').length > 1) {
            $(btn).closest('.order-block').remove();
        }
    }

    // ── Validate before submit ──
    function validateSales() {
        var hasError = false;
        $('.sku-input').each(function() {
            var sku = $(this).val().trim();
            if (sku && skuCache[sku] && !skuCache[sku].found) {
                hasError = true;
                $(this).css('border-color', '#dc2626');
            }
        });
        if (hasError) {
            alert('Some SKUs are invalid. Please fix them before submitting.');
            return false;
        }

        var orderCount = $('.order-block').length;
        return confirm('Create ' + orderCount + ' order(s)?');
    }
</script>

@endpush
@endsection