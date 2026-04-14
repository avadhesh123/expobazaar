@extends('layouts.app')
@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
    <div style="font-size:.85rem;color:#64748b;">{{ $notifications->count() }} unread notification(s)</div>
    @if($notifications->count() > 0)
    <form method="POST" action="{{ route('notifications.mark-all-read') }}">
        @csrf
        <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-check-double"></i> Mark All Read</button>
    </form>
    @endif
</div>

<div class="card">
    <div class="card-header"><h3><i class="fas fa-bell" style="margin-right:.5rem;color:#e8a838;"></i> Notifications</h3></div>
    <div class="card-body" style="padding:0;">
        @forelse($notifications as $notif)
        @php
            $data = $notif->data ?? [];
            $icon = match(true) {
                str_contains($data['type'] ?? '', 'vendor') || str_contains($data['type'] ?? '', 'kyc') => 'fa-user',
                str_contains($data['type'] ?? '', 'live_sheet') || str_contains($data['type'] ?? '', 'fill') => 'fa-clipboard-list',
                str_contains($data['type'] ?? '', 'consignment') || str_contains($data['type'] ?? '', 'planning') => 'fa-box',
                str_contains($data['type'] ?? '', 'shipment') || str_contains($data['type'] ?? '', 'sailing') => 'fa-ship',
                str_contains($data['type'] ?? '', 'grn') || str_contains($data['type'] ?? '', 'received') => 'fa-clipboard-check',
                str_contains($data['type'] ?? '', 'pricing') || str_contains($data['type'] ?? '', 'asn') => 'fa-tags',
                str_contains($data['type'] ?? '', 'chargeback') => 'fa-exclamation-triangle',
                str_contains($data['type'] ?? '', 'payout') || str_contains($data['type'] ?? '', 'payment') => 'fa-dollar-sign',
                default => 'fa-bell',
            };
            $color = match(true) {
                str_contains($data['type'] ?? '', 'rejected') || str_contains($data['type'] ?? '', 'chargeback') => '#dc2626',
                str_contains($data['type'] ?? '', 'approved') || str_contains($data['type'] ?? '', 'confirmed') || str_contains($data['type'] ?? '', 'activated') => '#16a34a',
                str_contains($data['type'] ?? '', 'submitted') || str_contains($data['type'] ?? '', 'warning') => '#e8a838',
                default => '#1e3a5f',
            };
        @endphp
        <div style="display:flex;align-items:start;gap:.85rem;padding:1rem 1.4rem;border-bottom:1px solid #f1f5f9;{{ !$notif->read_at ? 'background:#fffbeb;' : '' }}">
            <div style="width:38px;height:38px;border-radius:10px;background:{{ $color }}15;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas {{ $icon }}" style="color:{{ $color }};font-size:.85rem;"></i>
            </div>
            <div style="flex:1;">
                <div style="font-size:.85rem;font-weight:700;color:#0d1b2a;">{{ $data['title'] ?? 'Notification' }}</div>
                <div style="font-size:.82rem;color:#475569;margin-top:.15rem;">{{ $data['message'] ?? '' }}</div>
                <div style="font-size:.68rem;color:#94a3b8;margin-top:.25rem;">{{ $notif->created_at->diffForHumans() }}</div>
            </div>
            <div style="display:flex;gap:.3rem;align-items:center;flex-shrink:0;">
                @if(!empty($data['url']))
                <a href="{{ url($data['url']) }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-right"></i></a>
                @endif
                @if(!$notif->read_at)
                <form method="POST" action="{{ route('notifications.read', $notif->id) }}">@csrf<button type="submit" class="btn btn-outline btn-sm" title="Mark read"><i class="fas fa-check"></i></button></form>
                @endif
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:3rem;color:#94a3b8;">
            <i class="fas fa-bell-slash" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
            No notifications.
        </div>
        @endforelse
    </div>
</div>
@endsection
