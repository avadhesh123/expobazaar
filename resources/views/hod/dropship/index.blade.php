@extends('layouts.app')
@section('title', 'Dropship')
@section('page-title', 'Dropship — Product & Inventory Management')

@section('content')
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;border-left:3px solid #1e40af;">
        <div class="kpi-label">Dropship Products</div>
        <div class="kpi-value" style="color:#1e40af;">{{ number_format($stats['total_products']) }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #7c3aed;">
        <div class="kpi-label">Vendors</div>
        <div class="kpi-value" style="color:#7c3aed;">{{ number_format($stats['total_vendors']) }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #16a34a;">
        <div class="kpi-label">Total Available Stock</div>
        <div class="kpi-value" style="color:#16a34a;">{{ number_format($stats['total_inventory']) }}</div>
    </div>
</div>

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-upload" style="margin-right:.5rem;color:#e8a838;"></i> Upload Dropship Products</h3>
        <a href="{{ route('hod.dropship.template.products') }}" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Template</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('hod.dropship.upload.products') }}" enctype="multipart/form-data">
            @csrf
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company *</label><select name="company_code" required style="padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                        <option value="2100" selected>2100</option>
                        <option value="2200">2200</option>
                        <option value="2400">2400</option>
                    </select></div>
                <div style="flex:1;min-width:200px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Product File *</label><input type="file" name="product_file" required accept=".csv,.xlsx" style="font-size:.82rem;"></div>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Upload dropship products?')"><i class="fas fa-upload"></i> Upload</button>
            </div>
        </form>
    </div>
</div>

@if(session('upload_result'))
@php $r = session('upload_result'); @endphp
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3>Product Upload Results</h3>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:1rem;margin-bottom:.75rem;">
            <div style="padding:.4rem .8rem;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0;"><span style="font-size:.65rem;color:#64748b;">Created</span>
                <div style="font-weight:800;color:#16a34a;">{{ $r['created'] ?? 0 }}</div>
            </div>
            <div style="padding:.4rem .8rem;background:#eff6ff;border-radius:6px;border:1px solid #bfdbfe;"><span style="font-size:.65rem;color:#64748b;">Updated</span>
                <div style="font-weight:800;color:#1e40af;">{{ $r['updated'] ?? 0 }}</div>
            </div>
            <div style="padding:.4rem .8rem;background:#fef2f2;border-radius:6px;border:1px solid #fca5a5;"><span style="font-size:.65rem;color:#64748b;">Errors</span>
                <div style="font-weight:800;color:#dc2626;">{{ count($r['errors'] ?? []) }}</div>
            </div>
        </div>
        @if(!empty($r['errors']))<div style="max-height:150px;overflow-y:auto;background:#fef2f2;padding:.5rem;border-radius:4px;font-size:.72rem;">@foreach($r['errors'] as $err)<div style="padding:.15rem 0;border-bottom:1px solid #fecaca;color:#dc2626;">{{ $err }}</div>@endforeach</div>@endif
    </div>
</div>
@endif

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-boxes" style="margin-right:.5rem;color:#16a34a;"></i> Update Inventory</h3>
        <a href="{{ route('hod.dropship.template.inventory') }}" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Template</a>
    </div>
    <div class="card-body">
        <!-- <div style="padding:.5rem .8rem;background:#fefce8;border-radius:6px;border:1px solid #fde68a;margin-bottom:.75rem;font-size:.72rem;color:#854d0e;">
            <i class="fas fa-exclamation-triangle"></i> <strong>Add:</strong> Adds qty to existing stock. <strong>Set:</strong> Replaces qty to exact number (reserved preserved).
        </div> -->
        <form method="POST" action="{{ route('hod.dropship.update.inventory') }}" enctype="multipart/form-data">
            @csrf
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;">
                <!-- <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company *</label><select name="company_code" required style="padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                        <option value="2000">2000</option>
                        <option value="2100" selected>2100</option>
                        <option value="2200">2200</option>
                    </select></div> -->
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Warehouse *</label><select name="warehouse_id" required style="padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@foreach($warehouses as $wh)<option value="{{ $wh->id }}">{{ $wh->name }}</option>@endforeach</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Mode *</label><select name="update_mode" required style="padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                        <option value="add">Add to Stock</option>
                        <option value="set">Set Exact</option>
                    </select></div>
                <div style="flex:1;min-width:180px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Inventory File *</label><input type="file" name="inventory_file" required accept=".csv,.xlsx" style="font-size:.82rem;"></div>
                <button type="submit" class="btn btn-success" onclick="return confirm('Update inventory?')"><i class="fas fa-sync"></i> Update</button>
            </div>
        </form>
    </div>
</div>

@if(session('inventory_result'))
@php $ir = session('inventory_result'); @endphp
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3>Inventory Update Results</h3>
    </div>
    <div class="card-body">
        <div style="display:flex;gap:1rem;margin-bottom:.75rem;">
            <div style="padding:.4rem .8rem;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0;"><span style="font-size:.65rem;color:#64748b;">Updated</span>
                <div style="font-weight:800;color:#16a34a;">{{ $ir['updated'] ?? 0 }}</div>
            </div>
            <div style="padding:.4rem .8rem;background:#fef2f2;border-radius:6px;border:1px solid #fca5a5;"><span style="font-size:.65rem;color:#64748b;">Errors</span>
                <div style="font-weight:800;color:#dc2626;">{{ count($ir['errors'] ?? []) }}</div>
            </div>
        </div>
        @if(!empty($ir['errors']))<div style="max-height:150px;overflow-y:auto;background:#fef2f2;padding:.5rem;border-radius:4px;font-size:.72rem;">@foreach($ir['errors'] as $err)<div style="padding:.15rem 0;border-bottom:1px solid #fecaca;color:#dc2626;">{{ $err }}</div>@endforeach</div>@endif
    </div>
</div>
@endif

<div class="card">
     <div class="card-header">
        <h3><i class="fas fa-list" style="margin-right:.5rem;color:#1e3a5f;"></i> Dropship Products</h3>
        <div style="display:flex;gap:.4rem;align-items:center;">
            <span style="font-size:.78rem;color:#64748b;">{{ $products->total() }} products</span>
            <a href="{{ route('hod.dropship.download.products') }}" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Products CSV</a>
            <a href="{{ route('hod.dropship.download.inventory') }}" class="btn btn-outline btn-sm" style="border-color:#16a34a;color:#16a34a;"><i class="fas fa-download"></i> Inventory CSV</a>
        </div>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th>Vendor</th>
                    <th>Dimensions</th>
                    <th>Company</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                <tr>
                    <td style="font-family:monospace;font-weight:700;">{{ $p->sku }}</td>
                    <td>{{ Str::limit($p->name, 35) }}</td>
                    <td style="font-size:.72rem;">{{ $p->vendor->company_name ?? '—' }}</td>
                    <!-- <td style="text-align:right;font-family:monospace;">{{ match($p->currency) { 'INR'=>'₹','EUR'=>'€',default=>'$' } }}{{ number_format(floatval($p->fob_price ?? 0), 2) }}</td> -->
                    <td style="font-size:.68rem;color:#64748b;">{{ $p->length }}×{{ $p->width }}×{{ $p->height }} · {{ $p->weight }}kg</td>
                    <td>{{ $p->company_code }}</td>
                    <td style="font-size:.72rem;">{{ $p->created_at?->format('d M Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8;">No dropship products. Upload a CSV to get started.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $products->links('pagination::tailwind') }}</div>@endif
</div>
@endsection