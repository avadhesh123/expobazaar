@extends('layouts.app')
@section('title', 'Pricing Status')
@section('page-title', 'Pricing — ' . $asn->asn_number)

@section('content')
<div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <a href="{{ route('hod.asn-list') }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> ASN List</a>
    @if(in_array($asn->status, ['generated', 'locked', 'pricing_done']))
        <a href="{{ route('hod.pricing.prepare', $asn) }}" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> Edit Pricing</a>
    @endif
    <a href="{{ route('hod.pricing.download', $asn) }}" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Download Pricing CSV</a>
</div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-dollar-sign" style="margin-right:.5rem;color:#e8a838;"></i> {{ $asn->asn_number }} — Pricing Sheet</h3>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table class="data-table" style="margin:0;font-size:.75rem;">
                <thead>
                    <tr style="background:#f0f4f8;">
                        <th style="min-width:110px;">SKU</th>
                        <th style="min-width:80px;">SAP</th>
                        <th style="min-width:130px;">Vendor Name</th>
                        <th style="min-width:50px;text-align:center;">Qty</th>
                        <th style="min-width:60px;text-align:center;">FOB</th>
                        <th style="min-width:90px;text-align:center;background:#f0fdf4;border-right:2px solid #bbf7d0;">WSP</th>
                        @foreach($channels as $ch)
                         <th style="min-width:100px;text-align:center;font-size:.68rem;padding:.4rem .3rem;">
                            <div style="font-size:.65rem;color:#64748b;margin-bottom:.15rem;">{{ $ch->name }}</div>                            
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>                    
                  @foreach($existingPricing->groupBy('product_id') as $productId => $productPricings)
                            @php
                                $first = $productPricings->first();
                                $product = $first->product ?? null;

                            @endphp
                            <tr>
                                <td style="font-family:monospace; font-weight:600;">
                                    {{ $product?->sku ?? '—' }}
                                </td>
                                <td style="font-family:monospace; color:#64748b;">
                                    {{ $product?->sap_code ?? '—' }}
                                </td>
                                <td>{{ $product?->vendor?->company_name ?? '—' }}</td>
                                <td style="text-align:center; font-weight:600;">
                                    {{ $first->quantity ?? '—' }}
                                </td>
                                <td style="text-align:right;">${{ number_format($first->fob_price ?? 0, 2) }}</td>
                                <td style="text-align:right; font-weight:600;">
                                    ${{ number_format($first->wsp_price ?? 0, 2) }}
                                </td>

                                {{-- Dynamic Channel Prices --}}
                                @foreach($channels as $channel)
                                    @php
                                        $pricing = $productPricings->where('sales_channel_id', $channel->id)->first();
                                   //     print_r($pricing->toArray());
                                    @endphp
                                    <td style="text-align:right; font-family:monospace; font-weight:600;">
                                        @if($pricing)
                                            ${{ number_format($pricing->platform_price ?? 0, 2) }}
                                        @else
                                            <span style="color:#94a3b8;">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                </tbody>
            </table>
        </div>
        
    </div>
 
 
@endsection