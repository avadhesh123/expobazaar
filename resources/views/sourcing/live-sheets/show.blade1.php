@extends('layouts.app')
@section('title', 'Live Sheet: ' . $liveSheet->live_sheet_number)
@section('page-title', 'Live Sheet Detail')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('sourcing.live-sheets') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> All Live Sheets</a>
    @if($liveSheet->offerSheet)<a href="{{ route('sourcing.offer-sheets.review', $liveSheet->offerSheet) }}" class="btn btn-outline btn-sm"><i class="fas fa-file-alt"></i> View Offer Sheet</a>@endif
</div>

{{-- Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1.25rem 1.4rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <div>
                <div style="font-size:1.15rem;font-weight:800;color:#0d1b2a;font-family:monospace;">{{ $liveSheet->live_sheet_number }}</div>
                <div style="font-size:.82rem;color:#64748b;">
                    Vendor: <strong>{{ $liveSheet->vendor->company_name ?? '—' }}</strong> ·
                    Company: {{ $liveSheet->company_code }} ·
                    From: {{ $liveSheet->offerSheet->offer_sheet_number ?? '—' }}
                </div>
            </div>
            <div style="display:flex;gap:.5rem;">
                @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-warning','locked'=>'badge-success','unlocked'=>'badge-warning']; @endphp
                <span class="badge {{ $sc[$liveSheet->status]??'badge-gray' }}" style="font-size:.85rem;padding:.3rem .8rem;">{{ ucfirst($liveSheet->status) }}</span>
                @if($liveSheet->is_locked)<span style="padding:.3rem .6rem;background:#dcfce7;border-radius:8px;font-size:.78rem;font-weight:600;color:#166534;"><i class="fas fa-lock"></i> Locked</span>@endif
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.75rem;">
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Items</div><div style="font-weight:700;font-size:1rem;">{{ $liveSheet->items->count() }}</div></div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Total Qty</div><div style="font-weight:700;">{{ $liveSheet->items->sum('quantity') }}</div></div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Total CBM</div><div style="font-weight:800;font-family:monospace;color:#1e3a5f;">{{ number_format($liveSheet->total_cbm, 3) }}</div></div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Total Value</div><div style="font-weight:700;font-family:monospace;color:#166534;">${{ number_format($liveSheet->items->sum('total_price'), 2) }}</div></div>
            <div style="padding:.6rem;background:#f8fafc;border-radius:8px;"><div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Total Weight</div><div style="font-weight:600;font-family:monospace;">{{ number_format($liveSheet->items->sum('total_weight'), 2) }} kg</div></div>
        </div>

        @if($liveSheet->is_locked)
        <div style="margin-top:.75rem;padding:.5rem .75rem;background:#f0fdf4;border-radius:6px;font-size:.78rem;color:#166534;">
            <i class="fas fa-lock" style="margin-right:.2rem;"></i> Locked on {{ $liveSheet->locked_at?->format('d M Y H:i') }} by {{ $liveSheet->lockedByUser->name ?? '—' }}
        </div>
        @endif
    </div>
</div>

{{-- Action Bar --}}
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;padding:.75rem 1rem;background:#fff;border-radius:10px;border:1px solid #e8ecf1;">
    @if($liveSheet->status === 'submitted')
        <form method="POST" action="{{ route('sourcing.live-sheets.approve', $liveSheet) }}" style="display:inline;" onsubmit="return confirm('Approve and LOCK this live sheet?')">
            @csrf
            <button type="submit" class="btn btn-success"><i class="fas fa-lock" style="margin-right:.3rem;"></i> Approve & Lock</button>
        </form>
    @endif

    @if($liveSheet->is_locked && !$liveSheet->consignment)
        <form method="POST" action="{{ route('sourcing.live-sheets.create-consignment', $liveSheet) }}" style="display:inline;" onsubmit="return confirm('Create Consignment?\n\nThis will generate a unique consignment number and notify Logistics.')">
            @csrf
            <button type="submit" class="btn btn-primary"><i class="fas fa-box" style="margin-right:.3rem;"></i> Create Consignment</button>
        </form>
    @endif

    @if($liveSheet->consignment)
        <span style="display:flex;align-items:center;gap:.3rem;padding:.5rem .75rem;background:#dcfce7;border-radius:8px;font-size:.85rem;font-weight:600;color:#166534;">
            <i class="fas fa-check-circle"></i> Consignment: {{ $liveSheet->consignment->consignment_number }}
        </span>
    @endif

    @if($liveSheet->status === 'draft')
        <span style="display:flex;align-items:center;gap:.3rem;padding:.5rem .75rem;background:#fef3c7;border-radius:8px;font-size:.82rem;color:#92400e;">
            <i class="fas fa-clock"></i> Waiting for vendor to fill product details
        </span>
    @endif
</div>

{{-- Items Table --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-list" style="margin-right:.5rem;color:#1e3a5f;"></i> Product Items ({{ $liveSheet->items->count() }})</h3></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>SKU</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>CBM/Unit</th><th>Total CBM</th><th>Wt/Unit</th><th>Total Wt</th><th>Details</th></tr></thead>
            <tbody>
                @foreach($liveSheet->items as $item)
                @php $d = $item->product_details ?? []; @endphp
                <tr>
                    <td style="font-family:monospace;font-weight:600;font-size:.82rem;">{{ $item->product->sku ?? '—' }}</td>
                    <td style="font-weight:600;font-size:.82rem;">{{ $item->product->name ?? '—' }}</td>
                    <td style="text-align:center;font-weight:700;">{{ $item->quantity }}</td>
                    <td style="font-family:monospace;">${{ number_format($item->unit_price, 2) }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">${{ number_format($item->total_price, 2) }}</td>
                    <td style="font-family:monospace;text-align:center;">{{ number_format($item->cbm_per_unit, 4) }}</td>
                    <td style="font-family:monospace;font-weight:600;">{{ number_format($item->total_cbm, 3) }}</td>
                    <td style="font-family:monospace;text-align:center;">{{ number_format($item->weight_per_unit, 2) }}</td>
                    <td style="font-family:monospace;">{{ number_format($item->total_weight, 2) }} kg</td>
                    <td>
                        @if(!empty($d))
                            <div style="display:flex;flex-wrap:wrap;gap:.15rem;">
                                @foreach(['material','color','finish'] as $key)
                                    @if(!empty($d[$key]))<span style="font-size:.6rem;padding:.1rem .25rem;background:#f1f5f9;border-radius:3px;color:#475569;">{{ $d[$key] }}</span>@endif
                                @endforeach
                            </div>
                        @else — @endif
                    </td>
                </tr>
                @endforeach
                <tr style="background:#f8fafc;font-weight:700;">
                    <td colspan="2" style="text-align:right;">TOTALS</td>
                    <td style="text-align:center;">{{ $liveSheet->items->sum('quantity') }}</td>
                    <td></td>
                    <td style="font-family:monospace;color:#166534;">${{ number_format($liveSheet->items->sum('total_price'), 2) }}</td>
                    <td></td>
                    <td style="font-family:monospace;">{{ number_format($liveSheet->total_cbm, 3) }}</td>
                    <td></td>
                    <td style="font-family:monospace;">{{ number_format($liveSheet->items->sum('total_weight'), 2) }} kg</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
