@extends('layouts.app')
@section('title', 'Warehouse Rate Cards')
@section('page-title', 'Warehouse Rate Cards')

@section('content')
<div style="padding:.6rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Set warehouse charges for <strong>Inward</strong>, <strong>Storage</strong>, <strong>Outward</strong>, and <strong>Other</strong> services per warehouse. Rates are used to calculate vendor warehouse charges.
</div>

@forelse($warehouses as $wh)
@php
    $rates = $wh->rate_card ?? [];
    if (is_string($rates)) $rates = json_decode($rates, true) ?? [];
    $sectionColors = [
        'inward'  => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'header' => '#166534', 'icon' => 'fas fa-arrow-down'],
        'storage' => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'header' => '#1e40af', 'icon' => 'fas fa-warehouse'],
        'outward' => ['bg' => '#fefce8', 'border' => '#fde68a', 'header' => '#854d0e', 'icon' => 'fas fa-arrow-up'],
        'others'  => ['bg' => '#faf5ff', 'border' => '#e9d5ff', 'header' => '#7c3aed', 'icon' => 'fas fa-cog'],
    ];
@endphp
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <h3><i class="fas fa-warehouse" style="margin-right:.5rem;color:#1e3a5f;"></i> {{ $wh->name }} <span style="font-weight:400;color:#64748b;font-size:.78rem;">· {{ $wh->city ?? '' }}, {{ $wh->country ?? '' }} · {{ $wh->company_code }}</span></h3>
    </div>
    <div class="card-body" style="padding:1rem 1.4rem;">
        <form method="POST" action="{{ route('logistics.rate-cards.update', $wh) }}">
            @csrf
            @method('PUT')

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                @foreach($rateStructure as $section => $items)
                @php $sc = $sectionColors[$section]; @endphp
                <div style="background:{{ $sc['bg'] }};border:1px solid {{ $sc['border'] }};border-radius:10px;padding:.85rem 1rem;">
                    <div style="font-size:.78rem;font-weight:700;color:{{ $sc['header'] }};text-transform:uppercase;margin-bottom:.6rem;">
                        <i class="{{ $sc['icon'] }}" style="margin-right:.3rem;"></i> {{ ucfirst($section) }}
                    </div>
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="font-size:.62rem;color:#64748b;text-align:left;padding:.2rem 0;font-weight:600;">Charge</th>
                                <th style="font-size:.62rem;color:#64748b;text-align:left;padding:.2rem 0;font-weight:600;">Type</th>
                                <th style="font-size:.62rem;color:#64748b;text-align:left;padding:.2rem 0;font-weight:600;">UOM</th>
                                <th style="font-size:.62rem;color:#64748b;text-align:right;padding:.2rem 0;font-weight:600;">Rate ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                            <tr style="border-bottom:1px solid {{ $sc['border'] }};">
                                <td style="font-size:.78rem;font-weight:600;color:#0d1b2a;padding:.4rem 0;">{{ $item['label'] }}</td>
                                <td style="font-size:.7rem;color:#64748b;">{{ $item['charge_type'] }}</td>
                                <td style="font-size:.7rem;color:#64748b;">{{ $item['uom'] }}</td>
                                <td style="text-align:right;padding:.3rem 0;">
                                    <input type="number" step="0.01" min="0" name="rates[{{ $item['key'] }}]"
                                        value="{{ $rates[$item['key']] ?? '' }}"
                                        placeholder="0.00"
                                        style="width:90px;padding:.25rem .4rem;border:1px solid {{ $sc['border'] }};border-radius:5px;font-size:.78rem;font-family:monospace;text-align:right;background:#fff;">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endforeach
            </div>

            <div style="margin-top:1rem;display:flex;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Update rate card for {{ $wh->name }}?')">
                    <i class="fas fa-save" style="margin-right:.3rem;"></i> Save Rate Card — {{ $wh->name }}
                </button>
            </div>
        </form>
    </div>
</div>
@empty
<div class="card">
    <div class="card-body" style="text-align:center;padding:3rem;color:#94a3b8;">
        <i class="fas fa-warehouse" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
        No active warehouses found. Create warehouses in Admin → Warehouses first.
    </div>
</div>
@endforelse
@endsection