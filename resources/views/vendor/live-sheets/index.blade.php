@extends('layouts.app')
@section('title', 'My Live Sheets')
@section('page-title', 'My Live Sheets')

@section('content')

<div style="padding:.65rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Click <strong>Fill / Edit</strong> to view all columns and update Vendor FOB, Quantity, and other product details.
    Set <strong>Ex-Factory Date</strong> (within next 90 days) and <strong>Final Inspection Date</strong> (within 10 days of Ex-Factory) inline below.
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-list" style="margin-right:.5rem;color:#1e3a5f;"></i> My Live Sheets</h3>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Live Sheet #</th>
                    <th>Offer Sheet</th>
                    <th>Items</th>
                    <th>Total CBM</th>
                    <th>Status</th>

                    <th style="min-width:160px;">
                        Goods Ready Date
                        <span style="display:block;font-size:.62rem;font-weight:400;color:#94a3b8;">Within next 75 days</span>
                    </th>
                    <th style="min-width:160px;">
                        Final Inspection Date
                        <span style="display:block;font-size:.62rem;font-weight:400;color:#94a3b8;">Within 7 days of Goods Ready Date</span>
                    </th>

                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($liveSheets as $ls)
                @php

                $today = now()->toDateString();
                $maxExFactory = now()->addDays(75)->toDateString();
                $exFactory = $ls->ex_factory_date?->toDateString() ?? '';
                $finalInsp = $ls->final_inspection_date?->toDateString() ?? '';
                // Max inspection = Goods Ready Date + 7 days (or today + 100 as fallback before Goods Ready Date is set)
                $maxInspection = $exFactory ? \Carbon\Carbon::parse($exFactory)->addDays(7)->toDateString() : '';
                @endphp
                <tr id="row-{{ $ls->id }}">
                    <td style="font-family:monospace;font-weight:700;">{{ $ls->live_sheet_number }}</td>
                    <td style="font-size:.82rem;color:#64748b;">{{ $ls->offerSheet->offer_sheet_number ?? '—' }}</td>
                    <td style="text-align:center;font-weight:600;">{{ $ls->items->count() }}</td>
                    <td style="font-family:monospace;">{{ number_format($ls->total_cbm, 3) }}</td>
                    <td>
                        @if($ls->is_locked)
                        <span class="badge badge-success"><i class="fas fa-lock"></i> Locked</span>
                        @else
                        <span class="badge {{ ['draft'=>'badge-gray','submitted'=>'badge-warning'][$ls->status] ?? 'badge-gray' }}">{{ ucfirst($ls->status) }}</span>
                        @endif
                    </td>

                    {{-- Goods Ready Date --}}

                    @php
                    $formattedExFactory = $exFactory
                    ? \Carbon\Carbon::parse($exFactory)->format('d M Y')
                    : '—';
                    @endphp

                    <td>
                        @if(!$ls->consignment_id && $ls->is_locked)
                        <div style="position:relative;">
                            <input
                                type="date"
                                class="ex-factory-input"
                                data-id="{{ $ls->id }}"
                                value="{{ $exFactory }}"
                                min="{{ $today }}"
                                max="{{ $maxExFactory }}"
                                style="width:100%;padding:.32rem .45rem;border:1px solid {{ $exFactory ? '#86efac' : '#d1d5db' }};border-radius:6px;font-size:.8rem;font-family:monospace;color:#0d1b2a;background:{{ $exFactory ? '#f0fdf4' : '#fff' }};"
                                title="Select a date within the next 75 days">
                            <span class="save-indicator-{{ $ls->id }}" style="display:none;position:absolute;right:4px;top:50%;transform:translateY(-50%);font-size:.65rem;color:#16a34a;">
                                <i class="fas fa-check"></i>
                            </span>
                        </div>

                        <div style="font-size:.65rem;color:#94a3b8;margin-top:.2rem;">
                            Latest: {{ now()->addDays(90)->format('d M Y') }}
                        </div>

                        @elseif($ls->consignment_id)

                        <span style="font-size:.82rem;font-family:monospace;color:#475569;">
                            {{ $formattedExFactory }}
                        </span>

                        @else

                        <span style="font-size:.72rem;color:#94a3b8;">
                            <i class="fas fa-lock-open"></i> Available after approval
                        </span>

                        @endif
                    </td>

                    {{-- Final Inspection Date --}}
                    <td>
                        @if(!$ls->consignment_id && $ls->is_locked)
                        <div style="position:relative;">
                            <input
                                type="date"
                                class="inspection-input"
                                data-id="{{ $ls->id }}"
                                value="{{ $finalInsp }}"
                                min="{{ $exFactory ?: $today }}"
                                max="{{ $maxInspection ?: '' }}"
                                {{ !$exFactory ? 'disabled' : '' }}
                                style="width:100%;padding:.32rem .45rem;border:1px solid {{ $finalInsp ? '#86efac' : '#d1d5db' }};border-radius:6px;font-size:.8rem;font-family:monospace;color:#0d1b2a;background:{{ $finalInsp ? '#f0fdf4' : ($exFactory ? '#fff' : '#f8fafc') }};opacity:{{ $exFactory ? '1' : '.55' }};"
                                title="{{ $exFactory ? 'Select within 10 days of Ex-Factory date' : 'Set Ex-Factory date first' }}">
                        </div>
                        <div class="insp-hint-{{ $ls->id }}" style="font-size:.65rem;color:#94a3b8;margin-top:.2rem;">
                            @if($exFactory)
                            Latest: {{ \Carbon\Carbon::parse($exFactory)->addDays(10)->format('d M Y') }}
                            @else
                            Set Goods Ready Date first
                            @endif
                        </div>
                        @elseif($ls->consignment_id)
                        <span style="font-size:.82rem;font-family:monospace;color:#475569;">
                            {{ $finalInsp ? \Carbon\Carbon::parse($finalInsp)->format('d M Y') : '—' }}
                        </span>
                        @else
                        <span style="font-size:.72rem;color:#94a3b8;">
                            <i class="fas fa-lock-open"></i> Available after approval
                        </span>
                        @endif
                    </td>


                    <td style="font-size:.78rem;color:#64748b;">{{ $ls->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('vendor.live-sheets.edit', $ls) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> {{ $ls->is_locked ? 'View' : 'Fill / Edit' }}
                        </a>

                        @if(!$ls->consignment_id && $ls->is_locked)
                        <form method="POST" action="{{ route('vendor.live-sheets.create-consignment', $ls) }}" style="display:inline;" onsubmit="return confirm('Create consignment for this live sheet? Once created, dates cannot be changed.')">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm" style="margin-top: 2px;"><i class="fas fa-box"></i> Create Consignment</button>
                        </form>

                        @endif

                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:3rem;color:#94a3b8;">
                        <i class="fas fa-clipboard-list" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                        No live sheets assigned yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($liveSheets->hasPages())
    <div style="padding:1rem 1.4rem;border-top:1px solid #e8ecf1;">{{ $liveSheets->links('pagination::tailwind') }}</div>
    @endif
</div>

@push('scripts')
<script>
    (function() {
        var csrfToken = '{{ csrf_token() }}';
        var today = '{{ now()->toDateString() }}';
        var maxExFactory = '{{ now()->addDays(90)->toDateString() }}';

        // ── Helper: add days to a yyyy-mm-dd string ──────────────────────────────
        function addDays(dateStr, days) {
            var d = new Date(dateStr);
            d.setDate(d.getDate() + days);
            return d.toISOString().slice(0, 10);
        }

        // ── Format date for display hints ────────────────────────────────────────
        function formatDisplay(dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr);
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
        }

        // ── Save a single date field via AJAX ────────────────────────────────────
        function saveDate(lsId, field, value, onSuccess) {
            var body = {};
            body[field] = value;

            fetch('/vendor/live-sheets/' + lsId + '/dates', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (data.success && onSuccess) onSuccess();
                })
                .catch(function(e) {
                    console.error('Date save error:', e);
                });
        }

        // ── Ex-Factory inputs ─────────────────────────────────────────────────────
        document.querySelectorAll('.ex-factory-input').forEach(function(input) {
            input.addEventListener('change', function() {
                var lsId = this.getAttribute('data-id');
                var exDate = this.value;
                var inputEl = this;

                if (!exDate) return;

                // Enforce max 90 days
                if (exDate > maxExFactory) {
                    this.value = maxExFactory;
                    exDate = maxExFactory;
                }
                if (exDate < today) {
                    this.value = today;
                    exDate = today;
                }

                // Update inspection input constraints for this row
                var inspInput = document.querySelector('.inspection-input[data-id="' + lsId + '"]');
                var inspHint = document.querySelector('.insp-hint-' + lsId);
                var maxInsp = addDays(exDate, 10);

                if (inspInput) {
                    inspInput.disabled = false;
                    inspInput.min = exDate;
                    inspInput.max = maxInsp;
                    inspInput.style.opacity = '1';
                    inspInput.style.background = '#fff';
                    inspInput.style.borderColor = '#d1d5db';

                    // Clear inspection if it's now out of range
                    if (inspInput.value && (inspInput.value < exDate || inspInput.value > maxInsp)) {
                        inspInput.value = '';
                        inspInput.style.borderColor = '#d1d5db';
                        inspInput.style.background = '#fff';
                        // Clear saved inspection on server too
                        saveDate(lsId, 'final_inspection_date', null, null);
                    }
                }

                if (inspHint) {
                    inspHint.textContent = 'Latest: ' + formatDisplay(maxInsp);
                }

                // Save ex-factory date
                saveDate(lsId, 'ex_factory_date', exDate, function() {
                    inputEl.style.borderColor = '#86efac';
                    inputEl.style.background = '#f0fdf4';
                    var indicator = document.querySelector('.save-indicator-' + lsId);
                    if (indicator) {
                        indicator.style.display = 'inline';
                        setTimeout(function() {
                            indicator.style.display = 'none';
                        }, 2000);
                    }
                });
            });
        });

        // ── Final Inspection inputs ───────────────────────────────────────────────
        document.querySelectorAll('.inspection-input').forEach(function(input) {
            input.addEventListener('change', function() {
                var lsId = this.getAttribute('data-id');
                var inspDate = this.value;
                var inputEl = this;

                if (!inspDate) return;

                var minDate = this.min;
                var maxDate = this.max;

                // Enforce bounds
                if (maxDate && inspDate > maxDate) {
                    this.value = maxDate;
                    inspDate = maxDate;
                }
                if (minDate && inspDate < minDate) {
                    this.value = minDate;
                    inspDate = minDate;
                }

                // Save inspection date
                saveDate(lsId, 'final_inspection_date', inspDate, function() {
                    inputEl.style.borderColor = '#86efac';
                    inputEl.style.background = '#f0fdf4';
                });
            });
        });
    })();
</script>
@endpush
@endsection