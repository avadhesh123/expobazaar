@extends('layouts.app')
@section('title', 'Rate Cards')
@section('page-title', 'Warehouse Rate Card Management')

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;">
    <a href="{{ route('logistics.warehouse-charges') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Warehouse Charges</a>
    <a href="{{ route('logistics.warehouse-charges.vendor-allocation') }}" class="btn btn-outline btn-sm"><i class="fas fa-users"></i> Vendor Allocation</a>
</div>

{{-- Info --}}
<div style="padding:.75rem 1.2rem;background:#fefce8;border-radius:10px;border:1px solid #fde68a;margin-bottom:1.25rem;font-size:.82rem;color:#854d0e;display:flex;align-items:center;gap:.5rem;">
    <i class="fas fa-info-circle"></i>
    <span>Rate cards are country-specific. Inward & Storage rates are per CBM. Pick & Pack, Consumable, and Last Mile rates are per unit sold. Changes apply to future charge calculations only.</span>
</div>

{{-- Rate Cards by Warehouse --}}
@foreach($warehouses->groupBy('company_code') as $companyCode => $whGroup)
@php
    $cc = ['2000'=>['🇮🇳','India','#dcfce7','#166534'],'2100'=>['🇺🇸','USA','#dbeafe','#1e40af'],'2200'=>['🇳🇱','Netherlands','#fef3c7','#92400e']];
    $flag = $cc[$companyCode][0] ?? '';
    $country = $cc[$companyCode][1] ?? '';
    $bg = $cc[$companyCode][2] ?? '#f1f5f9';
    $color = $cc[$companyCode][3] ?? '#475569';
@endphp
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header" style="background:{{ $bg }};">
        <h3 style="color:{{ $color }};"><span style="font-size:1.1rem;margin-right:.3rem;">{{ $flag }}</span> {{ $country }} ({{ $companyCode }}) — {{ $whGroup->count() }} Warehouse(s)</h3>
    </div>
    <div class="card-body" style="padding:0;">
        @foreach($whGroup as $wh)
        <div style="padding:1.25rem 1.4rem;{{ !$loop->last?'border-bottom:1px solid #e8ecf1;':'' }}">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
                <div>
                    <div style="font-size:.95rem;font-weight:700;color:#0d1b2a;">{{ $wh->name }}</div>
                    <div style="font-size:.72rem;color:#94a3b8;font-family:monospace;">{{ $wh->code }} &middot; {{ $wh->city }}, {{ $wh->country }}</div>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('edit{{ $wh->id }}').style.display=document.getElementById('edit{{ $wh->id }}').style.display==='none'?'block':'none'">
                    <i class="fas fa-edit"></i> Edit Rates
                </button>
            </div>

            {{-- Current Rates Display --}}
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem;">
                <div style="padding:.65rem;background:#f8fafc;border-radius:8px;text-align:center;border-top:3px solid #1e40af;">
                    <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Inward / CBM</div>
                    <div style="font-size:1.1rem;font-weight:800;color:#1e40af;font-family:monospace;">${{ number_format($wh->inward_rate_per_cbm, 2) }}</div>
                </div>
                <div style="padding:.65rem;background:#f8fafc;border-radius:8px;text-align:center;border-top:3px solid #e8a838;">
                    <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Storage / CBM / Month</div>
                    <div style="font-size:1.1rem;font-weight:800;color:#e8a838;font-family:monospace;">${{ number_format($wh->storage_rate_per_cbm_month, 2) }}</div>
                </div>
                <div style="padding:.65rem;background:#f8fafc;border-radius:8px;text-align:center;border-top:3px solid #2d6a4f;">
                    <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Pick & Pack / Unit</div>
                    <div style="font-size:1.1rem;font-weight:800;color:#2d6a4f;font-family:monospace;">${{ number_format($wh->pick_pack_rate, 2) }}</div>
                </div>
                <div style="padding:.65rem;background:#f8fafc;border-radius:8px;text-align:center;border-top:3px solid #7c3aed;">
                    <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Consumable / Unit</div>
                    <div style="font-size:1.1rem;font-weight:800;color:#7c3aed;font-family:monospace;">${{ number_format($wh->consumable_rate, 2) }}</div>
                </div>
                <div style="padding:.65rem;background:#f8fafc;border-radius:8px;text-align:center;border-top:3px solid #dc2626;">
                    <div style="font-size:.62rem;color:#64748b;text-transform:uppercase;font-weight:600;">Last Mile / Unit</div>
                    <div style="font-size:1.1rem;font-weight:800;color:#dc2626;font-family:monospace;">${{ number_format($wh->last_mile_rate, 2) }}</div>
                </div>
            </div>

            {{-- Edit Form (hidden) --}}
            <div id="edit{{ $wh->id }}" style="display:none;margin-top:1rem;padding:1rem;background:#fffbeb;border-radius:10px;border:1px solid #fde68a;">
                <form method="POST" action="{{ route('logistics.rate-cards.update', $wh) }}" onsubmit="return confirm('Update rate card for {{ $wh->name }}? Future charge calculations will use these new rates.')">
                    @csrf
                    @method('PUT')
                    <div style="font-size:.85rem;font-weight:700;color:#0d1b2a;margin-bottom:.75rem;">Update Rate Card — {{ $wh->name }}</div>
                    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.75rem;">
                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label style="font-size:.68rem;">Inward / CBM ($)</label>
                            <input type="number" step="0.01" min="0" name="inward_rate_per_cbm" value="{{ $wh->inward_rate_per_cbm }}" required style="font-family:monospace;text-align:center;">
                        </div>
                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label style="font-size:.68rem;">Storage / CBM / Month ($)</label>
                            <input type="number" step="0.01" min="0" name="storage_rate_per_cbm_month" value="{{ $wh->storage_rate_per_cbm_month }}" required style="font-family:monospace;text-align:center;">
                        </div>
                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label style="font-size:.68rem;">Pick & Pack / Unit ($)</label>
                            <input type="number" step="0.01" min="0" name="pick_pack_rate" value="{{ $wh->pick_pack_rate }}" required style="font-family:monospace;text-align:center;">
                        </div>
                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label style="font-size:.68rem;">Consumable / Unit ($)</label>
                            <input type="number" step="0.01" min="0" name="consumable_rate" value="{{ $wh->consumable_rate }}" required style="font-family:monospace;text-align:center;">
                        </div>
                        <div class="form-group" style="margin-bottom:.5rem;">
                            <label style="font-size:.68rem;">Last Mile / Unit ($)</label>
                            <input type="number" step="0.01" min="0" name="last_mile_rate" value="{{ $wh->last_mile_rate }}" required style="font-family:monospace;text-align:center;">
                        </div>
                    </div>
                    <div style="display:flex;gap:.4rem;">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Rate Card</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('edit{{ $wh->id }}').style.display='none'">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endforeach

{{-- Charge Rules Reference --}}
<div class="card">
    <div class="card-header"><h3><i class="fas fa-book" style="margin-right:.5rem;color:#7c3aed;"></i> Charge Calculation Rules</h3></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div style="padding:.85rem;background:#eff6ff;border-radius:10px;border-left:3px solid #1e40af;">
                <div style="font-size:.78rem;font-weight:700;color:#1e40af;margin-bottom:.3rem;">CBM-Based Charges (Inventory)</div>
                <div style="font-size:.78rem;color:#334155;line-height:1.7;">
                    <strong>Inward Handling:</strong> Total CBM in inventory × Inward Rate per CBM<br>
                    <strong>Storage:</strong> Total CBM in inventory × Storage Rate per CBM per Month
                </div>
            </div>
            <div style="padding:.85rem;background:#f0fdf4;border-radius:10px;border-left:3px solid #2d6a4f;">
                <div style="font-size:.78rem;font-weight:700;color:#2d6a4f;margin-bottom:.3rem;">Sales-Based Charges (From Sale Sheet)</div>
                <div style="font-size:.78rem;color:#334155;line-height:1.7;">
                    <strong>Pick & Pack:</strong> Units sold that month × Pick & Pack Rate per Unit<br>
                    <strong>Consumable:</strong> Units sold that month × Consumable Rate per Unit<br>
                    <strong>Last Mile:</strong> Units sold that month × Last Mile Rate per Unit
                </div>
            </div>
        </div>
        <div style="margin-top:.75rem;padding:.5rem .75rem;background:#fef2f2;border-radius:6px;font-size:.75rem;color:#991b1b;">
            <i class="fas fa-exclamation-triangle" style="margin-right:.2rem;"></i> Calculated charges are compared with actual warehouse receipts uploaded monthly. Any variance must have a comment/explanation from the Logistics team.
        </div>
    </div>
</div>
@endsection
