@extends('layouts.app')
@section('title', 'Vendor Rate Cards')
@section('page-title', 'Vendor Rate Card Management')

@section('content')
<div style="padding:.6rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Create and manage vendor-specific warehouse rate cards. Rate cards must be <strong>approved</strong> before charges can be calculated. All rates in vendor's currency (2100=USD, 2200=EUR).
</div>

{{-- Create New Rate Card --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <h3><i class="fas fa-plus" style="margin-right:.5rem;color:#16a34a;"></i> Create Rate Card</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('finance.vendor-rate-cards.store') }}">@csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.75rem;">
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Vendor *</label><select name="vendor_id" required style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;">@foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->company_name }} ({{ $v->company_code }})</option>@endforeach</select></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Inward Rate / Carton *</label><input type="number" step="0.01" name="inward_rate_per_carton" required placeholder="0.00" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Storage Rate / CFT / Month *</label><input type="number" step="0.01" name="storage_rate_per_cft" required placeholder="0.00" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Fulfillment (≤ threshold) *</label><input type="number" step="0.01" name="fulfillment_rate_small" value="1.50" required style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Fulfillment (> threshold) *</label><input type="number" step="0.01" name="fulfillment_rate_large" value="2.50" required style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Threshold (units) *</label><input type="number" name="fulfillment_qty_threshold" value="3" required min="1" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Pick & Pack / Unit *</label><input type="number" step="0.01" name="pick_pack_rate_per_unit" value="0.50" required style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;font-family:monospace;"></div>
                <div><label style="font-size:.7rem;font-weight:600;color:#64748b;">Effective From *</label><input type="date" name="effective_from" required value="{{ date('Y-m-d') }}" style="width:100%;padding:.4rem .5rem;border:1px solid #d1d5db;border-radius:8px;font-size:.82rem;"></div>
            </div>
            <div style="margin-top:.75rem;display:flex;gap:.5rem;">
                <button type="submit" class="btn btn-success"><i class="fas fa-plus" style="margin-right:.3rem;"></i> Create Rate Card</button>
            </div>
        </form>
    </div>
</div>

{{-- Existing Rate Cards --}}
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-invoice-dollar" style="margin-right:.5rem;color:#1e3a5f;"></i> All Rate Cards</h3><span style="font-size:.78rem;color:#64748b;">{{ $rateCards->total() }} entries</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>Inward/Carton</th>
                    <th>Storage/CFT</th>
                    <th>Fulfill ≤{{ "threshold" }}</th>
                    <th>Fulfill > {{ "threshold" }}</th>
                    <th>Threshold</th>
                    <th>Pick&Pack/Unit</th>
                    <th>Effective</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rateCards as $rc)
                @php $sym = $rc->getCurrencySymbol(); @endphp
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:.82rem;">{{ $rc->vendor->company_name ?? '—' }}</div>
                        <div style="font-size:.62rem;color:#94a3b8;">{{ $rc->vendor->vendor_code ?? '' }} · v{{ $rc->version }}</div>
                    </td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($rc->inward_rate_per_carton), 2) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($rc->storage_rate_per_cft), 4) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($rc->fulfillment_rate_small), 2) }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($rc->fulfillment_rate_large), 2) }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $rc->fulfillment_qty_threshold }}</td>
                    <td style="font-family:monospace;">{{ $sym }}{{ number_format(floatval($rc->pick_pack_rate_per_unit), 2) }}</td>
                    <td style="font-size:.72rem;">{{ $rc->effective_from->format('d M Y') }} → {{ $rc->effective_to?->format('d M Y') ?? 'ongoing' }}</td>
                    <td>
                        @php $sc = ['draft'=>'badge-gray','pending_approval'=>'badge-warning','approved'=>'badge-success','expired'=>'badge-gray']; @endphp
                        <span class="badge {{ $sc[$rc->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$rc->status)) }}</span>
                        @if($rc->vendor_acknowledged)<div style="font-size:.58rem;color:#16a34a;">✓ Vendor acknowledged</div>@endif
                    </td>
                    <td>
                        <div style="display:flex;gap:.25rem;flex-wrap:wrap;">
                            @if($rc->status === 'draft')
                            <form method="POST" action="{{ route('finance.vendor-rate-cards.submit', $rc) }}" style="display:inline;">@csrf<button type="submit" class="btn btn-outline btn-sm" title="Submit for Approval"><i class="fas fa-paper-plane"></i></button></form>
                            @endif
                            @if($rc->status === 'pending_approval')
                            <form method="POST" action="{{ route('finance.vendor-rate-cards.approve', $rc) }}" style="display:inline;" onsubmit="return confirm('Approve this rate card?')">@csrf<button type="submit" class="btn btn-success btn-sm" title="Approve"><i class="fas fa-check"></i></button></form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" style="text-align:center;padding:3rem;color:#94a3b8;">No rate cards yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rateCards->hasPages())<div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $rateCards->links('pagination::tailwind') }}</div>@endif
</div>
@endsection