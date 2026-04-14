@extends('layouts.app')
@section('title', 'New Offer Sheet')
@section('page-title', 'Submit Offer Sheet')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;"><a href="{{ route('vendor.offer-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a></div>

<div style="padding:.75rem 1.2rem;background:#eff6ff;border-radius:10px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.82rem;color:#1e40af;"><i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Upload your product details with thumbnail images. The Sourcing Team will review and select items using checkboxes.</div>

<form method="POST" action="{{ route('vendor.offer-sheets.store') }}" enctype="multipart/form-data" id="offerForm">
    @csrf
    <div class="card" style="margin-bottom:1rem;">
        <div class="card-header"><h3><i class="fas fa-box" style="margin-right:.5rem;color:#e8a838;"></i> Products</h3><button type="button" class="btn btn-outline btn-sm" onclick="addProductRow()"><i class="fas fa-plus"></i> Add Product</button></div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table" id="productsTable">
                <thead><tr><th>#</th><th>Product Name *</th><th>Category</th><th>Price ($) *</th><th>Currency</th><th>Description</th><th>Thumbnail</th><th></th></tr></thead>
                <tbody id="productsBody">
                    <tr id="prow0">
                        <td style="text-align:center;color:#94a3b8;">1</td>
                        <td><input type="text" name="products[0][name]" required placeholder="Product name" style="width:180px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;"></td>
                        <td><select name="products[0][category_id]" style="width:120px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:inherit;"><option value="">Select...</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></td>
                        <td><input type="number" step="0.01" min="0" name="products[0][price]" required placeholder="0.00" style="width:90px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;"></td>
                        <td><select name="products[0][currency]" style="width:65px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;"><option value="USD">USD</option><option value="INR">INR</option><option value="EUR">EUR</option></select></td>
                        <td><input type="text" name="products[0][description]" placeholder="Brief desc..." style="width:140px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;"></td>
                        <td><input type="file" name="products[0][thumbnail]" accept="image/*" style="width:120px;font-size:.72rem;"></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div style="display:flex;gap:.5rem;justify-content:flex-end;">
        <a href="{{ route('vendor.offer-sheets') }}" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-primary" onclick="return confirm('Submit offer sheet for Sourcing review?')"><i class="fas fa-paper-plane" style="margin-right:.3rem;"></i> Submit Offer Sheet</button>
    </div>
</form>

@push('scripts')
<script>
var pRowCount = 1;
var catOptions = `<option value="">Select...</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach`;
function addProductRow(){var i=pRowCount;var r=document.createElement('tr');r.id='prow'+i;r.innerHTML=`<td style="text-align:center;color:#94a3b8;">${i+1}</td><td><input type="text" name="products[${i}][name]" required placeholder="Product name" style="width:180px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;"></td><td><select name="products[${i}][category_id]" style="width:120px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:inherit;">${catOptions}</select></td><td><input type="number" step="0.01" min="0" name="products[${i}][price]" required placeholder="0.00" style="width:90px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;font-family:monospace;text-align:right;"></td><td><select name="products[${i}][currency]" style="width:65px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;"><option value="USD">USD</option><option value="INR">INR</option><option value="EUR">EUR</option></select></td><td><input type="text" name="products[${i}][description]" placeholder="Brief desc..." style="width:140px;padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.82rem;"></td><td><input type="file" name="products[${i}][thumbnail]" accept="image/*" style="width:120px;font-size:.72rem;"></td><td><button type="button" onclick="document.getElementById('prow${i}').remove()" style="background:none;border:none;color:#dc2626;cursor:pointer;"><i class="fas fa-trash"></i></button></td>`;document.getElementById('productsBody').appendChild(r);pRowCount++;}
</script>
@endpush
@endsection
