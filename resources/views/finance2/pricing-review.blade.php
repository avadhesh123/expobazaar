@extends('layouts.app')
@section('title', 'Pricing Review')
@section('page-title', 'Platform Pricing Review')

@section('content')
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-invoice-dollar" style="margin-right:.5rem;color:#e8a838;"></i> Pending Pricing Approval</h3>
        <span style="font-size:.78rem;color:#64748b;">{{ $pricings->total() }} items pending</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Platform</th>
                    <th>ASN</th>
                    <th>Cost Price</th>
                    <th>Platform Price</th>
                    <th>Selling Price</th>
                    <th>Margin %</th>
                    <th>Company</th>
                    <th>Prepared By</th>
                    <th style="width:100px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @php $groupedByAsn = $pricings->groupBy('asn_id'); @endphp
                @forelse($pricings as $p)
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:.82rem;">{{ $p->product->name ?? '—' }}</div>
                    </td>
                    <td style="font-family:monospace;font-size:.8rem;">{{ $p->product->sku ?? '—' }}</td>
                    <td>
                        <span class="badge badge-info">{{ $p->salesChannel->name ?? '—' }}</span>
                    </td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $p->asn->asn_number ?? '—' }}</td>
                    <td style="font-family:monospace;font-weight:600;">${{ number_format($p->cost_price, 2) }}</td>
                    <td style="font-family:monospace;">${{ number_format($p->platform_price, 2) }}</td>
                    <td style="font-family:monospace;font-weight:700;color:#166534;">${{ number_format($p->selling_price, 2) }}</td>
                    <td>
                        @php $margin = $p->margin_percent; @endphp
                        <span style="font-weight:700;color:{{ $margin >= 30 ? '#166534' : ($margin >= 15 ? '#e8a838' : '#dc2626') }};">
                            {{ number_format($margin, 1) }}%
                        </span>
                        <div style="margin-top:.2rem;background:#e2e8f0;border-radius:3px;height:4px;width:80px;">
                            <div style="height:4px;border-radius:3px;width:{{ min($margin, 100) }}%;background:{{ $margin >= 30 ? '#16a34a' : ($margin >= 15 ? '#e8a838' : '#dc2626') }};"></div>
                        </div>
                    </td>
                    <td>
                        @php $cc = ['2000'=>'🇮🇳','2100'=>'🇺🇸','2200'=>'🇳🇱']; @endphp
                        <span style="font-size:.82rem;">{{ $cc[$p->company_code] ?? '' }} {{ $p->company_code }}</span>
                    </td>
                    <td style="font-size:.78rem;color:#64748b;">{{ $p->preparer->name ?? '—' }}</td>
                    <td>
                        @if($p->asn)
                        <form method="POST" action="{{ route('finance.pricing.approve', $p->asn) }}" style="display:inline;" onsubmit="return confirm('Approve all pricing for ASN {{ $p->asn->asn_number }}?')">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" style="text-align:center;padding:3rem;color:#94a3b8;">
                        <i class="fas fa-check-circle" style="font-size:2rem;color:#16a34a;display:block;margin-bottom:.5rem;"></i>
                        No pricing pending review.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pricings->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $pricings->links('pagination::tailwind') }}</div>
    @endif
</div>

{{-- LEGEND --}}
<div style="margin-top:1rem;display:flex;gap:1.5rem;font-size:.75rem;color:#64748b;">
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#16a34a;margin-right:.3rem;"></span> Margin ≥ 30% (Good)</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#e8a838;margin-right:.3rem;"></span> Margin 15-30% (Moderate)</span>
    <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#dc2626;margin-right:.3rem;"></span> Margin < 15% (Low)</span>
</div>
@endsection
