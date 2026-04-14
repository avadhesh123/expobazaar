@extends('layouts.app')
@section('title', 'Upload Offer Sheet')
@section('page-title', 'Upload Offer Sheet')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;"><a href="{{ route('vendor.offer-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a></div>

<div style="padding:.75rem 1.2rem;background:#eff6ff;border-radius:10px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.82rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
    Upload your product details using the provided Excel template. Required columns: <strong>Vendor SKU, Product Name, Vendor FOB</strong>. All other columns (dimensions, material, color, finish, category) are recommended.
</div>

<div style="max-width:650px;">

    {{-- ── Error banner (SKU conflicts / upload failures) ─────────────────── --}}
    @if(session('error') && session('upload_errors'))
    <div style="padding:.85rem 1.1rem;background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;margin-bottom:1rem;display:flex;gap:.75rem;align-items:flex-start;">
        <i class="fas fa-exclamation-circle" style="color:#dc2626;font-size:1.1rem;flex-shrink:0;margin-top:.1rem;"></i>
        <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:.88rem;color:#991b1b;margin-bottom:.15rem;">{!! session('error') !!}</div>
            @if(session('upload_errors'))
            <ul style="margin:.4rem 0 0 0;padding-left:1.1rem;font-size:.82rem;color:#7f1d1d;line-height:1.85;">
                @foreach(session('upload_errors') as $err)
                    <li>{!! $err !!}</li>
                @endforeach
            </ul>
            <div style="margin-top:.65rem;padding:.5rem .75rem;background:#fef2f2;border-left:3px solid #e8a838;border-radius:0 6px 6px 0;font-size:.75rem;color:#92400e;">
                <i class="fas fa-lightbulb" style="margin-right:.3rem;color:#e8a838;"></i>
                <strong>How to fix:</strong> Correct the SKUs listed above in your Excel file, then upload the file again below.
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Success banner ───────────────────────────────────────────────── --}}
    @if(session('success'))
    <div style="padding:.85rem 1.1rem;background:#dcfce7;border:1px solid #86efac;border-radius:10px;margin-bottom:1rem;display:flex;gap:.75rem;align-items:center;">
        <i class="fas fa-check-circle" style="color:#16a34a;font-size:1.1rem;flex-shrink:0;"></i>
        <span style="font-weight:600;font-size:.88rem;color:#166534;">{{ session('success') }}</span>
    </div>
    @endif

    <div class="card">
        <div class="card-header"><h3><i class="fas fa-file-excel" style="margin-right:.5rem;color:#16a34a;"></i> Upload Offer Sheet</h3></div>
        <div class="card-body">
            <form method="POST" action="{{ route('vendor.offer-sheets.store') }}" enctype="multipart/form-data">
                @csrf

                {{-- Template columns reference --}}
                <div style="padding:.75rem;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;margin-bottom:1.25rem;">
                    <div style="font-size:.78rem;font-weight:700;color:#166534;margin-bottom:.4rem;">Expected Template Columns:</div>
                    <div style="display:flex;flex-wrap:wrap;gap:.25rem;">
                        @foreach(['S.no','Vendor SKU *','Product Name *','Product Image','Length (in)','Width (in)','Height (in)','Weight (g)','Material','Color','Finish','Category','Sub Category','Vendor FOB ($) *','Comments','Selection'] as $col)
                        <span style="padding:.15rem .4rem;background:{{ str_contains($col,'*')?'#dcfce7':'#f1f5f9' }};border-radius:4px;font-size:.68rem;font-weight:{{ str_contains($col,'*')?'700':'500' }};color:{{ str_contains($col,'*')?'#166534':'#475569' }};">{{ str_replace(' *','',$col) }}{{ str_contains($col,'*')?' ★':'' }}</span>
                        @endforeach
                    </div>
                    <div style="font-size:.68rem;color:#64748b;margin-top:.3rem;">★ = Required columns</div>
                </div>

                {{-- File Upload --}}
                <div class="form-group">
                    <label>Offer Sheet File (Excel) <span style="color:#dc2626;">*</span></label>
                    <div id="dropZone"
                        style="padding:2rem;border:2px dashed {{ session('error') ? '#fca5a5' : '#d1d5db' }};border-radius:12px;text-align:center;cursor:pointer;transition:all .2s;background:{{ session('error') ? '#fff7f7' : '' }};"
                        onclick="document.getElementById('fileInput').click()"
                        ondragover="event.preventDefault();this.style.borderColor='#16a34a';this.style.background='#f0fdf4'"
                        ondragleave="this.style.borderColor='{{ session('error') ? '#fca5a5' : '#d1d5db' }}';this.style.background='{{ session('error') ? '#fff7f7' : '' }}'"
                        ondrop="event.preventDefault();document.getElementById('fileInput').files=event.dataTransfer.files;showFileName();this.style.borderColor='#16a34a'">
                        <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:{{ session('error') ? '#fca5a5' : '#94a3b8' }};display:block;margin-bottom:.5rem;" id="uploadIcon"></i>
                        <div style="font-size:.85rem;font-weight:600;color:{{ session('error') ? '#dc2626' : '#475569' }};" id="uploadText">
                            {{ session('error') ? 'Fix your file and upload again' : 'Drag & drop your Excel file here or click to browse' }}
                        </div>
                        <div style="font-size:.72rem;color:#94a3b8;margin-top:.3rem;" id="uploadHint">Accepts .xlsx, .xls, .csv (max 20MB)</div>
                    </div>
                    <input type="file" name="offer_file" id="fileInput" required accept=".xlsx,.xls,.csv" style="display:none;" onchange="showFileName()">
                    @error('offer_file')<span style="font-size:.72rem;color:#dc2626;">{{ $message }}</span>@enderror
                </div>

                <hr style="border:none;border-top:1px solid #e8ecf1;margin:1.5rem 0;">

                <div style="display:flex;gap:.5rem;justify-content:flex-end;">
                    <a href="{{ route('vendor.offer-sheets') }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Upload offer sheet? Products will be extracted from the Excel file.')">
                        <i class="fas fa-upload" style="margin-right:.3rem;"></i> Upload & Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function showFileName() {
    var input = document.getElementById('fileInput');
    if (input.files.length > 0) {
        var name = input.files[0].name;
        var size = (input.files[0].size / 1024 / 1024).toFixed(2);
        document.getElementById('uploadIcon').className = 'fas fa-file-excel';
        document.getElementById('uploadIcon').style.color = '#16a34a';
        document.getElementById('uploadText').textContent = name;
        document.getElementById('uploadText').style.color = '#16a34a';
        document.getElementById('uploadHint').textContent = size + ' MB';
        document.getElementById('dropZone').style.borderColor = '#16a34a';
        document.getElementById('dropZone').style.background = '#f0fdf4';
    }
}
</script>
@endpush
@endsection