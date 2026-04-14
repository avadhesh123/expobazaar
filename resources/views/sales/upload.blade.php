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
    <div class="card-header" style="background:#fef2f2;"><h3 style="color:#dc2626;"><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;"></i> Upload Errors ({{ count(session('upload_errors')) }})</h3></div>
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

{{-- Manual Entry Form --}}
<form method="POST" action="{{ route('sales.upload.store') }}" id="salesForm">
    @csrf

    <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header">
            <h3><i class="fas fa-keyboard" style="margin-right:.5rem;color:#1e3a5f;"></i> Enter Sales Data</h3>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <select name="company_code" required style="padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-weight:600;font-family:inherit;">
                    <option value="2000">🇮🇳 2000 – India</option>
                    <option value="2100" selected>🇺🇸 2100 – USA</option>
                    <option value="2200">🇳🇱 2200 – NL</option>
                </select>
                <button type="button" class="btn btn-outline btn-sm" onclick="addOrderRow()"><i class="fas fa-plus"></i> Add Row</button>
            </div>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table" id="ordersTable">
                <thead>
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Sales Channel *</th>
                        <th>Platform Order ID *</th>
                        <th>Order Date *</th>
                        <th>Customer Name</th>
                        <th>Customer Email</th>
                        <th>SKU</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total Amount *</th>
                        <th>Currency</th>
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody id="ordersBody">
                    <tr id="row0">
                        <td style="text-align:center;color:#94a3b8;font-weight:600;">1</td>
                        <td><select name="orders[0][sales_channel]" required style="width:120px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:inherit;"><option value="">Select...</option>@foreach($channels as $ch)<option value="{{ $ch->name }}">{{ $ch->name }}</option>@endforeach</select></td>
                        <td><input type="text" name="orders[0][platform_order_id]" required placeholder="AMZ-12345" style="width:120px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;"></td>
                        <td><input type="date" name="orders[0][order_date]" required value="{{ date('Y-m-d') }}" style="padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;"></td>
                        <td><input type="text" name="orders[0][customer_name]" placeholder="Name..." style="width:110px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;"></td>
                        <td><input type="email" name="orders[0][customer_email]" placeholder="email..." style="width:130px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;"></td>
                        <td><input type="text" name="orders[0][items][0][sku]" placeholder="SKU-001" style="width:90px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:monospace;"></td>
                        <td><input type="number" name="orders[0][items][0][quantity]" value="1" min="1" style="width:55px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;text-align:center;"></td>
                        <td><input type="number" step="0.01" name="orders[0][items][0][unit_price]" placeholder="0.00" style="width:80px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:monospace;text-align:right;"></td>
                        <td><input type="number" step="0.01" name="orders[0][total_amount]" required placeholder="0.00" style="width:90px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;font-family:monospace;text-align:right;background:#eff6ff;font-weight:600;"></td>
                        <td><select name="orders[0][currency]" style="width:65px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:inherit;"><option value="USD">USD</option><option value="EUR">EUR</option><option value="INR">INR</option><option value="GBP">GBP</option></select></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div style="display:flex;gap:.5rem;justify-content:flex-end;">
        <a href="{{ route('sales.orders') }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" onclick="return validateSales()"><i class="fas fa-upload" style="margin-right:.3rem;"></i> Upload Sales Data</button>
    </div>
</form>

@push('scripts')
<script>
var rowCount = 1;
var channelOptions = `<option value="">Select...</option>@foreach($channels as $ch)<option value="{{ $ch->name }}">{{ $ch->name }}</option>@endforeach`;

function addOrderRow() {
    var idx = rowCount;
    var row = document.createElement('tr');
    row.id = 'row' + idx;
    row.innerHTML = `
        <td style="text-align:center;color:#94a3b8;font-weight:600;">${idx + 1}</td>
        <td><select name="orders[${idx}][sales_channel]" required style="width:120px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:inherit;">${channelOptions}</select></td>
        <td><input type="text" name="orders[${idx}][platform_order_id]" required placeholder="Order ID" style="width:120px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;"></td>
        <td><input type="date" name="orders[${idx}][order_date]" required value="${new Date().toISOString().split('T')[0]}" style="padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;"></td>
        <td><input type="text" name="orders[${idx}][customer_name]" placeholder="Name..." style="width:110px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;"></td>
        <td><input type="email" name="orders[${idx}][customer_email]" placeholder="email..." style="width:130px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;"></td>
        <td><input type="text" name="orders[${idx}][items][0][sku]" placeholder="SKU" style="width:90px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:monospace;"></td>
        <td><input type="number" name="orders[${idx}][items][0][quantity]" value="1" min="1" style="width:55px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;text-align:center;"></td>
        <td><input type="number" step="0.01" name="orders[${idx}][items][0][unit_price]" placeholder="0.00" style="width:80px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:monospace;text-align:right;"></td>
        <td><input type="number" step="0.01" name="orders[${idx}][total_amount]" required placeholder="0.00" style="width:90px;padding:.3rem .4rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.78rem;font-family:monospace;text-align:right;background:#eff6ff;font-weight:600;"></td>
        <td><select name="orders[${idx}][currency]" style="width:65px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;font-family:inherit;"><option value="USD">USD</option><option value="EUR">EUR</option><option value="INR">INR</option><option value="GBP">GBP</option></select></td>
        <td><button type="button" onclick="document.getElementById('row${idx}').remove()" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:.82rem;"><i class="fas fa-trash"></i></button></td>
    `;
    document.getElementById('ordersBody').appendChild(row);
    rowCount++;
}

function validateSales() {
    var rows = document.querySelectorAll('#ordersBody tr');
    if (rows.length === 0) { alert('Add at least one order.'); return false; }
    return confirm('Upload ' + rows.length + ' order(s)?');
}
</script>
@endpush
@endsection
