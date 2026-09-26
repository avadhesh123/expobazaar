@extends('layouts.app')
@section('title', 'Vendor Dashboard')
@section('page-title', 'Vendor Dashboard')

@section('content')
{{-- Onboarding Status --}}
@if($vendor->status !== 'active')
<div style="padding:.85rem 1.2rem;background:#fef3c7;border-radius:10px;border:1px solid #fde68a;margin-bottom:1.25rem;">
    <div style="font-size:.85rem;font-weight:700;color:#92400e;margin-bottom:.4rem;">Account Setup Progress</div>
    <div style="display:flex;gap:.5rem;align-items:center;">
        @php
        $steps = [
        ['Account Created', 'fas fa-user-check', true],
        ['KYC Submitted', 'fas fa-id-card', in_array($vendor->kyc_status ?? '', ['submitted', 'approved'])],
        ['KYC Approved', 'fas fa-check-circle', $vendor->kyc_status === 'approved'],
        ['Contract Signed', 'fas fa-file-signature', in_array($vendor->contract_status ?? '', ['signed', 'sent'])],
        ['Active', 'fas fa-store', !in_array($vendor->status ?? '', ['pending_kyc', 'pending_approval'])],
        ];
        @endphp
        @foreach($steps as $index => [$label, $icon, $done])
        <div style="display:flex;align-items:center;gap:.3rem;padding:.3rem .6rem;background:{{ $done?'#dcfce7':'#f1f5f9' }};border-radius:6px;">
            <i class="{{ $icon }}" style="color:{{ $done?'#16a34a':'#94a3b8' }};font-size:.7rem;"></i>
            <span style="font-size:.72rem;font-weight:600;color:{{ $done?'#166534':'#94a3b8' }};">{{ $label }}</span>
        </div>
        @if(!$loop->last)<i class="fas fa-arrow-right" style="color:#d1d5db;font-size:.5rem;"></i>@endif
        @endforeach
    </div>
    @if($vendor->kyc_status === 'pending')<a href="{{ route('vendor.kyc') }}" class="btn btn-secondary btn-sm" style="margin-top:.5rem;"><i class="fas fa-upload"></i> Submit KYC Documents</a>@endif
</div>
@endif

{{-- KPIs --}}
<div class="grid-kpi">
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;">
            <div>
                <div class="kpi-label">Offer Sheets</div>
                <div class="kpi-value">{{ $data['stats']['offer_sheets'] ?? 0 }}</div>
            </div>
            <div class="kpi-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-file-alt"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;">
            <div>
                <div class="kpi-label">Consignments</div>
                <div class="kpi-value">{{ $data['stats']['consignments'] ?? 0 }}</div>
            </div>
            <div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-box"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;">
            <div>
                <div class="kpi-label">Total Sales</div>
                <div class="kpi-value" style="color:#16a34a;">{{ $activeCurrencySymbol }}{{ number_format($data['stats']['total_sales'], 2) }}</div>
            </div>
            <div class="kpi-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>
    <div class="kpi-card">
        <div style="display:flex;justify-content:space-between;">
            <div>
                <div class="kpi-label">Pending Payout</div>
                <div class="kpi-value" style="color:#e8a838;">{{ ($data['stats']['pending_payout'] ?? 0) < 0 ? '-' : '' }}{{ $activeCurrencySymbol }}{{ number_format(abs($data['stats']['pending_payout'] ?? 0), 2) }}</div>
            </div>
            <div class="kpi-icon" style="background:#fef3c7;color:#e8a838;"><i class="fas fa-money-check-alt"></i></div>
        </div>
    </div>
</div>
{{-- Top 20 Best Selling SKUs --}}
@php $cs = $activeCurrencySymbol ?? '$'; @endphp

<div class="card" style="margin-top:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-trophy" style="margin-right:.5rem;color:#e8a838;"></i> Top 20 Best Selling SKUs</h3>
        <form method="GET" style="display:flex;gap:.4rem;align-items:flex-end;">
            <div>
                <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;margin-bottom:.15rem;">From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}"
                    style="padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
            </div>
            <div>
                <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;margin-bottom:.15rem;">To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}"
                    style="padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('vendor.dashboard') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>

    <div style="padding:.4rem 1.25rem;background:#fefce8;border-bottom:1px solid #fde68a;font-size:.72rem;color:#854d0e;">
        <i class="fas fa-calendar" style="margin-right:.2rem;"></i>
        {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        · {{ $bestSelling->sum('total_qty') }} units sold · {{ $cs }}{{ number_format($bestSelling->sum('total_sales'), 2) }} total
    </div>

    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;margin:0;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="width:30px;">#</th>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th style="text-align:center;">Orders</th>
                    <th style="text-align:center;">Shipped Qty</th>
                    <th style="text-align:right;">Avg Price</th>
                    <th style="text-align:right;">Total Sales</th>
                    <th style="text-align:right;">% Share</th>
                    <th style="width:150px;">Sales Distribution</th>
                </tr>
            </thead>
            <tbody>
                @php $grandTotal = $bestSelling->sum('total_sales'); @endphp
                @forelse($bestSelling as $idx => $item)
                @php
                    $product = $item->product;
                    $pct = $grandTotal > 0 ? round(($item->total_sales / $grandTotal) * 100, 1) : 0;
                    $medals = ['🥇', '🥈', '🥉'];
                    $barColors = ['#e8a838', '#94a3b8', '#b45309', '#1e40af', '#16a34a', '#7c3aed', '#dc2626', '#0d9488'];
                    $barColor = $barColors[$idx % count($barColors)];
                @endphp
                <tr style="{{ $idx < 3 ? 'background:#fffef5;' : '' }}">
                    <td style="text-align:center;">
                        @if($idx < 3)
                        <span style="font-size:1rem;">{{ $medals[$idx] }}</span>
                        @else
                        <span style="color:#94a3b8;">{{ $idx + 1 }}</span>
                        @endif
                    </td>
                    <td style="font-family:monospace;font-weight:600;">{{ $product->sku ?? '—' }}</td>
                    <td style="font-size:.75rem;">{{ \Str::limit($product->name ?? '—', 35) }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $item->total_orders }}</td>
                    <td style="text-align:center;font-weight:700;color:#1e40af;">{{ number_format($item->total_qty) }}</td>
                    <td style="text-align:right;font-family:monospace;color:#64748b;">{{ $cs }}{{ number_format($item->avg_price, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:700;color:#166534;">{{ $cs }}{{ number_format($item->total_sales, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;color:{{ $barColor }};">{{ $pct }}%</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.3rem;">
                            <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;width:{{ $pct }}%;background:{{ $barColor }};border-radius:4px;"></div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8;">
                    <i class="fas fa-chart-bar" style="font-size:1.5rem;display:block;margin-bottom:.3rem;"></i>
                    No sales data for this period.
                </td></tr>
                @endforelse
            </tbody>
            @if($bestSelling->isNotEmpty())
            <tfoot>
                <tr style="background:#f0f4f8;font-weight:700;">
                    <td colspan="3">TOP 20 TOTAL</td>
                    <td style="text-align:center;">{{ $bestSelling->sum('total_orders') }}</td>
                    <td style="text-align:center;">{{ number_format($bestSelling->sum('total_qty')) }}</td>
                    <td></td>
                    <td style="text-align:right;font-family:monospace;color:#166534;">{{ $cs }}{{ number_format($grandTotal, 2) }}</td>
                    <td style="text-align:right;">100%</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-receipt" style="margin-right:.5rem;color:#1e3a5f;"></i> Recent Sales</h3><a href="{{ route('vendor.sales') }}" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Platform</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['recent_orders'] ?? [] as $o)
                    @php $oli = $orderLineItems[$o->id] ?? ['sale_amount' => 0, 'shipped_qty' => 0]; @endphp

                    <tr>
                        <td style="font-weight:600;font-family:monospace;font-size:.82rem;">{{ $o->order_number }}</td>
                        <td><span class="badge badge-info">{{ $o->salesChannel->name ?? '—' }}</span></td>

                        <td style="text-align:right;font-family:monospace;font-weight:700;color:#166534;">{{ $activeCurrencySymbol }}{{ number_format($oli['sale_amount'], 2) }}</td>
                        <td style="font-size:.82rem;">{{ $o->order_date?->format('d M Y') }}</td>

                    </tr>
                    @empty<tr>
                        <td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem;">No sales yet.</td>
                    </tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>

    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-box" style="margin-right:.5rem;color:#2d6a4f;"></i> Active Consignments</h3><a href="{{ route('vendor.consignments') }}" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Consignment</th>
                        <th>Items</th>
                        <th>Live Sheet</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['active_consignments'] ?? [] as $c)
                    <tr>
                        <td style="font-weight:600;font-family:monospace;font-size:.8rem;">{{ $c->consignment_number }}</td>
                        <td style="text-align:center;">{{ $c->total_items }}</td>
                        <td>@if($c->liveSheet)<span class="badge {{ $c->liveSheet->is_locked?'badge-success':($c->liveSheet->status==='submitted'?'badge-warning':'badge-gray') }}">{{ $c->liveSheet->is_locked?'Locked':ucfirst($c->liveSheet->status) }}</span>@else<span class="badge badge-gray">Pending</span>@endif</td>
                        <td><span class="badge badge-info">{{ ucfirst(str_replace('_',' ',$c->status)) }}</span></td>
                    </tr>
                    @empty<tr>
                        <td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem;">No active consignments.</td>
                    </tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-bolt" style="margin-right:.5rem;color:#e8a838;"></i> Quick Actions</h3>
    </div>
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:.5rem;">
        <a href="{{ route('vendor.offer-sheets') }}" class="btn btn-outline"><i class="fas fa-file-alt"></i> Offer Sheets</a>
        <a href="{{ route('vendor.consignments') }}" class="btn btn-outline"><i class="fas fa-box"></i> Consignments</a>
        <a href="{{ route('vendor.live-sheets') }}" class="btn btn-outline"><i class="fas fa-clipboard-list"></i> Live Sheets</a>
        <a href="{{ route('vendor.sales') }}" class="btn btn-outline"><i class="fas fa-chart-line"></i> Sales</a>
        <a href="{{ route('vendor.chargebacks') }}" class="btn btn-outline"><i class="fas fa-exclamation-triangle"></i> Chargebacks</a>
        <a href="{{ route('vendor.payouts') }}" class="btn btn-outline"><i class="fas fa-money-check-alt"></i> Payouts</a>
    </div>
</div>
@endsection