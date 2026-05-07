@extends('layouts.app')
@section('title', 'Warehouse Allocation')
@section('page-title', 'Warehouse Allocation & Transfers')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('logistics.inventory') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Inventory</a>
</div>

{{-- Warehouse Summary --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-warehouse" style="margin-right:.5rem;color:#1e3a5f;"></i> Warehouse Inventory Summary</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Warehouse</th>
                    <th>Code</th>
                    <th>Company</th>
                    <th>Country</th>
                    <th>SKUs</th>
                    <th>Total Qty</th>
                    <th>Available</th>
                    <th>Sub-Warehouses</th>
                    <th>Sub-Locations</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inventoryByWarehouse as $wh)
                <tr>
                    <td style="font-weight:600;">{{ $wh->name }}</td>
                    <td style="font-family:monospace;font-size:.82rem;">{{ $wh->code }}</td>
                    <td>{{ $wh->company_code }}</td>
                    <td>{{ $wh->country }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $wh->inventory_count }}</td>
                    <td style="text-align:center;font-weight:700;font-family:monospace;">{{ number_format($wh->inventory_sum_quantity ?? 0) }}</td>
                    <td style="text-align:center;font-weight:700;font-family:monospace;color:#166534;">{{ number_format($wh->inventory_sum_available_quantity ?? 0) }}</td>
                    <td style="text-align:center;">{{ $wh->subWarehouses->count() }}</td>
                    <td style="text-align:center;">{{ $wh->subLocations->count() }}</td>
                </tr>
                {{-- Sub-warehouses --}}
                @foreach($wh->subWarehouses as $sub)
                <tr style="background:#f8fafc;">
                    <td style="padding-left:2.5rem;font-size:.82rem;"><i class="fas fa-level-up-alt fa-rotate-90" style="color:#d1d5db;font-size:.65rem;margin-right:.3rem;"></i>{{ $sub->name }}</td>
                    <td style="font-family:monospace;font-size:.78rem;">{{ $sub->code }}</td>
                    <td colspan="7" style="font-size:.78rem;color:#64748b;">Sub-warehouse</td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Transfer Form --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-exchange-alt" style="margin-right:.5rem;color:#e8a838;"></i> Transfer Inventory</h3>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('transferForm').style.display=document.getElementById('transferForm').style.display==='none'?'block':'none'"><i class="fas fa-chevron-down"></i> Toggle</button>
    </div>
    <div id="transferForm" style="display:none;">
        <div class="card-body">
            <form method="POST" action="{{ route('logistics.inventory.transfer') }}">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto auto;gap:.75rem;align-items:flex-end;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Product (SKU) <span style="color:#dc2626;">*</span></label>
                        <select name="product_id" required style="font-family:inherit;">
                            <option value="">Select product...</option>
                            @foreach(\App\Models\Product::where('stock_quantity', '>', 0)->orderBy('sku')->get() as $p)
                            <option value="{{ $p->id }}">{{ $p->sku }} — {{ Str::limit($p->name, 30) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>From Warehouse <span style="color:#dc2626;">*</span></label>
                        <select name="from_warehouse_id" required>
                            <option value="">Select...</option>
                            @foreach($warehouses as $wh)<option value="{{ $wh->id }}">{{ $wh->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>To Warehouse <span style="color:#dc2626;">*</span></label>
                        <select name="to_warehouse_id" required>
                            <option value="">Select...</option>
                            @foreach($warehouses as $wh)<option value="{{ $wh->id }}">{{ $wh->name }}</option>@endforeach
                            @foreach($warehouses as $wh)
                            @foreach($wh->subWarehouses as $sub)
                            <option value="{{ $sub->id }}">↳ {{ $sub->name }} (sub of {{ $wh->name }})</option>
                            @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Quantity <span style="color:#dc2626;">*</span></label>
                        <input type="number" name="quantity" required min="1" placeholder="0" style="font-family:monospace;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Transportation Cost <span style="color:#dc2626;">*</span></label>
                        <input type="number" step="0.01" name="transportation_cost" required min="0" placeholder="0.00" style="font-family:monospace;">
                    </div>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Transfer this inventory?')"><i class="fas fa-exchange-alt" style="margin-right:.3rem;"></i> Transfer</button>
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('transferForm').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Movement History --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history" style="margin-right:.5rem;color:#2d6a4f;"></i> Recent Movements</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Product</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Qty</th>
                    <th>Reference</th>
                    <th>By</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $mv)
                <tr>
                    <td>
                        @php $tc = ['inward'=>['badge-success','fa-arrow-down'],'outward'=>['badge-danger','fa-arrow-up'],'transfer'=>['badge-info','fa-exchange-alt'],'adjustment'=>['badge-warning','fa-sliders-h']]; @endphp
                        <span class="badge {{ $tc[$mv->movement_type][0] ?? 'badge-gray' }}"><i class="fas {{ $tc[$mv->movement_type][1] ?? 'fa-circle' }}" style="margin-right:.2rem;font-size:.55rem;"></i>{{ ucfirst($mv->movement_type) }}</span>
                    </td>
                    <td style="font-size:.82rem;font-weight:500;">{{ $mv->product->name ?? '—' }}
                        <div style="font-size:.68rem;color:#94a3b8;font-family:monospace;">{{ $mv->product->sku ?? '' }}</div>
                    </td>
                    <td style="font-size:.8rem;">{{ $mv->fromWarehouse->name ?? '—' }}</td>
                    <td style="font-size:.8rem;">{{ $mv->toWarehouse->name ?? '—' }}</td>
                    <td style="font-weight:700;font-family:monospace;text-align:center;">{{ $mv->quantity }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $mv->reference_type ? ucfirst($mv->reference_type) . ' #' . $mv->reference_id : '—' }}</td>
                    <td style="font-size:.8rem;">{{ $mv->performer->name ?? '—' }}</td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $mv->created_at->format('d M Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8;">No movements recorded.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection