@extends('layouts.app')
@section('title', 'Order Management')

@section('content')

<div style="padding:1.5rem 2rem 2rem;">
    
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
        <h2 style="margin:0;font-weight:700;color:#1e3a5f;">
            <i class="fas fa-tasks" style="margin-right:10px;color:#2d6a4f;"></i> 
            Order Management
        </h2>
        <a href="{{ route('sales.upload') }}" class="btn btn-primary">
            <i class="fas fa-upload"></i> Upload Sales Data
        </a>
    </div>

    {{-- Filters --}}
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-body" style="padding:1.25rem 1.5rem;">
            <form method="GET" action="{{ route('sales.order-management') }}" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;">
                <!-- Your existing filters -->
                <div style="min-width:140px;">
                    <label style="font-size:0.8rem;font-weight:600;color:#64748b;display:block;margin-bottom:6px;">Company</label>
                    <select name="company_code" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;">
                        <option value="">All Companies</option>
                        <option value="2000" {{ request('company_code')=='2000'?'selected':'' }}>2000</option>
                        <option value="2100" {{ request('company_code')=='2100'?'selected':'' }}>2100</option>
                        <option value="2200" {{ request('company_code')=='2200'?'selected':'' }}>2200</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('sales.order-management') }}" class="btn btn-outline">Reset</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table" style="margin-bottom:0;">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th style="padding:14px 12px;">Order Date</th>
                        <th style="padding:14px 12px;">Order Number</th>
                        <th style="padding:14px 12px;">PO / Platform ID</th>
                        <th style="padding:14px 12px;">Sales Channel</th>
                        <th style="padding:14px 12px;">Style Code</th>
                        <th style="padding:14px 12px;text-align:right;">Unit Price</th>
                        <th style="padding:14px 12px;text-align:center;">Qty</th>
                        <th style="padding:14px 12px;text-align:right;">Order Amount</th>
                        <th style="padding:14px 12px;">Material Cost</th>
                        <th style="padding:14px 12px;">Order Processing Charges</th>
                        <th style="padding:14px 12px;">Status</th>
                        <th style="padding:14px 12px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr data-order-id="{{ $order->id }}">
                            <td>{{ $order->order_date?->format('d M Y') }}</td>
                            <td><strong>{{ $order->order_number }}</strong></td>
                            <td>{{ $order->platform_order_id ?? '-' }}</td>
                            <td>{{ $order->salesChannel?->name ?? '-' }}</td>
                            <td>{{ $order->items->first()?->sku ?? '-' }}</td>
                            <td style="text-align:right;">{{ number_format($order->items->first()?->unit_price ?? 0, 2) }}</td>
                            <td style="text-align:center;">{{ $order->items->sum('quantity') }}</td>
                            <td style="text-align:right;font-weight:600;">{{ number_format($order->total_amount, 2) }}</td>

                            <!-- Material Cost -->
                            <td>
                                <input type="number" 
                                       class="material-cost-input"
                                       value="{{ $order->items->first()?->material_cost ?? '' }}"
                                       step="0.01" 
                                       style="width:100px;padding:6px;border:1px solid #d1d5db;border-radius:6px;">
                            </td>

                            <!-- Order Processing Charges -->
                            <td>
                                <input type="number" 
                                       class="processing-charges-input"
                                       value="{{ $order->items->first()?->processing_charges ?? '' }}"
                                       step="0.01" 
                                       style="width:100px;padding:6px;border:1px solid #d1d5db;border-radius:6px;">
                            </td>

                            <td>
                                <span class="badge" style="padding:6px 12px;border-radius:20px; background:{{ $order->status == 'delivered' ? '#dcfce7' : ($order->status == 'shipped' ? '#dbeafe' : '#fef3c7') }}; color:{{ $order->status == 'delivered' ? '#166534' : ($order->status == 'shipped' ? '#1e40af' : '#92400e') }};">
                                    {{ ucfirst($order->status ?? 'Pending') }}
                                </span>
                            </td>

                            <td>
                                <button class="btn btn-sm btn-success save-costs-btn" 
                                        style="padding:6px 12px;">
                                    <i class="fas fa-save"></i> Save
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" style="text-align:center;padding:3rem;">No orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="padding:1rem 1.5rem;border-top:1px solid #e2e8f0;">
            {{ $orders->links() }}
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    $('.save-costs-btn').on('click', function() {
        const btn = $(this);
        const row = btn.closest('tr');
        const orderId = row.data('order-id');

        const materialCost = row.find('.material-cost-input').val();
        const processingCharges = row.find('.processing-charges-input').val();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: '/sales/order-management/' + orderId + '/update-costs',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                material_cost: materialCost,
                processing_charges: processingCharges
            },
            success: function(response) {
                if(response.success) {
                    btn.html('<i class="fas fa-check"></i> Saved').addClass('btn-success');
                    setTimeout(() => {
                        btn.html('<i class="fas fa-save"></i> Save').prop('disabled', false);
                    }, 1500);
                }
            },
            error: function() {
                alert('Failed to save data. Please try again.');
                btn.html('<i class="fas fa-save"></i> Save').prop('disabled', false);
            }
        });
    });

});
</script>

@endsection