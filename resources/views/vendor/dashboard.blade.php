@extends('layouts.app')
@section('title', 'Vendor Dashboard')
@section('page-title', 'Vendor Dashboard')

@section('content')
{{-- Onboarding Status --}}
 @php
    $vendor = auth()->user()->vendor;
    $setupSteps = [
        'account_created' => true,
        'kyc_submitted'   => !empty($vendor->kyc_status) && $vendor->kyc_status !== 'pending',
        'kyc_approved'    => $vendor->kyc_status === 'approved',
        'contract_signed' => !empty($vendor->contract_signed_at) || $vendor->contract_status === 'signed',
        'active'          => $vendor->status === 'active',
    ];
    $allComplete = !in_array(false, $setupSteps, true);
    $completedCount = count(array_filter($setupSteps));
    $totalSteps = count($setupSteps);
    $progressPct = round(($completedCount / $totalSteps) * 100);
@endphp

<div style="margin-bottom:1.25rem;border-radius:10px;border:1px solid {{ $allComplete ? '#bbf7d0' : '#fde68a' }};overflow:hidden;">
    {{-- Accordion Header — always visible --}}
    <div onclick="toggleSetupProgress()"
        style="padding:.6rem 1rem;background:{{ $allComplete ? '#f0fdf4' : '#fef3c7' }};cursor:pointer;display:flex;justify-content:space-between;align-items:center;user-select:none;">
        <div style="display:flex;align-items:center;gap:.6rem;">
            <i class="fas {{ $allComplete ? 'fa-check-circle' : 'fa-tasks' }}" style="color:{{ $allComplete ? '#16a34a' : '#e8a838' }};font-size:.9rem;"></i>
            <span style="font-size:.82rem;font-weight:700;color:{{ $allComplete ? '#166534' : '#92400e' }};">
                Account Setup {{ $allComplete ? '— Complete' : 'Progress' }}
            </span>
            <span style="font-size:.65rem;padding:1px 6px;border-radius:4px;background:{{ $allComplete ? '#16a34a' : '#e8a838' }};color:#fff;font-weight:600;">
                {{ $completedCount }}/{{ $totalSteps }}
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;">
            {{-- Mini progress bar in header --}}
            <div style="width:80px;height:5px;background:{{ $allComplete ? '#bbf7d0' : '#fde68a' }};border-radius:3px;overflow:hidden;">
                <div style="height:100%;width:{{ $progressPct }}%;background:{{ $allComplete ? '#16a34a' : '#e8a838' }};border-radius:3px;"></div>
            </div>
            <i class="fas fa-chevron-down" id="setupArrow" style="color:{{ $allComplete ? '#16a34a' : '#92400e' }};font-size:.65rem;transition:transform .2s;"></i>
        </div>
    </div>

    {{-- Accordion Body — toggle --}}
    <div id="setupBody" style="display:{{ $allComplete ? 'none' : 'block' }};padding:.75rem 1rem;background:#fff;border-top:1px solid {{ $allComplete ? '#bbf7d0' : '#fde68a' }};">
        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            @php
                $steps = [
                    'account_created' => ['icon' => 'fas fa-user-check', 'label' => 'Account Created'],
                    'kyc_submitted'   => ['icon' => 'fas fa-id-card', 'label' => 'KYC Submitted'],
                    'kyc_approved'    => ['icon' => 'fas fa-check-circle', 'label' => 'KYC Approved'],
                    'contract_signed' => ['icon' => 'fas fa-file-signature', 'label' => 'Contract Signed'],
                    'active'          => ['icon' => 'fas fa-store', 'label' => 'Active'],
                ];
            @endphp

            @foreach($steps as $key => $step)
                @php $done = $setupSteps[$key]; @endphp
                <div style="display:flex;align-items:center;gap:.3rem;padding:.3rem .6rem;background:{{ $done ? '#dcfce7' : '#f1f5f9' }};border-radius:6px;{{ !$done ? 'border:1px dashed #d1d5db;' : '' }}">
                    <i class="{{ $step['icon'] }}" style="color:{{ $done ? '#16a34a' : '#94a3b8' }};font-size:.7rem;"></i>
                    <span style="font-size:.72rem;font-weight:600;color:{{ $done ? '#166534' : '#94a3b8' }};">{{ $step['label'] }}</span>
                </div>
                @if(!$loop->last)
                <i class="fas fa-arrow-right" style="color:{{ $done ? '#16a34a' : '#d1d5db' }};font-size:.5rem;"></i>
                @endif
            @endforeach
        </div>
    </div>
</div>
 

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

 
{{-- Best Selling SKUs --}}
@php $cs = $activeCurrencySymbol ?? '$'; @endphp

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-trophy" style="margin-right:.5rem;color:#e8a838;"></i> Top Selling SKUs (50% Revenue)</h3>
        <form method="GET" style="display:flex;gap:.4rem;align-items:flex-end;">
            <div>
                <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;margin-bottom:.15rem;">From</label>
                <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}"
                    style="padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
            </div>
            <div>
                <label style="font-size:.6rem;font-weight:600;color:#64748b;display:block;margin-bottom:.15rem;">To</label>
                <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}"
                    style="padding:.3rem .4rem;border:1px solid #d1d5db;border-radius:6px;font-size:.78rem;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('vendor.dashboard') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
        </form>
    </div>

    {{-- Summary Bar --}}
    <div style="padding:.5rem .75rem;background:#fefce8;border-bottom:1px solid #fde68a;display:flex;gap:1.5rem;font-size:.78rem;flex-wrap:wrap;">
        <div>
            <span style="color:#64748b;">Period:</span>
            <strong>{{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} — {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}</strong>
        </div>
        <div>
            <span style="color:#64748b;">Total Sales:</span>
            <strong style="color:#166534;">{{ $cs }}{{ number_format($grandTotal, 2) }}</strong>
        </div>
        <div>
            <span style="color:#64748b;">SKUs driving 50%:</span>
            <strong style="color:#e8a838;">{{ $topSkuCount }} SKU(s)</strong>
            <span style="font-size:.65rem;color:#94a3b8;">({{ $topSkuPct }}% of revenue)</span>
        </div>
    </div> 
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;margin:0;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="width:30px;">#</th>
                    <th>SKU</th>
                    <th>Product Name</th>
                    <th style="text-align:center;">Orders</th>
                    <th style="text-align:center;">Sold Qty</th>
                    <th style="text-align:right;">Avg Price</th>
                    <th style="text-align:right;">Total Sales</th>
                    <th style="text-align:right;">% of Total</th> 
                </tr>
            </thead>
            <tbody>
                @php $cumulative = ($bestSelling->currentPage() - 1) * $bestSelling->perPage(); @endphp
                @forelse($bestSelling as $idx => $item)
                @php
                    $product = $item->product;
                    $pct = $grandTotal > 0 ? round(($item->total_sales / $grandTotal) * 100, 1) : 0;
                    $cumulative += floatval($item->total_sales);
                    $cumPct = $grandTotal > 0 ? round(($cumulative / $grandTotal) * 100, 1) : 0;
                    $rank = ($bestSelling->currentPage() - 1) * $bestSelling->perPage() + $idx + 1;
                    $medals = ['🥇', '🥈', '🥉'];
                    $barColor = $cumPct <= 25 ? '#16a34a' : ($cumPct <= 50 ? '#e8a838' : '#94a3b8');
                @endphp
                <tr style="{{ $rank <= 3 ? 'background:#fffef5;' : '' }}">
                    <td style="text-align:center;">
                        @if($rank <= 3)
                        <span style="font-size:1rem;">{{ $medals[$rank - 1] }}</span>
                        @else
                        <span style="color:#94a3b8;">{{ $rank }}</span>
                        @endif
                    </td>
                    <td style="font-family:monospace;font-weight:600;">{{ $product->sku ?? '—' }}</td>
                    <td style="font-size:.75rem;">{{ \Str::limit($product->name ?? '—', 30) }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $item->total_orders }}</td>
                    <td style="text-align:center;font-weight:700;color:#1e40af;">{{ number_format($item->total_qty) }}</td>
                    <td style="text-align:right;font-family:monospace;color:#64748b;">{{ $cs }}{{ number_format($item->avg_price, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:700;color:#166534;">{{ $cs }}{{ number_format($item->total_sales, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:600;color:{{ $barColor }};">{{ $pct }}%</td>
                    <!-- <td>
                        <div style="display:flex;align-items:center;gap:.3rem;">
                            <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;width:{{ min(100, $cumPct * 2) }}%;background:{{ $barColor }};border-radius:4px;"></div>
                            </div>
                            <span style="font-size:.6rem;color:#94a3b8;min-width:28px;">{{ $cumPct }}%</span>
                        </div>
                    </td> -->
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8;">
                    <i class="fas fa-chart-bar" style="font-size:1.5rem;display:block;margin-bottom:.3rem;"></i>
                    No sales data for this period.
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($bestSelling->hasPages())
    <div style="padding:.75rem 1.25rem;border-top:1px solid #e8ecf1;">
        {{ $bestSelling->links('pagination::tailwind') }}
    </div>
    @endif
</div>
<!-- GRAPH -->
 <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Sales Graph</h5>
        <div class="d-flex gap-2 align-items-center">
            <input type="date" id="sales_from" class="form-control form-control-sm"
                   value="{{ now()->subDays(30)->toDateString() }}">
            <span>to</span>
            <input type="date" id="sales_to" class="form-control form-control-sm"
                   value="{{ now()->toDateString() }}">
            <button type="button" id="btnLoadSalesChart" class="btn btn-sm btn-primary">
                Apply
            </button>
        </div>
    </div>
    <div class="card-body">
        <canvas id="vendorSalesChart" height="100"></canvas>
    </div>
</div>
 

<!-- END -->
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
<script>
function toggleSetupProgress() {
    var body = document.getElementById('setupBody');
    var arrow = document.getElementById('setupArrow');
    if (body.style.display === 'none') {
        body.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
    } else {
        body.style.display = 'none';
        arrow.style.transform = 'rotate(0deg)';
    }
}
</script>
{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('vendorSalesChart').getContext('2d');
    let salesChart = null;

    function loadSalesChart() {
        const from = document.getElementById('sales_from').value;
        const to   = document.getElementById('sales_to').value;

        fetch(`{{ route('vendor.dashboard.sales-chart') }}?from=${from}&to=${to}`)
            .then(r => r.json())
            .then(data => {
                if (salesChart) {
                    salesChart.destroy();
                }

                salesChart = new Chart(ctx, {
                    type: 'bar',   // or 'line'
                    data: {
                        labels: data.labels,
                        datasets: [
                            {
                                label: 'Sales Amount',
                                data: data.totals,
                                borderColor: '#2563eb',
                              //  backgroundColor: 'rgba(37, 99, 235, 0.1)',
                               backgroundColor: '#5773e6',
                                fill: true,
                                tension: 0.3,
                                yAxisID: 'y',
                            },
                            // {
                            //     label: 'Orders',
                            //     data: data.orders,
                            //     borderColor: '#16a34a',
                            //     backgroundColor: 'rgba(22, 163, 74, 0.1)',
                            //     fill: false,
                            //     tension: 0.3,
                            //     yAxisID: 'y1',
                            // }
                        ]
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: {
                                type: 'linear',
                                position: 'left',
                                title: { display: true, text: 'Sales Amount' }
                            },
                            // y1: {
                            //     type: 'linear',
                            //     position: 'right',
                            //     grid: { drawOnChartArea: false },
                            //     title: { display: true, text: 'Orders' }
                            // }
                        }
                    }
                });
            });
    }

    // Load on page open
    loadSalesChart();

    // Update when date changes / Apply clicked
    document.getElementById('btnLoadSalesChart').addEventListener('click', loadSalesChart);
    document.getElementById('sales_from').addEventListener('change', loadSalesChart);
    document.getElementById('sales_to').addEventListener('change', loadSalesChart);
});
</script>
@endsection