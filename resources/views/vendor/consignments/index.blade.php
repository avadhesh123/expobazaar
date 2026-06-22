@extends('layouts.app')
@section('title', 'My Consignments')
@section('page-title', 'My Consignments')

@section('content')
<div style="padding:.6rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i>
    For each consignment: upload <strong>Inspection Reports</strong> (inline / midline / final), a <strong>Commercial Invoice</strong>, and a <strong>Packing List</strong>. Both CI and PL are required before shipment processing.
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-box" style="margin-right:.5rem;color:#1e3a5f;"></i> My Consignments</h3></div>
    <div class="card-body" style="padding:0;">
        @forelse($consignments as $c)
        <div style="padding:1.25rem 1.4rem;border-bottom:1px solid #ccc;">
            {{-- Header Row --}}
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem;">
                <div>
                    <div style="font-size:1rem;font-weight:800;color:#0d1b2a;font-family:monospace;">{{ $c->consignment_number }}</div>
                    <div style="font-size:.72rem;color:#64748b;margin-top:.15rem;">
                        @php $flags = ['US' => '🇺🇸',  'EU' => '🇪🇺', 'NL' => '🇳🇱', 'UK' => '🇬🇧']; @endphp
                        {{ $flags[$c->destination_country] ?? '' }} {{ $c->destination_country }} · {{ $c->company_code }}
                        · Live Sheet: {{ $c->liveSheet->live_sheet_number ?? '—' }}
                    </div>
                </div>
                <div style="text-align:right;">
                    @php $sc = ['created'=>'badge-info','shipped'=>'badge-success','delivered'=>'badge-success','live_sheet_locked'=>'badge-warning']; @endphp
                    <span class="badge {{ $sc[$c->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$c->status)) }}</span>
                    <div style="font-size:.72rem;color:#64748b;margin-top:.2rem;">{{ $c->created_at->format('d M Y') }}</div>
                </div>
            </div>

            {{-- Stats Row --}}
            <div style="display:flex;gap:1.5rem;margin-bottom:.75rem;font-size:.78rem;">
                <span><strong>Items:</strong> {{ $c->total_items }}</span>
                <span><strong>CBM:</strong> {{ number_format($c->total_cbm, 3) }}</span>
                <span><strong>Value:</strong> <span style="color:#166534;font-weight:700;">{{$activeCurrencySymbol}}{{ number_format($c->total_value, 2) }}</span></span>
            </div>

            {{-- Three Document Sections --}}
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;">

                {{-- 1. Inspection Reports --}}
                <div style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;padding:.65rem .75rem;">
                    <div style="font-size:.72rem;font-weight:700;color:#7c3aed;text-transform:uppercase;margin-bottom:.4rem;"><i class="fas fa-search"></i> Inspection Reports</div>
                    @php $reports = $c->inspectionReports ?? collect(); @endphp
                    @if($reports->count() > 0)
                        @foreach($reports as $r)
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:.2rem 0;border-bottom:1px solid #f3e8ff;">
                            <div>
                                <span class="badge" style="font-size:.58rem;padding:.1rem .3rem;background:{{ $r->inspection_type==='final'?'#16a34a':($r->inspection_type==='midline'?'#e8a838':'#64748b') }};color:#fff;">{{ ucfirst($r->inspection_type) }}</span>
                                @if($r->result)<span class="badge" style="font-size:.55rem;padding:.08rem .25rem;background:{{ $r->result==='passed'?'#dcfce7':($r->result==='fail'?'#fef2f2':'#fefce8') }};color:{{ $r->result==='passed'?'#166534':($r->result==='fail'?'#991b1b':'#854d0e') }};">{{ ucfirst($r->result) }}</span>@endif
                                <div style="font-size:.6rem;color:#94a3b8;">{{ Str::limit($r->report_name, 20) }}</div>
                            </div>
                            <a href="{{ asset('storage/app/public/' . $r->report_file) }}" target="_blank" style="font-size:.6rem;color:#7c3aed;"><i class="fas fa-download"></i></a>
                        </div>
                        @endforeach
                    @else
                        <div style="font-size:.68rem;color:#94a3b8;margin-bottom:.3rem;">No reports uploaded yet.</div>
                    @endif
                    <button type="button" class="btn btn-outline btn-sm" style="width:100%;margin-top:.4rem;color:#7c3aed;border-color:#d8b4fe;font-size:.7rem;" onclick="document.getElementById('inspForm-{{ $c->id }}').style.display=document.getElementById('inspForm-{{ $c->id }}').style.display==='none'?'block':'none'">
                        <i class="fas fa-plus"></i> Upload Report
                    </button>
                    <div id="inspForm-{{ $c->id }}" style="display:none;margin-top:.4rem;">
                        <form method="POST" action="{{ route('vendor.inspections.upload', $c) }}" enctype="multipart/form-data">
                            @csrf
                            <div style="margin-bottom:.3rem;">
                                <label style="font-size:.6rem;font-weight:700;color:#64748b;">Type *</label>
                                <select name="inspection_type" required style="width:100%;padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:5px;font-size:.72rem;">
                                    <option value="inline">Inline</option>
                                    <option value="midline">Midline</option>
                                    <option value="final">Final</option>
                                </select>
                            </div>
                            <div style="margin-bottom:.3rem;">
                                <label style="font-size:.6rem;font-weight:700;color:#64748b;">Result</label>
                                <select name="result" style="width:100%;padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:5px;font-size:.72rem;">
                                    <option value="passed">Pass</option>
                                    <option value="fail">Fail</option>
                                    <option value="conditional">Conditional</option>
                                </select>
                            </div>
                            <div style="margin-bottom:.3rem;">
                                <label style="font-size:.6rem;font-weight:700;color:#64748b;">Remarks</label>
                                <input type="text" name="remarks" placeholder="Optional remarks" maxlength="500" style="width:100%;padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:5px;font-size:.72rem;">
                            </div>
                            <div style="margin-bottom:.3rem;">
                                <label style="font-size:.6rem;font-weight:700;color:#64748b;">Report File *</label>
                                <input type="file" name="report" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx,.ppt,.pptx" style="font-size:.68rem;width:100%;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;font-size:.7rem;"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </div>
                </div>

                {{-- 2. Commercial Invoice --}}
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.65rem .75rem;">
                    <div style="font-size:.72rem;font-weight:700;color:#1e40af;text-transform:uppercase;margin-bottom:.4rem;"><i class="fas fa-file-invoice"></i> Commercial Invoice</div>
                    @if($c->commercial_invoice_file)
                        <div style="background:#f0fdf4;padding:.4rem .5rem;border-radius:6px;border:1px solid #bbf7d0;margin-bottom:.3rem;">
                            <div style="font-size:.7rem;font-weight:700;color:#16a34a;"><i class="fas fa-check-circle"></i> Uploaded</div>
                            <div style="font-size:.68rem;color:#475569;margin-top:.15rem;">#{{ $c->commercial_invoice_number }}</div>
                            <div style="font-size:.62rem;color:#94a3b8;margin-top:.1rem;">
                                @if($c->commercial_invoice_upload_date){{ $c->commercial_invoice_upload_date->format('d M Y') }}@endif
                                @if($c->commercialInvoiceUploader) · by {{ $c->commercialInvoiceUploader->name }}@endif
                            </div>
                            <a href="{{ \App\Helpers\FileStorage::url($c->commercial_invoice_file) }}" target="_blank">
                                    <span style="font-size:.65rem;color:#1e40af;"><i class="fas fa-download"></i> Download</span>
                            </a>
                                    <!-- <a href="{{ asset('storage/app/public/' . $c->commercial_invoice_file) }}" target="_blank" style="font-size:.65rem;color:#1e40af;"><i class="fas fa-download"></i> Download</a> -->
                        </div>
                    @else
                        <div style="font-size:.68rem;color:#dc2626;margin-bottom:.3rem;"><i class="fas fa-exclamation-circle"></i> Not uploaded — required</div>
                    @endif
                    <button type="button" class="btn btn-outline btn-sm" style="width:100%;color:#1e40af;border-color:#93c5fd;font-size:.7rem;" onclick="document.getElementById('ciForm-{{ $c->id }}').style.display=document.getElementById('ciForm-{{ $c->id }}').style.display==='none'?'block':'none'">
                        <i class="fas fa-{{ $c->commercial_invoice_file ? 'redo' : 'upload' }}"></i> {{ $c->commercial_invoice_file ? 'Replace' : 'Upload' }} Invoice
                    </button>
                    <div id="ciForm-{{ $c->id }}" style="display:none;margin-top:.4rem;">
                        <form method="POST" action="{{ route('vendor.commercial-invoices.upload', $c) }}" enctype="multipart/form-data">
                            @csrf
                            <div style="margin-bottom:.3rem;">
                                <label style="font-size:.6rem;font-weight:700;color:#64748b;">Invoice # *</label>
                                <input type="text" name="commercial_invoice_number" required placeholder="e.g. CI-2026-001" maxlength="100" value="{{ $c->commercial_invoice_number }}" style="width:100%;padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:5px;font-size:.72rem;">
                            </div>
                            <div style="margin-bottom:.3rem;">
                                <label style="font-size:.6rem;font-weight:700;color:#64748b;">File (PDF/Image) *</label>
                                <input type="file" name="commercial_invoice_file" required accept=".pdf,.jpg,.jpeg,.png" style="font-size:.68rem;width:100%;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;font-size:.7rem;"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </div>
                </div>

                {{-- 3. Packing List --}}
                <div style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:.65rem .75rem;">
                    <div style="font-size:.72rem;font-weight:700;color:#854d0e;text-transform:uppercase;margin-bottom:.4rem;"><i class="fas fa-clipboard-list"></i> Packing List</div>
                    @if($c->packing_list_file)
                        <div style="background:#f0fdf4;padding:.4rem .5rem;border-radius:6px;border:1px solid #bbf7d0;margin-bottom:.3rem;">
                            <div style="font-size:.7rem;font-weight:700;color:#16a34a;"><i class="fas fa-check-circle"></i> Uploaded</div>
                            <div style="font-size:.68rem;color:#475569;margin-top:.15rem;">#{{ $c->packing_list_number }}</div>
                            <div style="font-size:.62rem;color:#94a3b8;margin-top:.1rem;">
                                @if($c->packing_list_upload_date){{ $c->packing_list_upload_date->format('d M Y') }}@endif
                                @if($c->packingListUploader) · by {{ $c->packingListUploader->name }}@endif
                            </div>
                            <a href="{{ asset('storage/app/public/' . $c->packing_list_file) }}" target="_blank" style="font-size:.65rem;color:#854d0e;"><i class="fas fa-download"></i> Download</a>
                        </div>
                    @else
                        <div style="font-size:.68rem;color:#dc2626;margin-bottom:.3rem;"><i class="fas fa-exclamation-circle"></i> Not uploaded — required</div>
                    @endif
                    <button type="button" class="btn btn-outline btn-sm" style="width:100%;color:#854d0e;border-color:#fde68a;font-size:.7rem;" onclick="document.getElementById('plForm-{{ $c->id }}').style.display=document.getElementById('plForm-{{ $c->id }}').style.display==='none'?'block':'none'">
                        <i class="fas fa-{{ $c->packing_list_file ? 'redo' : 'upload' }}"></i> {{ $c->packing_list_file ? 'Replace' : 'Upload' }} Packing List
                    </button>
                    <div id="plForm-{{ $c->id }}" style="display:none;margin-top:.4rem;">
                        <form method="POST" action="{{ route('vendor.packing-list.upload', $c) }}" enctype="multipart/form-data">
                            @csrf
                            <div style="margin-bottom:.3rem;">
                                <label style="font-size:.6rem;font-weight:700;color:#64748b;">Packing List # *</label>
                                <input type="text" name="packing_list_number" required placeholder="e.g. PL-2026-001" maxlength="100" value="{{ $c->packing_list_number }}" style="width:100%;padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:5px;font-size:.72rem;">
                            </div>
                            <div style="margin-bottom:.3rem;">
                                <label style="font-size:.6rem;font-weight:700;color:#64748b;">File (PDF/Excel/Image) *</label>
                                <input type="file" name="packing_list_file" required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.csv" style="font-size:.68rem;width:100%;">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;font-size:.7rem;"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Additional Documents Row --}}
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:.75rem;margin-top:.75rem;">

                {{-- 4. Shipping Bill --}}
                <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:.65rem .75rem;">
                    <div style="font-size:.72rem;font-weight:700;color:#c2410c;text-transform:uppercase;margin-bottom:.4rem;"><i class="fas fa-ship"></i> Shipping Bill</div>
                    @if($c->shipping_bill_file)
                        <div style="background:#f0fdf4;padding:.4rem .5rem;border-radius:6px;border:1px solid #bbf7d0;margin-bottom:.3rem;">
                            <div style="font-size:.7rem;font-weight:700;color:#16a34a;"><i class="fas fa-check-circle"></i> Uploaded</div>
                            <div style="font-size:.68rem;color:#475569;">#{{ $c->shipping_bill_number }}</div>
                            <div style="font-size:.62rem;color:#94a3b8;">@if($c->shipping_bill_upload_date){{ $c->shipping_bill_upload_date->format('d M Y') }}@endif @if($c->shippingBillUploader) · {{ $c->shippingBillUploader->name }}@endif</div>
                            <a href="{{ asset('storage/app/public/' . $c->shipping_bill_file) }}" target="_blank" style="font-size:.65rem;color:#c2410c;"><i class="fas fa-download"></i> Download</a>
                        </div>
                    @else
                        <div style="font-size:.68rem;color:#94a3b8;margin-bottom:.3rem;">Not uploaded</div>
                    @endif
                    <button type="button" class="btn btn-outline btn-sm" style="width:100%;color:#c2410c;border-color:#fed7aa;font-size:.68rem;" onclick="document.getElementById('sbForm-{{ $c->id }}').style.display=document.getElementById('sbForm-{{ $c->id }}').style.display==='none'?'block':'none'">
                        <i class="fas fa-{{ $c->shipping_bill_file ? 'redo' : 'upload' }}"></i> {{ $c->shipping_bill_file ? 'Replace' : 'Upload' }}
                    </button>
                    <div id="sbForm-{{ $c->id }}" style="display:none;margin-top:.4rem;">
                        <form method="POST" action="{{ route('vendor.shipping-bill.upload', $c) }}" enctype="multipart/form-data">@csrf
                            <div style="margin-bottom:.3rem;"><label style="font-size:.6rem;font-weight:700;color:#64748b;">Bill # *</label><input type="text" name="shipping_bill_number" required placeholder="SB-001" maxlength="100" value="{{ $c->shipping_bill_number }}" style="width:100%;padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:5px;font-size:.72rem;"></div>
                            <div style="margin-bottom:.3rem;"><label style="font-size:.6rem;font-weight:700;color:#64748b;">File *</label><input type="file" name="shipping_bill_file" required accept=".pdf,.jpg,.jpeg,.png" style="font-size:.68rem;width:100%;"></div>
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;font-size:.68rem;"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </div>
                </div>

                {{-- 5. Measurement Copy --}}
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.65rem .75rem;">
                    <div style="font-size:.72rem;font-weight:700;color:#166534;text-transform:uppercase;margin-bottom:.4rem;"><i class="fas fa-ruler-combined"></i> Measurement Copy</div>
                    @if($c->measurement_file)
                        <div style="background:#f0fdf4;padding:.4rem .5rem;border-radius:6px;border:1px solid #86efac;margin-bottom:.3rem;">
                            <div style="font-size:.7rem;font-weight:700;color:#16a34a;"><i class="fas fa-check-circle"></i> Uploaded</div>
                            <div style="font-size:.68rem;color:#475569;">#{{ $c->measurement_number }}</div>
                            <div style="font-size:.62rem;color:#94a3b8;">@if($c->measurement_upload_date){{ $c->measurement_upload_date->format('d M Y') }}@endif @if($c->measurementUploader) · {{ $c->measurementUploader->name }}@endif</div>
                            <a href="{{ asset('storage/app/public/' . $c->measurement_file) }}" target="_blank" style="font-size:.65rem;color:#166534;"><i class="fas fa-download"></i> Download</a>
                        </div>
                    @else
                        <div style="font-size:.68rem;color:#94a3b8;margin-bottom:.3rem;">Not uploaded</div>
                    @endif
                    <button type="button" class="btn btn-outline btn-sm" style="width:100%;color:#166534;border-color:#bbf7d0;font-size:.68rem;" onclick="document.getElementById('measForm-{{ $c->id }}').style.display=document.getElementById('measForm-{{ $c->id }}').style.display==='none'?'block':'none'">
                        <i class="fas fa-{{ $c->measurement_file ? 'redo' : 'upload' }}"></i> {{ $c->measurement_file ? 'Replace' : 'Upload' }}
                    </button>
                    <div id="measForm-{{ $c->id }}" style="display:none;margin-top:.4rem;">
                        <form method="POST" action="{{ route('vendor.measurement.upload', $c) }}" enctype="multipart/form-data">@csrf
                            <div style="margin-bottom:.3rem;"><label style="font-size:.6rem;font-weight:700;color:#64748b;">Ref # *</label><input type="text" name="measurement_number" required placeholder="MEAS-001" maxlength="100" value="{{ $c->measurement_number }}" style="width:100%;padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:5px;font-size:.72rem;"></div>
                            <div style="margin-bottom:.3rem;"><label style="font-size:.6rem;font-weight:700;color:#64748b;">File *</label><input type="file" name="measurement_file" required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls" style="font-size:.68rem;width:100%;"></div>
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;font-size:.68rem;"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </div>
                </div>

                {{-- 6. HBL Copy --}}
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.65rem .75rem;">
                    <div style="font-size:.72rem;font-weight:700;color:#1e40af;text-transform:uppercase;margin-bottom:.4rem;"><i class="fas fa-file-alt"></i> HBL Copy</div>
                    @if($c->hbl_file)
                        <div style="background:#f0fdf4;padding:.4rem .5rem;border-radius:6px;border:1px solid #bbf7d0;margin-bottom:.3rem;">
                            <div style="font-size:.7rem;font-weight:700;color:#16a34a;"><i class="fas fa-check-circle"></i> Uploaded</div>
                            <div style="font-size:.68rem;color:#475569;">#{{ $c->hbl_number }}</div>
                            <div style="font-size:.62rem;color:#94a3b8;">@if($c->hbl_upload_date){{ $c->hbl_upload_date->format('d M Y') }}@endif @if($c->hblUploader) · {{ $c->hblUploader->name }}@endif</div>
                            <a href="{{ asset('storage/app/public/' . $c->hbl_file) }}" target="_blank" style="font-size:.65rem;color:#1e40af;"><i class="fas fa-download"></i> Download</a>
                        </div>
                    @else
                        <div style="font-size:.68rem;color:#94a3b8;margin-bottom:.3rem;">Not uploaded</div>
                    @endif
                    <button type="button" class="btn btn-outline btn-sm" style="width:100%;color:#1e40af;border-color:#bfdbfe;font-size:.68rem;" onclick="document.getElementById('hblForm-{{ $c->id }}').style.display=document.getElementById('hblForm-{{ $c->id }}').style.display==='none'?'block':'none'">
                        <i class="fas fa-{{ $c->hbl_file ? 'redo' : 'upload' }}"></i> {{ $c->hbl_file ? 'Replace' : 'Upload' }}
                    </button>
                    <div id="hblForm-{{ $c->id }}" style="display:none;margin-top:.4rem;">
                        <form method="POST" action="{{ route('vendor.hbl.upload', $c) }}" enctype="multipart/form-data">@csrf
                            <div style="margin-bottom:.3rem;"><label style="font-size:.6rem;font-weight:700;color:#64748b;">HBL # *</label><input type="text" name="hbl_number" required placeholder="HBL-001" maxlength="100" value="{{ $c->hbl_number }}" style="width:100%;padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:5px;font-size:.72rem;"></div>
                            <div style="margin-bottom:.3rem;"><label style="font-size:.6rem;font-weight:700;color:#64748b;">File *</label><input type="file" name="hbl_file" required accept=".pdf,.jpg,.jpeg,.png" style="font-size:.68rem;width:100%;"></div>
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;font-size:.68rem;"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </div>
                </div>

                {{-- 7. Other Document --}}
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.65rem .75rem;">
                    <div style="font-size:.72rem;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:.4rem;"><i class="fas fa-paperclip"></i> Other Document</div>
                    @if($c->other_doc_file)
                        <div style="background:#f0fdf4;padding:.4rem .5rem;border-radius:6px;border:1px solid #bbf7d0;margin-bottom:.3rem;">
                            <div style="font-size:.7rem;font-weight:700;color:#16a34a;"><i class="fas fa-check-circle"></i> Uploaded</div>
                            <div style="font-size:.68rem;color:#475569;">{{ $c->other_doc_name }}</div>
                            <div style="font-size:.62rem;color:#94a3b8;">@if($c->other_doc_upload_date){{ $c->other_doc_upload_date->format('d M Y') }}@endif @if($c->otherDocUploader) · {{ $c->otherDocUploader->name }}@endif</div>
                            <a href="{{ asset('storage/app/public/' . $c->other_doc_file) }}" target="_blank" style="font-size:.65rem;color:#475569;"><i class="fas fa-download"></i> Download</a>
                        </div>
                    @else
                        <div style="font-size:.68rem;color:#94a3b8;margin-bottom:.3rem;">Not uploaded</div>
                    @endif
                    <button type="button" class="btn btn-outline btn-sm" style="width:100%;color:#475569;border-color:#e2e8f0;font-size:.68rem;" onclick="document.getElementById('otherForm-{{ $c->id }}').style.display=document.getElementById('otherForm-{{ $c->id }}').style.display==='none'?'block':'none'">
                        <i class="fas fa-{{ $c->other_doc_file ? 'redo' : 'upload' }}"></i> {{ $c->other_doc_file ? 'Replace' : 'Upload' }}
                    </button>
                    <div id="otherForm-{{ $c->id }}" style="display:none;margin-top:.4rem;">
                        <form method="POST" action="{{ route('vendor.other-doc.upload', $c) }}" enctype="multipart/form-data">@csrf
                            <div style="margin-bottom:.3rem;"><label style="font-size:.6rem;font-weight:700;color:#64748b;">Document Name *</label><input type="text" name="other_doc_name" required placeholder="e.g. Certificate of Origin" maxlength="200" value="{{ $c->other_doc_name }}" style="width:100%;padding:.25rem .4rem;border:1px solid #d1d5db;border-radius:5px;font-size:.72rem;"></div>
                            <div style="margin-bottom:.3rem;"><label style="font-size:.6rem;font-weight:700;color:#64748b;">File *</label><input type="file" name="other_doc_file" required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx" style="font-size:.68rem;width:100%;"></div>
                            <button type="submit" class="btn btn-primary btn-sm" style="width:100%;font-size:.68rem;"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:3rem;color:#94a3b8;">
            <i class="fas fa-box" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
            No consignments found.
        </div>
        @endforelse
    </div>
    @if($consignments->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $consignments->links('pagination::tailwind') }}</div>@endif
</div>
@endsection