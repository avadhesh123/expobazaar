@extends('layouts.app')
@section('title', 'Payouts')
@section('page-title', 'Monthly Payouts')

@section('content')


{{-- Warehouse Charges Summary --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-warehouse" style="margin-right:.5rem;color:#e8a838;"></i>Warehouse Charges</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>GRN</th>
                    <th>Inward</th>
                    <th>Storage</th>
                    <th>Fulfillment</th>
                    <th>Pick & Pack</th>
                    <th>Material</th>
                    <th>Total</th>
                    <th>Status</th>
                 </tr>
            </thead>
            <tbody>
                @forelse($vendorMonthlyCharges as $c)
                 <tr>
                    <td>
                        <div style="font-weight:600;font-size:.82rem;">{{ $c->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.6rem;color:#94a3b8;">{{ $c->vendor->vendor_code ?? '' }}</div>
                    </td>
                    <td style="font-family:monospace;font-size:.78rem;">{{ $c->grn->grn_number ?? '—' }}</td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($c->inward_charge), 2) }}
                        <div style="font-size:.58rem;color:#94a3b8;">{{ $c->inward_cartons }} cartons</div>
                    </td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($c->storage_charge), 2) }}
                        <div style="font-size:.58rem;color:#94a3b8;">{{ number_format(floatval($c->storage_cft), 1) }} CFT</div>
                    </td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($c->fulfillment_charge), 2) }}
                        <div style="font-size:.58rem;color:#94a3b8;">{{ $c->fulfillment_orders_small }}s + {{ $c->fulfillment_orders_large }}l</div>
                    </td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($c->pick_pack_charge), 2) }}
                        <div style="font-size:.58rem;color:#94a3b8;">{{ $c->pick_pack_units }} units</div>
                    </td>
                    <td style="font-family:monospace;">{{ $activeCurrencySymbol }}{{ number_format(floatval($c->material_cost), 2) }}</td>
                    <td style="font-family:monospace;font-weight:800;color:#dc2626;">{{ $activeCurrencySymbol }}{{ number_format(floatval($c->total_charges), 2) }}</td>
                    <td>
                        @php $sc = ['calculated'=>'badge-warning','approved'=>'badge-success','deducted'=>'badge-info','disputed'=>'badge-danger']; @endphp
                        <span class="badge {{ $sc[$c->status] ?? 'badge-gray' }}">{{ ucfirst($c->status) }}</span>
                    </td>

                </tr>
                @empty
                <tr>
                    <td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;">No charges for this period. Click "Run Charges" to calculate.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Payouts --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-money-check-alt" style="margin-right:.5rem;color:#2d6a4f;"></i> Payout History</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Sales</th>
                    <th>Storage</th>
                    <th>Inward</th>
                    <th>Logistics</th>
                    <th>Platform Ded.</th>
                    <th>Chargebacks</th>
                    <th>Gross Payout</th>
                    <th>Status</th>
                    <th>Invoice</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payouts as $p)
                @php $totalDed = $p->total_storage_charges + $p->total_inward_charges + $p->total_logistics_charges + $p->total_platform_deductions + $p->total_chargebacks; @endphp
                <tr>
                    <td style="font-weight:700;">{{ date('M',mktime(0,0,0,$p->payout_month,1)) }} {{ $p->payout_year }}</td>
                    <td style="font-family:monospace;color:#166534;font-weight:600;">{{ $activeCurrencySymbol }}{{ number_format($p->total_sales, 2) }}</td>
                    <td style="font-family:monospace;font-size:.78rem;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($p->total_storage_charges, 2) }}</td>
                    <td style="font-family:monospace;font-size:.78rem;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($p->total_inward_charges, 2) }}</td>
                    <td style="font-family:monospace;font-size:.78rem;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($p->total_logistics_charges, 2) }}</td>
                    <td style="font-family:monospace;font-size:.78rem;color:#dc2626;">-{{ $activeCurrencySymbol }}{{ number_format($p->total_platform_deductions, 2) }}</td>
                    <td style="font-family:monospace;font-size:.78rem;color:#991b1b;">-{{ $activeCurrencySymbol }}{{ number_format($p->total_chargebacks, 2) }}</td>
                    <td style="font-family:monospace;font-weight:800;color:{{ $p->net_payout>=0?'#166534':'#dc2626' }};">{{ $activeCurrencySymbol }}{{ number_format($p->net_payout, 2) }}</td>
                    <td>
                        @php $sc = ['calculated'=>'badge-warning','approved'=>'badge-info','payment_pending'=>'badge-warning','paid'=>'badge-success','invoice_received'=>'badge-success']; @endphp
                        <span class="badge {{ $sc[$p->status]??'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$p->status)) }}</span>
                        @if($p->payment_date)<div style="font-size:.62rem;color:#94a3b8;">{{ $p->payment_date->format('d M Y') }}</div>@endif
                    </td>
                    <td>
                        @if($p->vendor_invoice_file)<a href="{{ \App\Helpers\FileStorage::url($p->vendor_invoice_file) }}" target="_blank" style="font-size:.72rem;color:#166534;"><i class="fas fa-file-pdf"></i> {{ $p->vendor_invoice_number }}</a>
                        @elseif($p->status === 'paid')<span style="font-size:.72rem;color:#e8a838;"><i class="fas fa-clock"></i> Upload Required</span>
                        @else<span style="color:#94a3b8;font-size:.72rem;">—</span>@endif
                    </td>
                    <td>
                        @if($p->status === 'paid' && !$p->vendor_invoice_file)
                        <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('inv{{ $p->id }}').style.display=document.getElementById('inv{{ $p->id }}').style.display==='none'?'table-row':'none'"><i class="fas fa-upload"></i> Invoice</button>
                        @endif
                        @if($p->payment_advice_file ?? false)
                        <a href="{{ \App\Helpers\FileStorage::url($p->payment_advice_file) }}" class="btn btn-outline btn-sm" target="_blank"><i class="fas fa-file-download"></i></a>
                        @endif
                    </td>
                </tr>
                {{-- Invoice Upload Row --}}
                @if($p->status === 'paid' && !$p->vendor_invoice_file)
                <tr id="inv{{ $p->id }}" style="display:none;background:#eff6ff;">
                    <td colspan="11" style="padding:.75rem;">
                        <form method="POST" action="{{ route('vendor.payouts.invoice', $p) }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;">@csrf
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Invoice Number *</label><input type="text" name="vendor_invoice_number" required placeholder="INV-001" style="width:130px;padding:.3rem .5rem;border:1px solid #bfdbfe;border-radius:6px;font-size:.82rem;"></div>
                            <div><label style="font-size:.65rem;font-weight:600;color:#1e40af;">Invoice PDF *</label><input type="file" name="invoice" required accept=".pdf" style="font-size:.78rem;"></div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> Upload</button>
                        </form>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="11" style="text-align:center;padding:3rem;color:#94a3b8;"><i class="fas fa-money-check-alt" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>No payouts yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($payouts->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $payouts->links('pagination::tailwind') }}</div>@endif
</div>
@endsection