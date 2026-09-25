@extends('layouts.app')
@section('title', 'Order Management')
@section('page-title', 'Sales Orders — Order Management')

@section('content')
{{-- KPIs --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;border-left:3px solid #1e40af;">
        <div class="kpi-label">Total Label Created</div>
        <div class="kpi-value" style="color:#1e40af;">{{ $stats['total_shipped'] }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #e8a838;">
        <div class="kpi-label">In Transit</div>
        <div class="kpi-value" style="color:#e8a838;">{{ $stats['in_transit'] }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #16a34a;">
        <div class="kpi-label">Delivered</div>
        <div class="kpi-value" style="color:#16a34a;">{{ $stats['delivered'] }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #dc2626;">
        <div class="kpi-label">Overdue / Critical</div>
        <div class="kpi-value" style="color:#dc2626;">{{ $stats['critical'] }}</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem; border: 1px solid #ccc; border-radius: 6px; display: flex; gap: 1rem; align-items: center;">
        <form method="GET" action="{{ route('sales.order-management') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
            <!-- <div style="min-width:110px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company</label><select name="company_code" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All</option>
                    <option value="2000" {{ request('company_code')==='2000'?'selected':'' }}>🇮🇳 2000</option>
                    <option value="2100" {{ request('company_code')==='2100'?'selected':'' }}>🇺🇸 2100</option>
                    <option value="2200" {{ request('company_code')==='2200'?'selected':'' }}>🇳🇱 2200</option>
                </select></div> -->
            <div style="min-width:130px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Channel</label><select name="channel_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All</option>@foreach($channels as $ch)<option value="{{ $ch->id }}" {{ request('channel_id')==(string)$ch->id?'selected':'' }}>{{ $ch->name }}</option>@endforeach
                </select></div>
            <div style="min-width:120px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Status</label><select name="status" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All</option>
                    <option value="open" {{ request('status')==='open'?'selected':'' }}>In Open</option>
                    <option value="label_created" {{ request('status')==='label_created'?'selected':'' }}>Label Created</option>
                    <option value="cancelled" {{ request('status')==='cancelled'?'selected':'' }}>Cancelled</option>
                    <option value="shipped" {{ request('status')==='shipped'?'selected':'' }}>Shipped</option>
                    <option value="delivered" {{ request('status')==='delivered'?'selected':'' }}>Delivered</option>
                    <!-- <option value="out_for_delivery" {{ request('status')==='out_for_delivery'?'selected':'' }}>Out for Delivery</option> -->
                    <option value="returned" {{ request('status')==='returned'?'selected':'' }}>Returned</option>
                    <option value="exception" {{ request('status')==='exception'?'selected':'' }}>Exception</option>
                    <option value="lost_in_transit" {{ request('status')==='lost_in_transit'?'selected':'' }}>Lost in transit</option>

                </select></div>
            <div style="flex:1;min-width:170px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="PO, Tracking, Invoice..." style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('sales.order-management') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('sales.orders') }}" class="btn btn-outline btn-sm" style="margin-left:auto;"><i class="fas fa-arrow-left"></i> All Orders</a>
            <a href="{{ route('sales.to-be-shipped') }}" class="btn btn-outline btn-sm"><i class="fas fa-shipping-fast"></i> To Be Shipped</a>
        </form>
    </div>
</div>
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem; border: 1px solid #ccc; border-radius: 6px; display: flex; gap: 1rem; align-items: center;">
        <form action="{{ route('sales.orders.upload-store-pickup-csv') }}"
            method="POST"
            enctype="multipart/form-data"
            style="display:inline-flex;gap:.5rem;align-items:center; background:#f8fafc;padding:.5rem .75rem;border-radius:6px;">
            @csrf
            <input type="file" name="csv_file" accept=".csv" required
                style="font-size:.78rem;">
            <button type="submit" class="btn btn-primary btn-sm"
                onclick="return confirm('Upload and update order data?')">
                <i class="fas fa-upload"></i> Upload CSV
            </button>
        </form>
        <a href="{{ route('sales.download-store-pickup') }}" class="btn btn-success btn-sm"><i class="fas fa-download"></i> Download CSV</a>

    </div>
</div>

{{-- Orders Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-tasks" style="margin-right:.5rem;color:#1e3a5f;"></i> Order Management</h3><span style="font-size:.78rem;color:#64748b;">{{ $orders->total() }} orders</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.72rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="min-width:90px;">Order Date <br>Invoice #</th>
                    <th style="min-width:90px;">Order No # / Platform Order Id</th>
                    <th>Channel</th>
                    <th>SKU</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:center;">Ord Qty</th>
                    <th style="text-align:right;">Ord Amt</th>
                    <th>Warehouse</th>
                    <th>Ship Method</th>
                    <th style="text-align:center;">Ship Qty</th>
                    <th style="text-align:right;">Ship Amt</th>
                    <th>Tracking ID</th>
                    <th>Carrier</th>
                    <th style="background:#eef2ff;min-width:90px;">Ship Date</th>
                    <th style="background:#eef2ff;min-width:100px;">Current Status</th>
                    <th style="background:#eef2ff;min-width:90px;">Delivery Date</th>
                    <th style="background:#eef2ff;text-align:center;">Ageing</th>
                    <th style="background:#eef2ff;min-width:70px;">Material Cost</th>
                    <th style="background:#eef2ff;min-width:70px;">Processing Cost</th>
                    <th style="background:#eef2ff;min-width:70px;">Shipping Cost</th>
                    <th style="background:#eef2ff;min-width:80px;">Remarks</th>
                    <th>Save</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                @php
                $firstItem = $o->items->first();
                $sku = $firstItem->sku ?? $firstItem->product->sku ?? '—';
                $unitPrice = $firstItem ? floatval($firstItem->unit_price) : 0;
                $orderQty = $firstItem ? intval($firstItem->quantity) : 0;
                $shipMethods = ['1'=>'Store Pickup','2'=>'Mktplace','3'=>'Seller'];
                $statusColors = [
                'in_transit'=>'#e8a838','out_for_delivery'=>'#1e40af',
                'delivered'=>'#16a34a','returned'=>'#dc2626','exception'=>'#eea48b',
                'shipped'=>'#e8a838',
                ];

                $statusBg = [
                'in_transit' => '#e8a838',
                'out_for_delivery' => '#1e40af',
                'delivered' => '#adf4c3',
                'returned' => '#dc2626',
                'exception' => '#eea48b',
                'shipped' => '#e8a838',
                'cancelled' => '#f1f5f9',
                ];
                $status = $o->current_status ?? $o->status;

                @endphp
                <form method="POST" action="{{ route('sales.order-management.update', $o) }}">
                    @csrf
                    <tr style="{{ isset($statusBg[$status]) ? 'background:' . $statusBg[$status] . ';' : '' }}"> {{-- Cols 1-10: Read-only from Sales Data --}}
                        <td style="font-size:.72rem;">
                            <span>{{ $o->order_date?->format('d M y') ?? '—' }}</span>
                            <span style="display:block;font-size:.65rem;color:#64748b;">{{ $o->invoice_number ?? '—' }}</span>
                        </td>
                        <td style="font-family:monospace;font-weight:600;">
                            <span>{{ $o->order_number  ?? '—' }}</span>
                            <span style="display:block;font-size:.65rem;color:#64748b;">{{ $o->platform_order_id ?? '—' }}</span>
                        </td>
                        <td style="font-size:.68rem;">{{ $o->salesChannel->name ?? '—' }}</td>
                        <td style="font-family:monospace;font-size:.72rem;">{{ $sku }}</td>
                        <td style="text-align:right;font-family:monospace;">{{$activeCurrencySymbol}}{{ number_format($unitPrice, 2) }}</td>
                        <td style="text-align:center;font-weight:600;">{{ $orderQty }}</td>
                        <td style="text-align:right;font-family:monospace;">{{$activeCurrencySymbol}}{{ number_format(floatval($o->total_amount), 2) }}</td>
                        <td style="font-size:.72rem;"> {{ $o->warehouse->name ?? '—' }}</td>
                        <td style="font-size:.68rem;">{{ $shipMethods[$o->shipping_method] ?? $o->shipping_method ?? '—' }}</td>
                        {{-- Cols 11-14: From To Be Shipped (read-only) --}}
                        <td style="text-align:center;font-weight:600;">{{ $o->shipped_qty ?? '—' }}</td>
                        <td style="text-align:right;font-family:monospace;">{{$activeCurrencySymbol}}{{ number_format(floatval($o->shipped_amount), 2) }}</td>
                        <td style="font-family:monospace;font-size:.68rem;">{{ $o->tracking_id ?? '—' }}</td>
                        <td style="font-size:.72rem;">{{ $o->carrier ?? '—' }}</td>

                        {{-- Cols 15-21: Editable --}}
                        <td style="background:#eef2ff;">
                            <input type="date" name="ship_date" value="{{ $o->ship_date?->format('Y-m-d') ?? '' }}"
                                style="width:100%;padding:.2rem .25rem;border:1px solid #c7d2fe;border-radius:4px;font-size:.72rem;">
                        </td>
                        <td style="background:#eef2ff;">
                            <select name="current_status" style="width:100%;padding:.2rem .25rem;border:1px solid #c7d2fe;border-radius:4px;font-size:.72rem;">
                                <option value="">—</option>
                                @foreach(['in_transit'=>'In Transit','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','returned'=>'Returned','exception'=>'Exception'] as $val => $label)
                                <option value="{{ $val }}" {{ ($o->current_status ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td style="background:#eef2ff;">
                            <input type="date" name="delivery_date" value="{{ $o->delivery_date?->format('Y-m-d') ?? '' }}"
                                style="width:100%;padding:.2rem .25rem;border:1px solid #c7d2fe;border-radius:4px;font-size:.72rem;">
                        </td>
                        <td style="background:#eef2ff;text-align:center;">
                            @if($o->current_status === 'delivered')
                            <span style="font-weight:700;color:#16a34a;font-size:.78rem;"><i class="fas fa-check-circle"></i></span>
                            @elseif($o->ageing_label)
                            <span style="font-weight:700;color:{{ $o->ageing_color }};font-size:.78rem;">{{ round($o->ageing_days,2) }}d</span>
                            <div style="font-size:.5rem;font-weight:700;color:{{ $o->ageing_color }};">{{ $o->ageing_label }}</div>
                            @else
                            —
                            @endif
                        </td>
                        <td style="background:#eef2ff;">
                            <input type="number" step="0.01" name="material_cost" value="{{ $o->material_cost }}" placeholder="0.00"
                                style="width:60px;padding:.2rem .25rem;border:1px solid #c7d2fe;border-radius:4px;font-size:.72rem;font-family:monospace;text-align:right;">
                        </td>
                        <td style="background:#eef2ff;">
                            <input type="number" step="0.01" name="order_processing_charges" value="{{ $o->order_processing_charges }}" placeholder="0.00"
                                style="width:60px;padding:.2rem .25rem;border:1px solid #c7d2fe;border-radius:4px;font-size:.72rem;font-family:monospace;text-align:right;">
                        </td>
                        <td style="background:#eef2ff;">
                            <input type="number" step="0.01" name="shipping_cost" value="{{ $o->shipping_cost }}" placeholder="0.00"
                                style="width:60px;padding:.2rem .25rem;border:1px solid #c7d2fe;border-radius:4px;font-size:.72rem;font-family:monospace;text-align:right;">
                        </td>
                        <td style="background:#eef2ff;">
                            <input type="text" name="remarks" value="{{ $o->remarks }}" placeholder="..."
                                style="width:75px;padding:.2rem .25rem;border:1px solid #c7d2fe;border-radius:4px;font-size:.72rem;">
                        </td>
                        <td>
                            <button type="submit" class="btn btn-primary btn-sm" title="Save"><i class="fas fa-save"></i></button>
                        </td>
                    </tr>
                </form>
                @empty
                <tr>
                    <td colspan="23" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-box-open" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No shipped orders found. Ship orders from the <a href="{{ route('sales.to-be-shipped') }}">To Be Shipped</a> tab first.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $orders->links('pagination::tailwind') }}</div>@endif
</div>

<div style="margin-top:1rem;padding:.6rem 1rem;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;font-size:.72rem;color:#64748b;">
    <strong>Ageing Rules:</strong>
    <span style="color:#16a34a;font-weight:600;">ON TIME</span> = &lt; 5 days &nbsp;|&nbsp;
    <span style="color:#e8a838;font-weight:600;">DUE</span> = 5-6 days &nbsp;|&nbsp;
    <span style="color:#dc2626;font-weight:600;">OVERDUE</span> = 7-9 days &nbsp;|&nbsp;
    <span style="color:#7c2d12;font-weight:600;">CRITICAL</span> = 10+ days (from Ship Date, until Delivered)
</div>
@endsection