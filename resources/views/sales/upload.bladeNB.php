@extends('layouts.app')
@section('title', 'Upload Sales Data')
@section('page-title', 'Upload Sales Data')

@section('content')
<div style="padding:.6rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
    Upload sales data from Excel/CSV. Required columns: <strong>Order Date, PO Number, Style Code (SKU), Per Unit Sales Price, Order Qty</strong>.
    System will auto-fill: Vendor Name, Vendor Type, SAP Code, Order Amount, Invoice Number, Warehouse.
    If SAP code is not found for a SKU, that row will be skipped with an error.
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
                <div style="min-width:120px;">
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Company Code *</label>
                    <select name="company_code" required style="padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                        <option value="2000">🇮🇳 2000</option>
                        <option value="2100" selected>🇺🇸 2100</option>
                        <option value="2200">🇳🇱 2200</option>
                    </select>
                </div>
                <div style="flex:1;min-width:250px;">
                    <label style="font-size:.7rem;font-weight:600;color:#64748b;display:block;margin-bottom:.25rem;">Sales File (CSV / XLSX) *</label>
                    <input type="file" name="sales_file" required accept=".csv,.xlsx,.xls" style="font-size:.82rem;">
                </div>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Upload and process sales data?')"><i class="fas fa-upload" style="margin-right:.3rem;"></i> Upload & Process</button>
            </div>
        </form>
    </div>
</div>

{{-- Upload Results --}}
@if(session('upload_result'))
@php $result = session('upload_result'); @endphp
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-chart-bar" style="margin-right:.5rem;"></i> Upload Results</h3></div>
    <div class="card-body">
        <div style="display:flex;gap:1.5rem;margin-bottom:1rem;">
            <div style="padding:.5rem 1rem;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;">
                <div style="font-size:.65rem;color:#64748b;">Orders Created</div>
                <div style="font-size:1.2rem;font-weight:800;color:#16a34a;">{{ $result['created'] ?? 0 }}</div>
            </div>
            <div style="padding:.5rem 1rem;background:#fef2f2;border-radius:8px;border:1px solid #fca5a5;">
                <div style="font-size:.65rem;color:#64748b;">Skipped / Errors</div>
                <div style="font-size:1.2rem;font-weight:800;color:#dc2626;">{{ count($result['errors'] ?? []) }}</div>
            </div>
            <div style="padding:.5rem 1rem;background:#fefce8;border-radius:8px;border:1px solid #fde68a;">
                <div style="font-size:.65rem;color:#64748b;">Total Rows</div>
                <div style="font-size:1.2rem;font-weight:800;color:#854d0e;">{{ $result['total_rows'] ?? 0 }}</div>
            </div>
        </div>
        @if(!empty($result['errors']))
        <div style="max-height:200px;overflow-y:auto;background:#fef2f2;padding:.75rem;border-radius:6px;font-size:.75rem;">
            @foreach($result['errors'] as $err)
            <div style="padding:.2rem 0;border-bottom:1px solid #fecaca;color:#dc2626;">{{ $err }}</div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endif

{{-- Column Reference --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-info-circle" style="margin-right:.5rem;color:#64748b;"></i> Column Reference</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead><tr><th>#</th><th>Column</th><th>Required</th><th>Source</th><th>Notes</th></tr></thead>
            <tbody>
                <tr><td>1</td><td style="font-weight:600;">Order Date</td><td><span class="badge badge-danger">Required</span></td><td>File</td><td>Format: YYYY-MM-DD</td></tr>
                <tr><td>2</td><td style="font-weight:600;">PO Number / Order ID</td><td><span class="badge badge-danger">Required</span></td><td>File</td><td>Unique per order</td></tr>
                <tr><td>3</td><td style="font-weight:600;">Invoice Number</td><td><span class="badge badge-gray">Auto</span></td><td>System</td><td>Generated: CompanyCode + FY + Sequence</td></tr>
                <tr><td>4</td><td style="font-weight:600;">Sales Channel</td><td><span class="badge badge-warning">Optional</span></td><td>File</td><td>Matched to sales_channels by name</td></tr>
                <tr><td>5</td><td style="font-weight:600;">Vendor Name</td><td><span class="badge badge-gray">Auto</span></td><td>System</td><td>Looked up from Style Code (SKU)</td></tr>
                <tr><td>6</td><td style="font-weight:600;">Vendor Type</td><td><span class="badge badge-gray">Auto</span></td><td>System</td><td>From vendor record</td></tr>
                <tr><td>7</td><td style="font-weight:600;">SAP Code</td><td><span class="badge badge-gray">Auto</span></td><td>System</td><td>Looked up from SKU. <strong>If not found → row skipped</strong></td></tr>
                <tr><td>8</td><td style="font-weight:600;">Style Code (SKU)</td><td><span class="badge badge-danger">Required</span></td><td>File</td><td>Must match a product SKU in the system</td></tr>
                <tr><td>9</td><td style="font-weight:600;">Per Unit Sales Price</td><td><span class="badge badge-danger">Required</span></td><td>File</td><td>Numeric value</td></tr>
                <tr><td>10</td><td style="font-weight:600;">Order Qty</td><td><span class="badge badge-danger">Required</span></td><td>File</td><td>Integer, min 1</td></tr>
                <tr><td>11</td><td style="font-weight:600;">Order Amount</td><td><span class="badge badge-gray">Auto</span></td><td>System</td><td>= Per Unit Sales Price × Order Qty</td></tr>
                <tr><td>12</td><td style="font-weight:600;">Warehouse Name</td><td><span class="badge badge-gray">Auto</span></td><td>System</td><td>From vendor's assigned warehouse</td></tr>
                <tr><td>13</td><td style="font-weight:600;">Shipping Method</td><td><span class="badge badge-warning">Optional</span></td><td>File</td><td>1=Store Pickup, 2=Marketplace Label, 3=Seller Label</td></tr>
                <tr><td>14</td><td style="font-weight:600;">Customer Type</td><td><span class="badge badge-warning">Optional</span></td><td>File</td><td>e.g. CFL, B2B, D2C</td></tr>
                <tr><td>15</td><td style="font-weight:600;">Customer Name</td><td><span class="badge badge-warning">Optional</span></td><td>File</td><td></td></tr>
                <tr><td>16</td><td style="font-weight:600;">Company Name</td><td><span class="badge badge-warning">Optional</span></td><td>File</td><td></td></tr>
                <tr style="background:#f8fafc;"><td>17-23</td><td style="font-weight:600;">Address, City, State, Zip, Country, Phone, Email</td><td><span class="badge badge-warning">Optional</span></td><td>File</td><td>Shipping/customer details</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
