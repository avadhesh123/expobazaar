@extends('layouts.app')
@section('title', 'Orders')
@section('page-title', 'Sales Orders')

@section('content')
{{-- KPI Stats --}}
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card">
        <div class="kpi-label">Total Orders</div>
        <div class="kpi-value"> {{ number_format($stats['total_orders'] ?? 0) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;">
        <div class="kpi-label">Total Revenue</div>
        <div class="kpi-value" style="color:#16a34a;font-size:1.2rem;">{{$activeCurrencySymbol}} {{ number_format($stats['total_revenue'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;">
        <div class="kpi-label">Pending</div>
        <div class="kpi-value" style="color:#e8a838;">{{ number_format($stats['pending_orders'] ?? 0) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #1e40af;">
        <div class="kpi-label">Today</div>
        <div class="kpi-value" style="color:#1e40af;">{{ number_format($stats['today_orders'] ?? 0) }}</div>
        <div style="font-size:.65rem;color:#94a3b8;">{{$activeCurrencySymbol}} {{ number_format($stats['today_revenue'] ?? 0, 0) }}</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">

        <div style="display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end;">

            {{-- Filters Form --}}
            <form method="GET" action="{{ route('sales.orders') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end; flex:1;">

                <div style="flex:1; min-width:180px;">
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Order #, platform ID..."
                        style="width:100%;padding:.45rem .55rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                </div>

                <!-- <div style="min-width:110px;">
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label>
                    <select name="company_code" style="width:100%;padding:.45rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                        <option value="">All</option>
                        <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>2000</option>
                        <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>2100</option>
                        <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>2200</option>
                    </select>
                </div> -->

                <div style="min-width:140px;">
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Channel</label>
                    <select name="sales_channel_id" style="width:100%;padding:.45rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                        <option value="">All</option>
                        @foreach($channels as $ch)
                        <option value="{{ $ch->id }}" {{ request('sales_channel_id')==(string)$ch->id?'selected':'' }}>
                            {{ $ch->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div style="min-width:120px;">
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label>
                    <select name="status" style="width:100%;padding:.45rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                        <option value="">All</option>
                        @foreach(['open','pending','label_created','processing','shipped','delivered','cancelled','returned','exception','lost_in_transit'] as $s)
                        <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:22px;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="{{ route('sales.orders') }}" class="btn btn-outline btn-sm" style="margin-top:22px;">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>

            {{-- Download CSV Button - Aligned to right --}}
            <div style="margin-top:22px;">
                <a href="{{ route('sales.orders.download', request()->query()) }}"
                    class="btn btn-success btn-sm"
                    style="padding:8px 16px; white-space:nowrap;">
                    <i class="fas fa-download"></i> Download CSV
                </a>
            </div>

        </div>
    </div>
</div>

{{-- Orders Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-shopping-cart" style="margin-right:.5rem;color:#1e3a5f;"></i> Orders</h3><span style="font-size:.78rem;color:#64748b;">{{ $orders->total() }} orders</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Channel</th>
                    <th>Items</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                <tr>
                    <td style="font-family:monospace;font-weight:600;font-size:.78rem;">
                        <div>{{ $o->order_number }}</div>
                        <div style="font-size:.62rem;color:#94a3b8;">{{ $o->platform_order_id }}</div>
                    </td>
                    <td style="font-size:.78rem;">{{ $o->order_date ? \Carbon\Carbon::parse($o->order_date)->format('d M Y') : '—' }}</td>
                    <td style="font-size:.78rem;">{{ $o->customer_name ?? '—' }}
                        <div style="font-size:.62rem;color:#94a3b8;">{{ $o->customer_email ?? '' }}</div>
                    </td>
                    <td><span class="badge badge-info">{{ $o->salesChannel->name ?? '—' }}</span></td>
                    <td style="text-align:center;font-weight:600;">{{ $o->items->count() }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">{{ $o->currency ?? $activeCurrencySymbol }}{{ number_format($o->total_amount, 2) }}</td>
                    <td>
                        @php $sc = ['pending'=>'badge-warning','processing'=>'badge-info','confirmed'=>'badge-info','shipped'=>'badge-success','delivered'=>'badge-success','cancelled'=>'badge-danger','refunded'=>'badge-gray']; @endphp
                        <span class="badge {{ $sc[$o->status] ?? 'badge-gray' }}">{{ ucfirst($o->status ?? 'unknown') }}</span>
                    </td>
                    <td>
                        <span class="badge {{ $o->payment_status==='paid'?'badge-success':'badge-warning' }}">{{ ucfirst($o->payment_status ?? 'unpaid') }}</span>
                    </td>
                    <td>
                        <a href="{{ route('sales.orders.show', $o) }}" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i></a>
                        {{-- Cancel Button - Only for open or label_created --}}
                        @if(in_array($o->status, ['open', 'label_created', 'pending']))
                        <button onclick="cancelOrder('{{ $o->id }}', '{{ $o->order_number }}')"
                            class="btn btn-danger btn-sm" style="margin-left:4px;">
                            <i class="fas fa-times"></i>
                        </button>
                        @endif
                        {{-- Change Status Button --}}
                        @if(!in_array($o->status, ['delivered', 'cancelled', 'refunded']))
                        <button onclick="changeOrderStatus('{{ $o->id }}', '{{ $o->order_number }}', '{{ $o->status }}')"
                            class="btn btn-warning btn-sm" style="margin-left:4px;">
                            <i class="fas fa-edit"></i>
                        </button>
                        @endif

                        @if(auth()->user()->isAdmin() || \App\Services\PermissionService::can(auth()->user(), 'sales.orders.delete'))
                        <form method="POST" action="{{ route('sales.orders.delete', $o) }}" style="display:inline;"
                            onsubmit="return confirm('Delete order {{ $o->order_number }}? Inventory will be restored.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626;border-color:#fecaca;" title="Delete Order">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-shopping-cart" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No orders found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $orders->links('pagination::tailwind') }}</div>@endif
</div>
{{-- Cancel Order Modal --}}
<div id="cancelModal" class="modal" style="display:none;position:fixed;z-index:111;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.6);">
    <div style="background:white;margin:5% auto;padding:25px;border-radius:10px;width:90%;max-width:480px;">
        <h3 style="margin-bottom:15px;font-weight: bold;color:#dc2626;background-color: #ddd;  padding: .5rem;  border-radius: .5rem;">Cancel Order</h3>

        <p style="margin-bottom:15px; font-size:12px;" id="cancelOrderInfo"></p>

        <form id="cancelForm">
            @csrf
            <input type="hidden" id="cancel_order_id" name="order_id">

            <div class="form-group">
                <label>Reason for Cancellation <span style="color:red;">*</span></label>
                <textarea name="reason" id="cancel_reason" rows="4" class="form-control"
                    placeholder="Enter reason (e.g. Customer requested, Out of stock, etc.)" required></textarea>
            </div>

            <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closeCancelModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
            </div>
        </form>
    </div>
</div>
{{-- Change Order Status Modal --}}
<div id="statusModal" class="modal" style="display:none;position:fixed;z-index:122;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.6);">
    <div style="background:white;margin:5% auto;padding:25px;border-radius:10px;width:90%;max-width:480px;">
        <h3 style="margin-bottom:15px;font-weight: bold;  background-color: #ddd;  padding: .5rem;  border-radius: .5rem;">Change Order Status</h3>
        <p style="margin-bottom:15px;font-size:12px;" id="statusOrderInfo"></p>

        <form id="statusForm">
            @csrf
            <input type="hidden" id="status_order_id" name="order_id">
            <input type="hidden" id="current_status" name="current_status">

            <div class="form-group">
                <label>New Status <span style="color:red;">*</span></label>
                <select id="new_status" name="status" class="form-control" required>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="form-group">
                <label>Remarks / Reason</label>
                <textarea name="remarks" id="status_remarks" rows="4" class="form-control"
                    placeholder="Enter remarks or reason for status change..."></textarea>
            </div>

            <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closeStatusModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>
<script>
    function cancelOrder(orderId, orderNumber) {
        $('#cancel_order_id').val(orderId);
        $('#cancelOrderInfo').html(`Are you sure you want to cancel order <strong>${orderNumber}</strong>?`);
        $('#cancelModal').fadeIn();
    }

    function closeCancelModal() {
        $('#cancelModal').fadeOut();
        $('#cancelForm')[0].reset();
    }

    // Submit Cancel Form
    $('#cancelForm').on('submit', function(e) {
        e.preventDefault();

        const orderId = $('#cancel_order_id').val();
        const reason = $('#cancel_reason').val().trim();

        if (!reason) {
            alert("Please provide a reason for cancellation.");
            return;
        }

        $.ajax({
            url: "{{ route('sales.orders.cancel', ':id') }}".replace(':id', orderId),
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                order_id: orderId,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    alert('Order cancelled successfully.');
                    location.reload();
                } else {
                    alert(response.message || 'Failed to cancel order.');
                }
            },
            error: function() {
                alert(response.message || 'An error occurred while cancelling the order.');
            }
        });
    });


    function changeOrderStatus(orderId, orderNumber, currentStatus) {
        $('#status_order_id').val(orderId);
        $('#statusOrderInfo').html(`Change status for order <strong>#${orderNumber}</strong> (Current: ${currentStatus})`);
        $('#current_status').val(currentStatus);
        $('#statusModal').fadeIn();
    }

    function closeStatusModal() {
        $('#statusModal').fadeOut();
        $('#statusForm')[0].reset();
    }

    // Submit Status Change
    $('#statusForm').on('submit', function(e) {
        e.preventDefault();

        const orderId = $('#status_order_id').val();
        const newStatus = $('#new_status').val();
        const remarks = $('#status_remarks').val().trim();

        $.ajax({
            url: "{{ route('sales.orders.status.update', ':id') }}".replace(':id', orderId),
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                status: newStatus,
                remarks: remarks
            },
            success: function(response) {
                if (response.success) {
                    alert('Order status updated successfully.');
                    location.reload();
                } else {
                    alert(response.message || 'Failed to update status.');
                }
            },
            error: function(xhr) {
                let msg = xhr.responseJSON?.message || 'An error occurred.';
                alert(msg);
            }
        });
    });
</script>
@endsection