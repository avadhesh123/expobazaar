@extends('layouts.app')
@section('title', 'Vendor Charges')
@section('page-title', 'Monthly Vendor Warehouse Charges')

@section('content')
<div class="grid-kpi" style="grid-template-columns:repeat(4,1fr);">
    <div class="kpi-card" style="border-left:3px solid #dc2626;">
        <div class="kpi-label">Total Charges</div>
        <div class="kpi-value" style="color:#dc2626;">{{$activeCurrencySymbol}}{{ number_format($stats['total_charges'], 2) }}</div>
        <div style="font-size:.62rem;color:#94a3b8;">{{ $stats['vendor_count'] }} vendors</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #1e40af;">
        <div class="kpi-label">Storage</div>
        <div class="kpi-value" style="color:#1e40af;">{{$activeCurrencySymbol}}{{ number_format($stats['total_storage'], 2) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #e8a838;">
        <div class="kpi-label">Fulfillment + P&P</div>
        <div class="kpi-value" style="color:#e8a838;">{{$activeCurrencySymbol}}{{ number_format($stats['total_fulfill'] + $stats['total_pickpack'], 2) }}</div>
    </div>
    <div class="kpi-card" style="border-left:3px solid #16a34a;">
        <div class="kpi-label">Pending Approval</div>
        <div class="kpi-value" style="color:#16a34a;">{{ $stats['pending_count'] }}</div>
    </div>
</div>

{{-- Filters + Run --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-body" style="padding:.85rem 1.4rem;">
        <form method="GET" action="{{ route('finance.vendor-charges') }}" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;">Month</label><select name="month" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $month==$m?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
            <div style="min-width:80px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;">Year</label><select name="year" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@for($y=date('Y');$y>=date('Y')-2;$y--)<option value="{{ $y }}" {{ $year==$y?'selected':'' }}>{{ $y }}</option>@endfor</select></div>
            <div style="min-width:140px;"><label style="font-size:.7rem;font-weight:600;color:#64748b;">Vendor</label><select name="vendor_id" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">
                    <option value="">All</option>@foreach($vendors as $v)<option value="{{ $v->id }}" {{ request('vendor_id')==(string)$v->id?'selected':'' }}>{{ $v->company_name }}</option>@endforeach
                </select></div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <a href="{{ route('finance.vendor-charges') }}" class="btn btn-outline btn-sm"><i class="fas fa-times"></i></a>
            <a href="{{ route('finance.vendor-charges.download', ['month'=>$month,'year'=>$year]) }}" class="btn btn-secondary btn-sm" style="margin-left:auto;"><i class="fas fa-download"></i> CSV</a>
            <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('runPanel').style.display=document.getElementById('runPanel').style.display==='none'?'block':'none'"><i class="fas fa-play"></i> Run Charges</button>
        </form>
    </div>
</div>

{{-- Run Panel --}}
<div id="runPanel" style="display:none;margin-bottom:1.25rem;">
    <div class="card" style="border-color:#16a34a;">
        <div class="card-body">
            <form method="POST" action="{{ route('finance.vendor-charges.run') }}" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;" onsubmit="return confirm('Calculate monthly vendor charges? This uses approved rate cards and current inventory/sales data.')">@csrf
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Month *</label><select name="charge_month" required style="padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $m==now()->month?'selected':'' }}>{{ date('M',mktime(0,0,0,$m,1)) }}</option>@endfor</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Year *</label><input type="number" name="charge_year" value="{{ date('Y') }}" required min="2024" style="width:80px;padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Vendor</label><select name="charge_vendor_id" style="padding:.4rem .5rem;border:1px solid #bbf7d0;border-radius:8px;font-size:.82rem;">
                        <option value="">All Vendors</option>@foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->company_name }}</option>@endforeach
                    </select></div>
                <button type="submit" class="btn btn-success"><i class="fas fa-play"></i> Run</button>
            </form>
            <div style="font-size:.7rem;color:#64748b;margin-top:.3rem;"><i class="fas fa-info-circle"></i> Calculates: Inward + Storage + Fulfillment + Pick&Pack + Material per vendor per GRN. Won't overwrite existing records.</div>
        </div>
    </div>
</div>

{{-- Charges Table --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-calculator" style="margin-right:.5rem;color:#1e3a5f;"></i> Vendor Charges — {{ date('M', mktime(0,0,0,$month,1)) }} {{ $year }}</h3>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($charges as $c)
                @php $sym = $c->getCurrencySymbol(); @endphp
                <tr style="{{ ($c->charge_status ?? 'active') === 'superseded' ? 'opacity:.5;background:#fef2f2;' : '' }}">

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
                    <td>
                        @if($c->status === 'calculated')
                        <form method="POST" action="{{ route('finance.vendor-charges.approve', $c) }}" style="display:inline;" onsubmit="return confirm('Approve and lock?')">@csrf<button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button></form>
                        @endif
                        <a href="{{ route('finance.vendor-charges.statement', [$c->vendor, 'month'=>$month, 'year'=>$year]) }}" class="btn btn-outline btn-sm" title="Statement"><i class="fas fa-file-alt"></i></a>
                        {{-- SUPERSEDE BUTTON (if active) --}}
                        @if(($c->charge_status ?? 'active') === 'active')
                        <button type="button" class="btn btn-outline btn-sm" style="color:#dc2626;border-color:#fecaca;font-size:.65rem;"
                            onclick="supersedeCharge({{ $c->id }})" title="Supersede — void this charge">
                            <i class="fas fa-ban"></i> Supersede
                        </button>
                        @endif

                        {{-- RESTORE BUTTON (if superseded) --}}
                        @if(($c->charge_status ?? 'active') === 'superseded')
                        <form method="POST" action="{{ route('finance.vendor-charges.restore', $c) }}" style="display:inline;"
                            onsubmit="return confirm('Restore this charge to active?')">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-sm" style="color:#16a34a;border-color:#bbf7d0;font-size:.65rem;" title="Restore to active">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                        </form>
                        @endif

                        {{-- STATUS BADGE — add to the Status column or next to amount --}}
                        @if(($c->charge_status ?? 'active') === 'superseded')
                        <span style="font-size:.6rem;padding:2px 6px;border-radius:4px;background:#fef2f2;color:#dc2626;font-weight:700;margin-left:.3rem;">
                            VOIDED
                        </span>
                        @endif
                        @if(($c->charge_status ?? 'active') === 'superseded')
                            <div style="font-size:.6rem;color:#dc2626;margin-top:.15rem;">
                                Voided by {{ $c->supersededBy->name ?? '—' }} on {{ $c->superseded_at ? \Carbon\Carbon::parse($c->superseded_at)->format('d M Y') : '—' }}
                                <br>Reason: {{ $c->supersede_reason ?? '—' }}
                            </div>
                        @endif

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
    @if($charges->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $charges->links('pagination::tailwind') }}</div>@endif
</div>


{{-- ═══════════════════════════════════════════ --}}
{{-- SUPERSEDE MODAL — add before @endsection   --}}
{{-- ═══════════════════════════════════════════ --}}

<div id="supersedeModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;width:460px;max-width:92%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #fecaca;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:.95rem;font-weight:700;color:#dc2626;margin:0;">
                <i class="fas fa-ban" style="margin-right:.3rem;"></i> Supersede Charge
            </h3>
            <button onclick="document.getElementById('supersedeModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:#94a3b8;">&times;</button>
        </div>
        <form method="POST" id="supersedeForm" action="">
            @csrf
            <div style="padding:1.25rem;">
                <div style="padding:.6rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;font-size:.78rem;color:#991b1b;margin-bottom:1rem;">
                    <i class="fas fa-exclamation-triangle" style="margin-right:.3rem;"></i>
                    This charge will be <strong>voided</strong> and will <strong>NOT</strong> be deducted from the vendor's payout.
                </div>
                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:.3rem;">Reason <span style="color:#dc2626;">*</span></label>
                    <textarea name="supersede_reason" required rows="3" placeholder="e.g. Incorrect calculation, charges already adjusted, waived by management..."
                        style="width:100%;padding:.5rem .65rem;border:1.5px solid #d1d5db;border-radius:8px;font-size:.85rem;font-family:inherit;resize:vertical;"></textarea>
                </div>
            </div>
            <div style="padding:.75rem 1.25rem;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:.4rem;">
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('supersedeModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-sm" style="background:#dc2626;color:#fff;border:none;padding:.4rem .8rem;border-radius:6px;font-weight:700;cursor:pointer;"
                    onclick="return confirm('Are you sure? This charge will be voided.')">
                    <i class="fas fa-ban" style="margin-right:.2rem;"></i> Confirm Supersede
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function supersedeCharge(chargeId) {
    document.getElementById('supersedeForm').action = '/finance/vendor-charges/' + chargeId + '/supersede';
    document.getElementById('supersedeModal').style.display = 'flex';
}

document.getElementById('supersedeModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

@endsection