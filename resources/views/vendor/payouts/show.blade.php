@extends('layouts.app')
@section('title', 'Payout Detail')
@section('page-title', 'Payout — ' . date('M Y', mktime(0,0,0,$payout->payout_month,1,$payout->payout_year)))

@section('content')
<a href="{{ route('vendor.payouts') }}" class="btn btn-outline btn-sm" style="margin-bottom:1rem;"><i class="fas fa-arrow-left"></i> Back to Payouts</a>
{{-- In vendor/payouts/show.blade.php --}}
<a href="{{ route('vendor.payouts.download', $payout) }}" class="btn btn-outline btn-sm">
    <i class="fas fa-download" style="margin-right:.2rem;"></i> Download Payout
</a>
{{-- Payout Header --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;font-size:.82rem;">
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Vendor</div><div style="font-weight:700;">{{ $payout->vendor->company_name ?? '—' }}</div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Period</div><div style="font-weight:600;">{{ date('F Y', mktime(0,0,0,$payout->payout_month,1,$payout->payout_year)) }}</div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Company</div><div>{{ $payout->company_code }}</div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Status</div><div>@php $sc = ['calculated'=>'badge-warning','approved'=>'badge-info','payment_pending'=>'badge-warning','paid'=>'badge-success']; @endphp<span class="badge {{ $sc[$payout->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$payout->status)) }}</span></div></div>
            <div><div style="font-size:.6rem;color:#64748b;font-weight:600;text-transform:uppercase;">Shipped Qty</div><div style="font-weight:700;color:#1e40af;">{{ number_format($payoutSummary['total_qty'] ?? 0) }}</div></div>
        </div>
    </div>
</div>

{{-- KPIs --}}
<div style="display:flex;gap:.75rem;margin-bottom:1.25rem;">
    <div class="kpi-card" style="flex:1;border-left:3px solid #16a34a;"><div class="kpi-label">Sales (WSP x Qty)</div><div class="kpi-value" style="color:#16a34a;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_sales'] ?? 0, 2) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #e8a838;"><div class="kpi-label">Commission</div><div class="kpi-value" style="color:#e8a838;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_commission'] ?? 0, 2) }}</div></div>
    <div class="kpi-card" style="flex:1;border-left:3px solid #7c3aed;"><div class="kpi-label">Net Payout</div><div class="kpi-value" style="color:{{ ($payoutSummary['net_payout'] ?? 0) >= 0 ? '#7c3aed' : '#dc2626' }};">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['net_payout'] ?? 0, 2) }}</div></div>
</div>

{{-- SKU-Level Breakdown --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><h3><i class="fas fa-list-alt" style="margin-right:.5rem;color:#1e3a5f;"></i> SKU-Level Breakdown</h3><span style="font-size:.78rem;color:#64748b;">{{ $lineItems->count() }} items</span></div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table" style="font-size:.78rem;">
            <thead><tr style="background:#f0f4f8;">
                <th style="width:35px;">S.No</th>
                <th>Order #</th>
                <th>SKU</th>
                <th>Channel</th>
                <th style="text-align:right;">Vendor WSP</th>
                <th style="text-align:center;">Shipped Qty</th>
                <th style="text-align:right;">Sale Amount</th>
                <th style="text-align:right;">Commission</th>
                <th style="text-align:right;background:#f5f3ff;">Gross Payout</th>
            </tr></thead>
            <tbody>
                @forelse($lineItems as $idx => $li)
                <tr>
                    <td style="text-align:center;color:#94a3b8;">{{ $idx + 1 }}</td>
                    <td style="font-family:monospace;font-size:.72rem;">{{ $li->order_number ?? '—' }}</td>
                    <td style="font-family:monospace;font-weight:600;">{{ $li->sku }}</td>
                    <td style="font-size:.72rem;">{{ $li->channel }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($li->vendor_wsp, 2) }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $li->qty }}</td>
                    <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($li->sale_amount, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;color:#e8a838;">{{ $activeCurrencySymbol }}{{ number_format($li->commission, 2) }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:700;background:#f5f3ff;color:#7c3aed;">{{ $activeCurrencySymbol }}{{ number_format($li->net_payout, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8;">No shipped items found for this period.</td></tr>
                @endforelse
            </tbody>
            @if($lineItems->isNotEmpty())
            <tfoot><tr style="background:#f0f4f8;font-weight:700;">
                <td colspan="4"></td>
                <td style="text-align:right;">TOTAL</td>
                <td style="text-align:center;">{{ number_format($payoutSummary['total_qty'] ?? 0) }}</td>
                <td style="text-align:right;font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_sales'] ?? 0, 2) }}</td>
                <td style="text-align:right;font-family:monospace;color:#e8a838;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_commission'] ?? 0, 2) }}</td>
                <td style="text-align:right;font-family:monospace;color:#7c3aed;background:#f5f3ff;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_payout'] ?? 0, 2) }}</td>
            </tr></tfoot>
            @endif
        </table>
    </div>
</div>

{{-- Deductions --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
    {{-- Warehouse Charges --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-warehouse" style="margin-right:.5rem;color:#e8a838;"></i> Warehouse Charges</h3></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table" style="font-size:.78rem;">
                <thead><tr style="background:#f0f4f8;"><th>Warehouse</th><th style="text-align:right;">Amount</th></tr></thead>
                <tbody>
                    @forelse($warehouseCharges as $wc)
                    <tr>
                        <td>{{ $wc->warehouse->name ?? '—' }}</td>
                        <td style="text-align:right;font-family:monospace;font-weight:600;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format(floatval($wc->total_charges ?? $wc->total_charge ?? 0), 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" style="text-align:center;color:#94a3b8;padding:1rem;">No warehouse charges</td></tr>
                    @endforelse
                </tbody>
                @if($warehouseCharges->isNotEmpty())
                <tfoot><tr style="background:#f0f4f8;font-weight:700;"><td style="text-align:right;">Total</td><td style="text-align:right;font-family:monospace;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($warehouseCharges->sum(fn($c) => floatval($c->total_charges ?? $c->total_charge ?? 0)), 2) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Chargebacks --}}
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="margin-right:.5rem;color:#dc2626;"></i> Chargebacks</h3></div>
        <div class="card-body" style="padding:0;">
            <table class="data-table" style="font-size:.78rem;">
                <thead><tr style="background:#f0f4f8;"><th>Order</th><th>Reason</th><th style="text-align:right;">Amount</th></tr></thead>
                <tbody>
                    @forelse($chargebacks as $cb)
                    <tr>
                        <td style="font-family:monospace;font-size:.72rem;">{{ $cb->order->order_number ?? '—' }}</td>
                        <td style="font-size:.72rem;">{{ Str::limit($cb->reason ?? '—', 30) }}</td>
                        <td style="text-align:right;font-family:monospace;font-weight:600;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format(floatval($cb->amount), 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:1rem;">No chargebacks</td></tr>
                    @endforelse
                </tbody>
                @if($chargebacks->isNotEmpty())
                <tfoot><tr style="background:#f0f4f8;font-weight:700;"><td colspan="2" style="text-align:right;">Total</td><td style="text-align:right;font-family:monospace;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($chargebacks->sum('amount'), 2) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

{{-- Final Summary --}}
@php

    $warehouseAdjustments = \App\Models\PayoutWarehouseAdjustment::where('vendor_payout_id', $payout->id)
      ->orderByDesc('adjustment_date')
      ->with('creator')
      ->get();

    $totalWarehouseAdjustment = $warehouseAdjustments->sum('amount');

    $totalWhCharges = $warehouseCharges->sum(fn($c) => floatval($c->total_charges ?? $c->total_charge ?? 0));
    $totalChargebacks = $chargebacks->sum('amount');
    $grossPayout = $payoutSummary['total_payout'] ?? 0;
    $netPayout = round($grossPayout - $totalWhCharges - $totalChargebacks + $totalWarehouseAdjustment, 2);

@endphp
<div class="card">
    <div class="card-header"><h3><i class="fas fa-calculator" style="margin-right:.5rem;color:#7c3aed;"></i> Final Payout Summary</h3></div>
    <div class="card-body" style="padding:1rem 1.4rem;">
        <table style="width:100%;max-width:500px;font-size:.85rem;">
            <tr><td style="padding:.4rem 0;">Total Sales (Vendor WSP x Shipped Qty)</td><td style="text-align:right;font-family:monospace;font-weight:600;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_sales'] ?? 0, 2) }}</td></tr>
            <tr><td style="padding:.4rem 0;color:#e8a838;">— EB Commission</td><td style="text-align:right;font-family:monospace;color:#e8a838;">-{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_commission'] ?? 0, 2) }}</td></tr>
            <tr style="border-top:1px solid #e2e8f0;"><td style="padding:.4rem 0;font-weight:600;">Gross Payout</td><td style="text-align:right;font-family:monospace;font-weight:600;">{{ $activeCurrencySymbol }}{{ number_format($grossPayout, 2) }}</td></tr>
            <tr><td style="padding:.4rem 0;color:#dc2626;">— Warehouse Charges</td><td style="text-align:right;font-family:monospace;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($totalWhCharges, 2) }}</td></tr>
            <tr><td style="padding:.4rem 0;color:#dc2626;">— Chargebacks</td><td style="text-align:right;font-family:monospace;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($totalChargebacks, 2) }}</td></tr>
            <tr><td style="padding:.4rem 0;color:#dc2626;">— Warehouse Adjustments</td><td style="text-align:right;font-family:monospace;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($totalWarehouseAdjustment, 2) }}</td></tr>
            <tr style="border-top:2px solid #1e3a5f;"><td style="padding:.6rem 0;font-weight:800;font-size:1rem;">NET PAYOUT</td><td style="text-align:right;font-family:monospace;font-weight:800;font-size:1.1rem;color:{{ $netPayout >= 0 ? '#7c3aed' : '#dc2626' }};">{{ $activeCurrencySymbol }}{{ number_format($netPayout, 2) }}</td></tr>
        </table>
    </div>
</div>
@endsection
