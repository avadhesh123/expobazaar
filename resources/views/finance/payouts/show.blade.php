@extends('layouts.app')
@section('title', 'Payout Detail')
@section('page-title', 'Vendor Payout — ' . ($payout->vendor->company_name ?? 'Vendor'))

@section('content')
@php
//$currency = match($payout->company_code) { '2000' => '₹', '2200' => '€', default => '$' };
$monthName = \Carbon\Carbon::create($payout->payout_year, $payout->payout_month)->format('F Y');

$payments = \App\Models\PayoutPayment::where('vendor_payout_id', $payout->id)
    ->orderByDesc('payment_date')
    ->with('creator')
    ->get();
$totalPaid = $payments->sum('amount');
$balanceDue = max(0, round(floatval($payout->net_payout) - $totalPaid, 2));
$isFullyPaid = $balanceDue <= 0.01 && $totalPaid > 0;
@endphp
<div class="card" style="margin-bottom:1.25rem;">

    <div class="card-body" style="padding:1rem 1.4rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;font-size:.82rem;">
            <div>
                <div style="font-size:.65rem;color:#64748b;font-weight:600;text-transform:uppercase;">Vendor</div>
                <div style="font-weight:700;">{{ $payout->vendor->company_name ?? '—' }} ({{ $payout->company_code }})</div>
               </div>
            <div>
                <div style="font-size:.65rem;color:#64748b;font-weight:600;text-transform:uppercase;">Period</div>
                <div style="font-weight:600;">{{ $monthName }} {{$totalPaid}} == {{$payout->net_payout}}</div>
            </div>
             
            <div>
                <div style="font-size:.65rem;color:#64748b;font-weight:600;text-transform:uppercase;">Status</div>
                <div>@php $sc = ['draft'=>'badge-gray','calculated'=>'badge-warning','approved'=>'badge-success','paid'=>'badge-info']; @endphp<span class="badge {{ $sc[$payout->status] ?? 'badge-gray' }}">{{ ucfirst($payout->status) }}</span></div>
            </div>
            <div>
                <a href="{{ route('finance.payouts.download', $payout) }}" class="btn btn-outline btn-sm">
                    <i class="fas fa-download" style="margin-right:.2rem;"></i> Download Payout
                </a>
            </div>

            <div>

                <a href="{{ route('finance.payouts.show', $payout) }}?recalculate=1" class="btn btn-outline btn-sm" onclick="return confirm('Recalculate with latest shipped data?')">
                    <i class="fas fa-sync"></i> Recalculate Live
                </a>
                @if($calculatedAt)
                <span style="font-size:.65rem;color:#94a3b8;">Snapshot from {{ \Carbon\Carbon::parse($calculatedAt)->format('d M Y H:i') }}</span>
                @endif
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
        <div class="kpi-label">Gross Payout</div>
        <div class="kpi-value" style="color:#7c3aed;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_payout'], 2) }}</div>
    </div>
</div>
<div class="card" style="margin-bottom:1.25rem;">

{{-- Payout Header --}}
     <div class="card-header">
        <h3><i class="fas fa-money-check-alt" style="margin-right:.5rem;color:#16a34a;"></i> Payment Tracking</h3>
        <div style="display:flex;align-items:center;gap:.75rem;">
            <span style="font-size:.78rem;">
                Paid: <strong style="color:#16a34a;">{{ $activeCurrencySymbol }}{{ number_format($totalPaid, 2) }}</strong>
                &middot;
                Balance: <strong style="color:{{ $isFullyPaid ? '#16a34a' : '#dc2626' }};">{{ $activeCurrencySymbol }}{{ number_format($balanceDue, 2) }}</strong>
            </span>
            @if(!$isFullyPaid)
            <button type="button" class="btn btn-primary btn-sm" onclick="togglePaymentForm()">
    <i class="fas fa-plus" style="margin-right:.2rem;"></i> Record Payment
        </button>
            @else
            <span class="badge badge-success" style="font-size:.72rem;">Fully Paid</span>
            @endif
        </div>
    </div>
    <script>
        function togglePaymentForm() {
            const form = document.getElementById('paymentForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
  {{-- Payment Form --}}
    <div id="paymentForm" style="display:none;padding:1rem 1.4rem;background:#f0fdf4;border-bottom:1px solid #bbf7d0;">
        <form method="POST" action="{{ route('finance.payouts.payment', $payout) }}">
            @csrf
            <div style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;">
                <div>
                    <label style="font-size:.65rem;font-weight:600;color:#166534;display:block;margin-bottom:.2rem;">Amount <span style="color:#dc2626;">*</span>(<span style="font-size:.55rem;color:#64748b;margin-top:.15rem;">Max: {{ $activeCurrencySymbol }}{{ number_format($balanceDue, 2) }}</span>)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" max="{{ $balanceDue }}" value="{{ $balanceDue }}" required
                        placeholder="{{ number_format($balanceDue, 2) }}"
                        style="width:110px;padding:.4rem .5rem;border:1.5px solid #bbf7d0;border-radius:6px;font-size:.85rem;font-family:monospace;text-align:center;">
                </div>
                <div>
                    <label style="font-size:.65rem;font-weight:600;color:#166534;display:block;margin-bottom:.2rem;">Payment Date <span style="color:#dc2626;">*</span></label>
                    <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}"
                        style="padding:.4rem .5rem;border:1.5px solid #bbf7d0;border-radius:6px;font-size:.82rem;">
                </div>
                <div>
                    <label style="font-size:.65rem;font-weight:600;color:#166534;display:block;margin-bottom:.2rem;">Payment Mode</label>
                    <select name="payment_mode" style="padding:.4rem .5rem;border:1.5px solid #bbf7d0;border-radius:6px;font-size:.82rem;">
                        <option value="">Select...</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="upi">UPI</option>
                        <option value="cash">Cash</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:.65rem;font-weight:600;color:#166534;display:block;margin-bottom:.2rem;">Reference #</label>
                    <input type="text" name="reference_number" placeholder="Txn ID, Cheque #..."
                        style="width:130px;padding:.4rem .5rem;border:1.5px solid #bbf7d0;border-radius:6px;font-size:.82rem;">
                </div>
                <div style="flex:1;min-width:100px;">
                    <label style="font-size:.65rem;font-weight:600;color:#166534;display:block;margin-bottom:.2rem;">Remarks</label>
                    <input type="text" name="remarks" placeholder="e.g. Tranche 1, partial payment..."
                        style="width:100%;padding:.4rem .5rem;border:1.5px solid #bbf7d0;border-radius:6px;font-size:.82rem;">
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="padding:.45rem .8rem;"
                    onclick="return confirm('Record this payment?')">
                    <i class="fas fa-save" style="margin-right:.2rem;"></i> Save Payment
                </button>
                <button type="button" class="btn btn-outline btn-sm"
                    onclick="document.getElementById('paymentForm').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>

    {{-- Progress Bar --}}
    @php $pctPaid = $payout->net_payout > 0 ? min(100, round(($totalPaid / $payout->net_payout) * 100, 1)) : 0; @endphp
    <div style="padding:.5rem 1.4rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
        <div style="display:flex;justify-content:space-between;font-size:.68rem;color:#64748b;margin-bottom:.25rem;">
            <span>{{ $pctPaid }}% paid</span>
            <span>{{ $activeCurrencySymbol }}{{ number_format($totalPaid, 2) }} / {{ $activeCurrencySymbol }}{{ number_format($payout->net_payout, 2) }}</span>
        </div>
        <div style="height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden;">
            <div style="height:100%;width:{{ $pctPaid }}%;background:{{ $isFullyPaid ? '#16a34a' : '#e8a838' }};border-radius:3px;transition:width .3s;"></div>
        </div>
    </div>

    {{-- Payment History --}}
    <div class="card-body" style="padding:0;">
        @if($payments->isNotEmpty())
        <table class="data-table" style="font-size:.78rem;margin:0;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="width:30px;">#</th>
                    <th>Date</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Mode</th>
                    <th>Reference</th>
                    <th>Remarks</th>
                    <th>Recorded By</th>
                    <th style="text-align:right;">Running Total</th>
                    <th style="width:40px;"></th>
                </tr>
            </thead>
            <tbody>
                @php $runningTotal = 0; @endphp
                @foreach($payments->sortBy('payment_date')->values() as $idx => $payment)
                @php $runningTotal += floatval($payment->amount); @endphp
                <tr>
                    <td style="text-align:center;color:#94a3b8;">{{ $idx + 1 }}</td>
                    <td style="font-family:monospace;font-size:.75rem;">{{ $payment->payment_date->format('d M Y') }}</td>
                    <td style="text-align:right;font-family:monospace;font-weight:700;color:#16a34a;">{{ $activeCurrencySymbol }}{{ number_format($payment->amount, 2) }}</td>
                    <td>
                        @php
                            $modeLabels = ['bank_transfer'=>'Bank Transfer','cheque'=>'Cheque','upi'=>'UPI','cash'=>'Cash','other'=>'Other'];
                            $modeColors = ['bank_transfer'=>'#1e40af','cheque'=>'#7c3aed','upi'=>'#16a34a','cash'=>'#e8a838','other'=>'#64748b'];
                        @endphp
                        @if($payment->payment_mode)
                        <span style="font-size:.65rem;padding:1px 6px;border-radius:4px;background:{{ $modeColors[$payment->payment_mode] ?? '#64748b' }}15;color:{{ $modeColors[$payment->payment_mode] ?? '#64748b' }};font-weight:600;">
                            {{ $modeLabels[$payment->payment_mode] ?? $payment->payment_mode }}
                        </span>
                        @else
                        <span style="color:#94a3b8;">—</span>
                        @endif
                    </td>
                    <td style="font-family:monospace;font-size:.72rem;">{{ $payment->reference_number ?? '—' }}</td>
                    <td style="font-size:.72rem;color:#64748b;">{{ $payment->remarks ?? '—' }}</td>
                    <td style="font-size:.72rem;">{{ $payment->creator->name ?? '—' }}</td>
                    <td style="text-align:right;font-family:monospace;font-size:.72rem;color:#64748b;">{{ $activeCurrencySymbol }}{{ number_format($runningTotal, 2) }}</td>
                    <td>
                        <form method="POST" action="{{ route('finance.payout-payment.delete', $payment) }}" onsubmit="return confirm('Delete this payment of {{ $activeCurrencySymbol }}{{ number_format($payment->amount, 2) }}?')" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:.7rem;padding:2px;" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:#f0fdf4;font-weight:700;">
                    <td colspan="2">Total Paid</td>
                    <td style="text-align:right;font-family:monospace;color:#16a34a;">{{ $activeCurrencySymbol }}{{ number_format($totalPaid, 2) }}</td>
                    <td colspan="4"></td>
                    <td style="text-align:right;font-family:monospace;color:{{ $isFullyPaid ? '#16a34a' : '#dc2626' }};">
                        Balance: {{ $activeCurrencySymbol }}{{ number_format($balanceDue, 2) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        @else
        <div style="padding:1.5rem;text-align:center;color:#94a3b8;font-size:.82rem;">
            <i class="fas fa-money-check-alt" style="font-size:1.5rem;display:block;margin-bottom:.3rem;"></i>
            No payments recorded yet.
            <div style="font-size:.72rem;margin-top:.3rem;">Click "+ Record Payment" to add the first tranche.</div>
        </div>
        @endif
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
                    <th style="text-align:right;background:#f5f3ff;">Gross Payout</th>
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
$totalPayout = $payoutSummary['total_payout'] ?? 0;
$totalWhCharges = $warehouseCharges->sum(fn($c) => floatval($c->total_charges ?? $c->amount ?? 0));

$totalChargebacks = $chargebacks->sum('amount');
$netPayout = round($totalPayout - $totalWhCharges - $totalChargebacks, 2);

$finalPayout = $payoutSummary['total_payout'] - $totalWhCharges - $totalChargebacks;

@endphp
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-calculator" style="margin-right:.5rem;color:#7c3aed;"></i> Final Payout Summary</h3>
    </div>
    <div class="card-body" style="padding:1rem 1.4rem;">

        <table style="width:100%;max-width:500px;font-size:.85rem;">
            <tr>
                <td style="padding:.4rem 0;">Total Sales (Vendor WSP x QTY)</td>
                <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_sales'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td style="padding:.4rem 0;color:#e8a838;">— EB Commission</td>
                <td style="text-align:right;font-family:monospace;color:#e8a838;">-{{ $activeCurrencySymbol }}{{ number_format($payoutSummary['total_commission'] ?? 0, 2) }}</td>
            </tr>
            <tr style="border-top:1px solid #e2e8f0;">
                <td style="padding:.4rem 0;font-weight:600;">Gross Payout</td>
                <td style="text-align:right;font-family:monospace;font-weight:600;">{{ $activeCurrencySymbol }}{{ number_format($totalPayout, 2) }}</td>
            </tr>
            <tr>
                <td style="padding:.4rem 0;color:#dc2626;">— Warehouse Charges</td>
                <td style="text-align:right;font-family:monospace;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($totalWhCharges, 2) }}</td>
            </tr>
            <tr>
                <td style="padding:.4rem 0;color:#dc2626;">— Chargebacks</td>
                <td style="text-align:right;font-family:monospace;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($totalChargebacks, 2) }}</td>
            </tr>
            <tr style="border-top:2px solid #1e3a5f;">
                <td style="padding:.6rem 0;font-weight:800;font-size:1rem;">NET PAYOUT</td>
                <td style="text-align:right;font-family:monospace;font-weight:800;font-size:1.1rem;color:{{ $netPayout >= 0 ? '#7c3aed' : '#dc2626' }};">{{ $activeCurrencySymbol }}{{ number_format($netPayout, 2) }}</td>
            </tr>
        </table>
    </div>

    
</div>

<div style="margin-top:1rem;display:flex;gap:.5rem;">
    <a href="{{ route('finance.payouts') }}" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Payouts</a>
</div>
@endsection