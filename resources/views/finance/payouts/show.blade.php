@extends('layouts.app')
@section('title', 'Payout Detail')
@section('page-title', 'Vendor Payout — ' . ($payout->vendor->company_name ?? 'Vendor'))

@section('content')
@php
//$currency = match($payout->company_code) { '2000' => '₹', '2200' => '€', default => '$' };
$monthName = \Carbon\Carbon::create($payout->payout_year, $payout->payout_month)->format('F Y');
@endphp

{{-- Payout Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;font-size:.82rem;">
            <div>
                <div style="font-size:.65rem;color:#64748b;font-weight:600;text-transform:uppercase;">Vendor</div>
                <div style="font-weight:700;">{{ $payout->vendor->company_name ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size:.65rem;color:#64748b;font-weight:600;text-transform:uppercase;">Period</div>
                <div style="font-weight:600;">{{ $monthName }}</div>
            </div>
            <div>
                <div style="font-size:.65rem;color:#64748b;font-weight:600;text-transform:uppercase;">Company</div>
                <div style="font-weight:600;">{{ $payout->company_code }}</div>
            </div>
            <div>
                <div style="font-size:.65rem;color:#64748b;font-weight:600;text-transform:uppercase;">Status</div>
                <div>@php $sc = ['draft'=>'badge-gray','calculated'=>'badge-warning','approved'=>'badge-success','paid'=>'badge-info']; @endphp<span class="badge {{ $sc[$payout->status] ?? 'badge-gray' }}">{{ ucfirst($payout->status) }}</span></div>
            </div>
        </div>
    </div>
</div>

{{-- KPIs --}}
<div style="display:flex;gap:1rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;border-left:3px solid #1e40af;">
        <div class="kpi-label">Total Qty</div>
        <div class="kpi-value" style="color:#1e40af;">{{ number_format($payoutSummary['total_qty']) }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #16a34a;">
        <div class="kpi-label">Total Sales</div>
        <div class="kpi-value" style="color:#16a34a;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_sales'], 2) }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #e8a838;">
        <div class="kpi-label">EB Commission</div>
        <div class="kpi-value" style="color:#e8a838;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_commission'], 2) }}</div>
    </div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #7c3aed;">
        <div class="kpi-label">Net Payout</div>
        <div class="kpi-value" style="color:#7c3aed;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_payout'], 2) }}</div>
    </div>
</div>

{{-- SKU-Level Breakdown --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-list-alt" style="margin-right:.5rem;color:#1e3a5f;"></i> SKU-Level Payout Breakdown</h3><span style="font-size:.78rem;color:#64748b;">{{ $lineItems->count() }} items</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="width:40px;">S.No</th>
                    <th>SKU</th>
                    <th>Channel</th>
                    <th style="text-align:right;">Vendor WSP</th>
                    <th style="text-align:center;">QTY</th>
                    <th style="text-align:right;">Sale Amount</th>
                    <th style="text-align:right;">EB Commission</th>
                    <th style="text-align:right;background:#f5f3ff;">Net Payout</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lineItems as $idx => $li)
                <tr>
                    <td style="text-align:center;color:#94a3b8;font-weight:600;">{{ $idx + 1 }}</td>
                    <td style="font-family:monospace;font-weight:600;">{{ $li->sku }}</td>
                    <td style="font-size:.72rem;">{{ $li->channel }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ number_format($li->vendor_wsp, 2) }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $li->qty }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($li->sale_amount, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;color:#e8a838;">{{ $activeCurrencySymbol }}{{ number_format($li->commission, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:700;background:#f5f3ff;color:#7c3aed;">{{ $activeCurrencySymbol }}{{ number_format($li->net_payout, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:2rem;color:#94a3b8;">No order items found for this period.</td>
                </tr>
                @endforelse
            </tbody>
            @if($lineItems->isNotEmpty())
            <tfoot>
                <tr style="background:#f0f4f8;font-weight:700;">
                    <td colspan="3" style="text-align:right;">TOTAL</td>
                    <td></td>
                    <td style="text-align:center;">{{ number_format($payoutSummary['total_qty']) }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_sales'], 2) }}</td>
                    <td style="text-align:right;font-family:monospace;color:#e8a838;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_commission'], 2) }}</td>
                    <td style="text-align:right;font-family:monospace;color:#7c3aed;background:#f5f3ff;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_payout'], 2) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- Deductions --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
    {{-- Warehouse Charges --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-warehouse" style="margin-right:.5rem;color:#e8a838;"></i>Vendor Warehouse Charges</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table" style="font-size:.78rem;">
                <thead>
                    <tr style="background:#f0f4f8;">
                        <th>Warehouse</th>
                        <th>GRN</th>
                        <th>Inward</th>
                        <th>Storage</th>
                        <th>Fulfillment</th>
                        <th>Pick & Pack</th>
                        <th>Material</th>
                        <th>Total Charges</th>
                     </tr>
                </thead>
                <tbody>
                    @forelse($warehouseCharges as $wc)
                    <tr>
                        <td>{{ $wc->warehouse->name ?? '—' }}</td>
                        <td>{{ $wc->grn->grn_number ?? '—' }}</td>
                        <td><span class="badge badge-gray">{{ ucfirst($wc->inward_charge ?? '—') }}</span></td>
                        <td><span class="badge badge-gray">{{ ucfirst($wc->storage_charge ?? '—') }}</span></td>
                        <td><span class="badge badge-gray">{{ ucfirst($wc->fulfillment_charge ?? '—') }}</span></td>
                        <td><span class="badge badge-gray">{{ ucfirst($wc->pick_pack_charge ?? '—') }}</span></td>
                        <td><span class="badge badge-gray">{{ ucfirst($wc->material_cost ?? '—') }}</span></td>
                        <td><span class="badge badge-gray">{{ ucfirst($wc->total_charges ?? '—') }}</span></td>

                     </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align:center;color:#94a3b8;padding:1rem;">No warehouse charges</td>
                    </tr>
                    @endforelse
                </tbody>
                @if($warehouseCharges->isNotEmpty())
                <tfoot>
                    <tr style="background:#f0f4f8;font-weight:700;">
                        <td colspan="7" style="text-align:right;">Total</td>
                        <td>{{ $activeCurrencySymbol }}{{ number_format($warehouseCharges->sum(fn($c) => floatval($c->total_charges ?? $c->amount ?? 0)), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Chargebacks --}}
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#dc2626;"></i> Chargebacks</h3>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="data-table" style="font-size:.78rem;">
                <thead>
                    <tr style="background:#f0f4f8;">
                        <th>Order</th>
                        <th>Reason</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($chargebacks as $cb)
                    <tr>
                        <td style="font-family:monospace;font-size:.72rem;">{{ $cb->order->order_number ?? '—' }}</td>
                        <td style="font-size:.72rem;">{{ Str::limit($cb->reason ?? '—', 30) }}</td>
                        <td style="text-align:right;font-family:monospace;font-weight:600;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format(floatval($cb->amount), 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" style="text-align:center;color:#94a3b8;padding:1rem;">No chargebacks</td>
                    </tr>
                    @endforelse
                </tbody>
                @if($chargebacks->isNotEmpty())
                <tfoot>
                    <tr style="background:#f0f4f8;font-weight:700;">
                        <td colspan="2" style="text-align:right;">Total</td>
                        <td style="text-align:right;font-family:monospace;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($chargebacks->sum('amount'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

{{-- Final Payout Summary --}}
@php
$totalWhCharges = $warehouseCharges->sum(fn($c) => floatval($c->calculated_amount ?? $c->amount ?? 0));
$totalChargebacks = $chargebacks->sum('amount');
$finalPayout = $payoutSummary['total_payout'] - $totalWhCharges - $totalChargebacks;
@endphp
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-calculator" style="margin-right:.5rem;color:#7c3aed;"></i> Final Payout Summary</h3>
    </div>
    <div class="card-body" style="padding:1rem 1.4rem;">
        <table style="width:100%;max-width:500px;font-size:.85rem;">
            <tr>
                <td style="padding:.4rem 0;">Total Sales (Vendor WSP × QTY)</td>
                <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_payout'], 2) }}</td>
            </tr>
            <tr>
                <td style="padding:.4rem 0;color:#dc2626;">— Warehouse Charges</td>
                <td style="text-align:right;font-family:monospace;color:#dc2626;">{{ $activeCurrencySymbol }}{{ number_format($warehouseCharges->sum(fn($c) => floatval($c->total_charges ?? $c->amount ?? 0)), 2) }}</td>
            </tr>
            <tr>
                <td style="padding:.4rem 0;color:#dc2626;">— Chargebacks</td>
                <td style="text-align:right;font-family:monospace;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($totalChargebacks, 2) }}</td>
            </tr>
            <tr style="border-top:2px solid #1e3a5f;">
                <td style="padding:.6rem 0;font-weight:800;font-size:1rem;">NET PAYOUT</td>
                <td style="text-align:right;font-family:monospace;font-weight:800;font-size:1.1rem;color:#7c3aed;">{{ $activeCurrencySymbol }}{{ number_format($finalPayout, 2) }}</td>
            </tr>
        </table>
    </div>
</div>

<div style="margin-top:1rem;display:flex;gap:.5rem;">
    <a href="{{ route('finance.payouts') }}" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Payouts</a>
</div>
@endsection