@extends('layouts.app')
@section('title', 'Order: ' . $order->order_number)
@section('page-title', 'Order Details')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('sales.orders') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Orders</a>
</div>
@php
$currency = match ($order->company_code) {
'2000' => 'INR',
'2200' => 'EUR',
default => 'USD',
};

@endphp
{{-- Order Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1.25rem 1.4rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <div>
                <div style="font-size:1.2rem;font-weight:800;color:#0d1b2a;font-family:monospace;">{{ $order->order_number }}</div>
                @if($order->platform_order_id)<div style="font-size:.82rem;color:#64748b;">Platform: <strong>{{ $order->platform_order_id }}</strong></div>@endif
            </div>
            <div style="display:flex;gap:.5rem;">
                <span class="badge badge-info" style="font-size:.82rem;padding:.3rem .7rem;">{{ $order->salesChannel->name ?? '—' }}</span>
                @php $ssc = ['pending'=>'badge-danger','cancelled'=>'badge-danger','shipped'=>'badge-warning','delivered'=>'badge-success','returned'=>'badge-gray']; @endphp
                <span class="badge {{ $ssc[$order->status ?? 'pending'] ?? 'badge-gray' }}" style="font-size:.82rem;padding:.3rem .7rem;">{{ ucfirst($order->status ?? 'pending') }}</span>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:.75rem;">
            @php $ccBg = ['2000'=>'#dcfce7','2100'=>'#dbeafe','2200'=>'#fef3c7']; @endphp
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Company</div>
                <div style="font-weight:700;"><span style="padding:.1rem .3rem;background:{{ $ccBg[$order->company_code] ?? '#f1f5f9' }};border-radius:4px;">{{ $order->company_code }}</span></div>
            </div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Order Date</div>
                <div style="font-weight:600;">{{ $order->order_date->format('d M Y') }}</div>
            </div>
            <div style="padding:.6rem;background:#dcfce7;border-radius:8px;">
                <div style="font-size:.62rem;color:#166534;text-transform:uppercase;font-weight:600;">Total Amount</div>
                <div style="font-weight:800;font-size:1.1rem;color:#166534;font-family:monospace;">{{ $order->currency }}{{ number_format($order->total_amount, 2) }}</div>
            </div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Items</div>
                <div style="font-weight:700;">{{ $order->items->count() }}</div>
            </div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Payment</div>
                <div><span class="badge {{ ($order->payment_status ?? 'unpaid')==='paid'?'badge-success':'badge-warning' }}">{{ ucfirst($order->payment_status ?? 'unpaid') }}</span></div>
            </div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;">
                <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Uploaded By</div>
                <div style="font-size:.82rem;">{{ $order->uploader->name ?? '—' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="grid-2">
    {{-- Customer & Shipping --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user" style="margin-right:.5rem;color:#1e3a5f;"></i> Customer & Shipping</h3>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
                <div><span style="font-size:.68rem;color:#64748b;font-weight:600;">Name</span><br><span style="font-weight:600;">{{ $order->customer_name ?? '—' }}</span></div>
                <div><span style="font-size:.68rem;color:#64748b;font-weight:600;">Email</span><br><span style="font-size:.82rem;">{{ $order->customer_email ?? '—' }}</span></div>
            </div>
            <div style="margin-top:.75rem;padding:.5rem 0;background:#f8fafc;border-radius:6px;">
                <div style="font-size:.68rem;color:#64748b;font-weight:600;margin-bottom:.2rem;">Shipping Address</div>
                <div style="font-size:.82rem;">{{ $order->shipping_address ?? '' }}{{ $order->shipping_city ? ', '.$order->shipping_city : '' }}{{ $order->shipping_state ? ', '.$order->shipping_state : '' }}{{ $order->shipping_country ? ' '.$order->shipping_country : '' }} {{ $order->shipping_pincode ?? '' }}</div>
            </div>
        </div>
    </div>

    {{-- Tracking --}}
    {{-- Tracking Section - Multiple Trackings --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-truck" style="margin-right:.5rem;color:#e8a838;"></i> Shipment Tracking 
            <span style="font-size:.75rem;font-weight:500;">({{ $order->trackings->count() ?? 0 }})</span>
        </h3>
    </div>
    <div class="card-body">

        @if($order->trackings->count() > 0)
            <div style="margin-bottom:1.25rem;">
                @foreach($order->trackings as $tracking)
                <div style="padding:.75rem;background:#f8fafc;border-radius:8px;margin-bottom:.75rem;border-left:4px solid #3b82f6;">
                    <div style="display:flex;justify-content:space-between;align-items:start;">
                        <div>
                            <strong style="font-family:monospace;">{{ $tracking->tracking_id }}</strong><br>
                            <span style="color:#64748b;font-size:.82rem;">{{ $tracking->shipping_provider }}</span>
                        </div>
                        @if($tracking->tracking_url)
                            <a href="{{ $tracking->tracking_url }}" target="_blank" class="btn btn-outline btn-sm">
                                <i class="fas fa-external-link-alt"></i> Track
                            </a>
                        @endif
                    </div>
                    @if($tracking->shipped_date)
                        <small style="color:#166534;">Shipped: {{ $tracking->shipped_date->format('d M Y') }}</small>
                    @endif
                </div>
                @endforeach
            </div>
        @else
            <div style="text-align:center;padding:2rem;color:#94a3b8;">
                <i class="fas fa-truck" style="font-size:2.5rem;opacity:0.3;margin-bottom:.5rem;"></i>
                <div>No tracking information added yet.</div>
            </div>
        @endif

        {{-- Add New Tracking Form --}}
        <form method="POST" action="{{ route('sales.orders.tracking.store', $order) }}">
            @csrf
            <div style="background:#f8fafc;padding:1rem;border-radius:8px;">
                <h4 style="margin-bottom:.75rem;font-size:.9rem;color:#1e3a5f;">Add New Tracking</h4>
                
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.75rem;">
                    <div class="form-group">
                        <label>Shipping Provider</label>
                        <select name="shipping_provider" class="form-control" required>
                            <option value="">Select Provider</option>
                            <option value="UPS">UPS</option>
                            <option value="FedEx">FedEx</option>
                            <option value="USPS">USPS</option>
                            <option value="DHL">DHL</option>
                            <option value="Amazon Logistics">Amazon Logistics</option>
                            <option value="BlueDart">BlueDart</option>
                            <option value="PostNL">PostNL</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tracking Number *</label>
                        <input type="text" name="tracking_id" class="form-control" placeholder="e.g. 1Z999AA10123456784" required>
                    </div>

                    <div class="form-group">
                        <label>Tracking URL (Optional)</label>
                        <input type="url" name="tracking_url" class="form-control" placeholder="https://...">
                    </div>

                    <div class="form-group">
                        <label>Shipped Date</label>
                        <input type="date" name="shipped_date" class="form-control" value="{{ now()->format('Y-m-d') }}">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:1rem;">
                    <i class="fas fa-plus"></i> Add Tracking
                </button>
            </div>
        </form>

    </div>
</div>
</div>

{{-- Order Items --}}
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-box" style="margin-right:.5rem;color:#2d6a4f;"></i> Order Items ({{ $order->items->count() }})</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Vendor</th>
                    <th>OrderedQty</th>
                    <th>Shipped Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td style="font-family:monospace;font-weight:600;font-size:.82rem;">{{ $item->sku ?? $item->product->sku ?? '—' }}</td>
                    <td style="font-size:.82rem;font-weight:500;">{{ $item->product->name ?? '—' }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $item->product->vendor->company_name ?? '—' }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $item->quantity }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $item->shipped_qty ?? '—' }}</td>
                    <td style="font-family:monospace;">{{ $order->currency }}{{ number_format($item->unit_price, 2) }}</td>
                    <td style="font-family:monospace;font-weight:700;">{{ $order->currency }}{{ number_format($item->total_price, 2) }}</td>
                </tr>
                @endforeach
                <tr style="background:#f8fafc;font-weight:700;">
                    <td colspan="5" style="text-align:right;">Subtotal</td>
                    <!-- <td style="text-align:center;">{{ $order->items->sum('quantity') }}</td> -->
                    <td style="text-align:center;">{{ $order->items->sum('shipped_qty') }}</td>
                    <td style="font-family:monospace;">{{$currency }}{{ number_format($order->subtotal, 2) }}</td>
                </tr>
                @if($order->shipping_amount > 0)<tr style="background:#f8fafc;">
                    <td colspan="6" style="text-align:right;font-size:.82rem;">Shipping</td>
                    <td style="font-family:monospace;">{{$currency }}{{ number_format($order->shipping_amount, 2) }}</td>
                </tr>@endif
                @if($order->tax_amount > 0)<tr style="background:#f8fafc;">
                    <td colspan="6" style="text-align:right;font-size:.82rem;">Tax</td>
                    <td style="font-family:monospace;">{{$currency }}{{ number_format($order->tax_amount, 2) }}</td>
                </tr>@endif
                @if($order->discount_amount > 0)<tr style="background:#f8fafc;">
                    <td colspan="6" style="text-align:right;font-size:.82rem;color:#dc2626;">Discount</td>
                    <td style="font-family:monospace;color:#dc2626;">-{{$currency }}{{ number_format($order->discount_amount, 2) }}</td>
                </tr>@endif
                <tr style="background:#dcfce7;">
                    <td colspan="6" style="text-align:right;font-weight:800;font-size:.9rem;color:#166534;">TOTAL</td>
                    <td style="font-family:monospace;font-weight:800;font-size:1rem;color:#166534;">{{ $currency  }}{{ number_format($order->total_amount, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- Chargebacks --}}
@if($order->chargebacks->count() > 0)
<div class="card" style="margin-top:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#dc2626;"></i> Chargebacks ({{ $order->chargebacks->count() }})</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Amount</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->chargebacks as $cb)
                <tr>
                    <td style="font-family:monospace;font-weight:700;color:#dc2626;">${{ number_format($cb->amount, 2) }}</td>
                    <td style="font-size:.82rem;">{{ $cb->reason }}</td>
                    <td><span class="badge {{ $cb->status==='confirmed'?'badge-danger':($cb->status==='rejected'?'badge-gray':'badge-warning') }}">{{ ucfirst($cb->status) }}</span></td>
                    <td style="font-size:.82rem;">{{ $cb->created_at->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection