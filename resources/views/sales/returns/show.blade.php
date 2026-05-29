@extends('layouts.app')
@section('title', 'Return ' . $orderReturn->return_number)
@section('page-title', 'Return — ' . $orderReturn->return_number)

@section('content')
@php
    $statusColors = ['initiated'=>'#e8a838','received'=>'#7c3aed','inspected'=>'#1e40af','approved'=>'#16a34a','rejected'=>'#dc2626','refunded'=>'#16a34a','restocked'=>'#0891b2'];
    $statusFlow = ['initiated','received','inspected','approved','refunded','restocked'];
    $currentIdx = array_search($orderReturn->status, $statusFlow);
@endphp

<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('sales.returns') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Returns</a>
    <a href="{{ route('sales.orders.show', $orderReturn->order_id) }}" class="btn btn-outline btn-sm"><i class="fas fa-file-invoice"></i> View Order</a>
</div>

{{-- Status Progress --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.75rem 1.4rem;">
        <div style="display:flex;align-items:center;gap:0;">
            @foreach($statusFlow as $idx => $step)
            <div style="flex:1;text-align:center;">
                <div style="width:28px;height:28px;border-radius:50%;margin:0 auto .3rem;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;color:#fff;background:{{ $idx <= ($currentIdx ?? -1) ? ($statusColors[$orderReturn->status] ?? '#64748b') : '#d1d5db' }};">
                    @if($idx < ($currentIdx ?? -1)) ✓ @else {{ $idx + 1 }} @endif
                </div>
                <div style="font-size:.6rem;font-weight:{{ $orderReturn->status === $step ? '800' : '600' }};color:{{ $orderReturn->status === $step ? '#0d1b2a' : '#94a3b8' }};">{{ ucfirst($step) }}</div>
            </div>
            @if($idx < count($statusFlow) - 1)<div style="flex:.5;height:2px;background:{{ $idx < ($currentIdx ?? -1) ? ($statusColors[$orderReturn->status] ?? '#64748b') : '#e2e8f0' }};"></div>@endif
            @endforeach
        </div>
    </div>
</div>

{{-- Return Info --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;font-size:.82rem;">
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Return #</div><div style="font-weight:700;font-family:monospace;">{{ $orderReturn->return_number }}</div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Order #</div><div style="font-family:monospace;">{{ $orderReturn->order->order_number ?? '—' }}</div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Channel</div><div>{{ $orderReturn->order->salesChannel->name ?? '—' }}</div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Return Date</div><div>{{ $orderReturn->return_date->format('d M Y') }}</div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Reason</div><div><span class="badge badge-warning">{{ ucwords(str_replace('_',' ',$orderReturn->reason)) }}</span></div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Return Amount</div><div style="font-weight:700;color:#dc2626;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($orderReturn->total_return_amount, 2) }}</div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Warehouse</div><div>{{ $orderReturn->warehouse->name ?? '—' }}</div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Tracking</div><div style="font-family:monospace;">{{ $orderReturn->tracking_id ?? '—' }} {{ $orderReturn->carrier ? "({$orderReturn->carrier})" : '' }}</div></div>
        </div>
        @if($orderReturn->reason_detail)
        <div style="margin-top:.75rem;padding:.5rem .75rem;background:#f8fafc;border-radius:6px;font-size:.78rem;color:#334155;">{{ $orderReturn->reason_detail }}</div>
        @endif
    </div>
</div>

{{-- Return Items --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-boxes" style="margin-right:.5rem;color:#e8a838;"></i> Return Items</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead><tr style="background:#f0f4f8;">
                <th>SKU</th><th>Product</th><th style="text-align:center;">Return Qty</th><th style="text-align:right;">Unit Price</th>
                <th style="text-align:right;">Return Amount</th><th>Condition</th><th>Restocked</th>
            </tr></thead>
            <tbody> 
                
                @foreach($orderReturn->items as $item)
               
                <tr>
                    <td style="font-family:monospace;font-weight:600;">{{ $item->sku }}</td>
                    <td>{{ $item->product->name ?? '—' }}{{ $item->product_id ?? '—' }}</td>
                    <td style="text-align:center;font-weight:700;">{{ $item->return_qty }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;color:#dc2626;">{{ $activeCurrencySymbol }}{{ number_format($item->return_amount, 2) }}</td>
                    <td>
                        @php $condColors = ['good'=>'badge-success','damaged'=>'badge-warning','defective'=>'badge-gray','unsellable'=>'badge-gray']; @endphp
                        <span class="badge {{ $condColors[$item->condition_status] ?? 'badge-gray' }}">{{ ucfirst($item->condition_status) }}</span>
                    </td>
                    <td style="text-align:center;">
                        @if($item->restock) <span style="color:#16a34a;font-weight:700;"><i class="fas fa-check-circle"></i> {{ $item->restocked_qty }}</span>
                        @else <span style="color:#94a3b8;">—</span> @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Actions --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-cogs" style="margin-right:.5rem;color:#64748b;"></i> Actions</h3></div>
    <div class="card-body">
        <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
            {{-- Update Status --}}
            @if(!in_array($orderReturn->status, ['rejected','restocked']))
            <form method="POST" action="{{ route('sales.returns.update-status', $orderReturn) }}" style="display:flex;gap:.5rem;align-items:flex-end;">
                @csrf
                <div>
                    <label style="font-size:.68rem;font-weight:600;color:#64748b;">Update Status</label>
                    <select name="status" required style="padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                        @if($orderReturn->status === 'initiated')<option value="received">Mark Received</option>@endif
                        @if($orderReturn->status === 'received')<option value="inspected">Mark Inspected</option>@endif
                        @if(in_array($orderReturn->status, ['inspected','received']))<option value="approved">Approve</option><option value="rejected">Reject</option>@endif
                        @if($orderReturn->status === 'approved')<option value="refunded">Mark Refunded</option>@endif
                    </select>
                </div>
                <div style="flex:1;min-width:150px;">
                    <label style="font-size:.68rem;font-weight:600;color:#64748b;">Notes</label>
                    <input type="text" name="inspection_notes" placeholder="Optional notes..." style="padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;width:100%;">
                </div>
                @if($orderReturn->status === 'approved')
                <div>
                    <label style="font-size:.68rem;font-weight:600;color:#64748b;">Refund Amount</label>
                    <input type="number" step="0.01" name="refund_amount" value="{{ $orderReturn->total_return_amount }}" style="width:100px;padding:.35rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;">
                </div>
                @endif
                <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Update return status?')"><i class="fas fa-save"></i> Update</button>
            </form>
            @endif

            {{-- Restock --}}
            @if(in_array($orderReturn->status, ['approved','inspected']) && $orderReturn->items->where('restock', false)->where('condition_status', 'good')->isNotEmpty())
            <form method="POST" action="{{ route('sales.returns.restock', $orderReturn) }}" style="margin-left:auto;">
                @csrf
                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Restock all items in good condition back to inventory?')">
                    <i class="fas fa-warehouse" style="margin-right:.3rem;"></i> Restock Good Items
                </button>
            </form>
            @endif
        </div>

        @if($orderReturn->inspection_notes)
        <div style="margin-top:.75rem;padding:.5rem .75rem;background:#eff6ff;border-radius:6px;font-size:.78rem;color:#1e40af;">
            <strong>Inspection Notes:</strong> {{ $orderReturn->inspection_notes }}
        </div>
        @endif
    </div>
</div>
@endsection
