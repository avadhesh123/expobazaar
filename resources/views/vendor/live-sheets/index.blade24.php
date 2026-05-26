@extends('layouts.app')
@section('title', 'My Live Sheets')
@section('page-title', 'My Live Sheets')

@section('content')

<div style="padding:.65rem 1rem;background:#eff6ff;border-radius:8px;border:1px solid #bfdbfe;margin-bottom:1.25rem;font-size:.78rem;color:#1e40af;">
    <i class="fas fa-info-circle" style="margin-right:.3rem;"></i> Click <strong>Fill / Edit</strong> to view all columns and update Vendor FOB, Quantity, and other product details.
    Set <strong>Ex-Factory Date</strong> (within next 75 days) and <strong>Final Inspection Date</strong> (within 7 days of Ex-Factory) inline below.
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
                    <th style="min-width:180px;">
                        Factory Location
                        <span style="display:block;font-size:.62rem;font-weight:400;color:#94a3b8;">City, Country</span>
                    </th>
                    <th style="min-width:160px;">
                        Final Inspection Date
                        <span style="display:block;font-size:.62rem;font-weight:400;color:#94a3b8;">Within Goods Ready Date</span>
                    </th>

                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($liveSheets as $ls)
                @php

                $today = now()->toDateString();
                $exFactoryDate = now()->addDays(75);
                $maxExFactory = $exFactoryDate->toDateString();
                $exFactory = $ls->ex_factory_date?->toDateString() ?? '';
                $finalInsp = $ls->final_inspection_date?->toDateString() ?? '';
                $factoryLoc = $ls->factory_location ?? '';
                // Max inspection = Goods Ready Date + 7 days (or today + 100 as fallback before Goods Ready Date is set)
                // $maxInspection = $exFactory ? \Carbon\Carbon::parse($exFactory)->addDays(7)->toDateString() : '';
                // For client-side validation, we only need to validate that the maximum inspection date is within the selected ex-factory date. The server-side validation will also enforce this rule.

                $maxInspection = $exFactory ?? $maxExFactory;
                
                  $minInspection = $exFactoryDate
                    ->copy()
                    ->subDays(8)        // You can change 3 to any number 1-7
                    ->toDateString();

                @endphp
                <tr id="row-{{ $ls->id }}">{{ $maxInspection}} 
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
                                name="goods_ready_date"
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
                            Latest: {{ now()->addDays(75)->format('d M Y') }}
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
                    {{-- Factory Location --}}
                    <td>
                        @if(!$ls->consignment_id && $ls->is_locked)
                        <input type="text"
                            class="factory-location-input"
                            data-id="{{ $ls->id }}"
                            value="{{ $factoryLoc }}"
                            placeholder="e.g. Moradabad, India"
                            maxlength="500"
                            style="width:100%;padding:.32rem .45rem;border:1px solid {{ $factoryLoc ? '#86efac' : '#d1d5db' }};border-radius:6px;font-size:.8rem;color:#0d1b2a;background:{{ $factoryLoc ? '#f0fdf4' : '#fff' }};">
                        <div style="font-size:.6rem;color:#94a3b8;margin-top:.1rem;">City, Country</div>
                        @elseif($ls->consignment_id)
                        <span style="font-size:.82rem;color:#475569;">{{ $factoryLoc ?: '—' }}</span>
                        @else
                        <span style="font-size:.72rem;color:#94a3b8;"><i class="fas fa-lock-open"></i> Available after approval</span>

                        @endif
                    </td>
                    {{-- Final Inspection Date --}}
                    <td>
                        @if(!$ls->consignment_id && $ls->is_locked)
                        <div style="position:relative;">
                            <!-- <input
                                type="date"
                                class="inspection-input"
                                data-id="{{ $ls->id }}"
                                value="{{ $finalInsp }}"
                                min="{{  $minInspection }}"
                                max="{{ $maxInspection }}"
                                {{ !$exFactory ? 'disabled' : '' }}
                                style="width:100%;padding:.32rem .45rem;border:1px solid {{ $finalInsp ? '#86efac' : '#d1d5db' }};border-radius:6px;font-size:.8rem;font-family:monospace;color:#0d1b2a;background:{{ $finalInsp ? '#f0fdf4' : ($exFactory ? '#fff' : '#f8fafc') }};opacity:{{ $exFactory ? '1' : '.55' }};"
                                title="{{ $exFactory ? 'Select within 10 days of Ex-Factory date' : 'Set Ex-Factory date first' }}"
                                > -->

                                <input 
                                type="date" 
       class="inspection-input" 
       data-id="{{ $ls->id }}"
       min="{{ \Carbon\Carbon::parse($maxExFactory)->subDays(7)->format('Y-m-d') }}" 
       max="{{ \Carbon\Carbon::parse($maxExFactory)->format('Y-m-d') }}" 
       value="{{ $finalInsp ?? '' }}">
                        </div>
                        <div class="insp-hint-{{ $ls->id }}" style="font-size:.65rem;color:#94a3b8;margin-top:.2rem;">
                            @if($exFactory)
                            Latest: {{ now()->addDays(65)->format('d M Y') }}
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
        var maxExFactory = '{{ now()->addDays(75)->toDateString() }}';

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
 
// ── Dynamic Final Inspection Date (Last 7 Days of Goods Ready Date) ───────
 console.log('Initializing Live Sheets date inputs...');

    // Listen for changes on Goods Ready Date (Max Ex-Factory)
    document.querySelectorAll('.ex-factory-input').forEach(function(readyInput) {
        console.log('Attaching change listener to Goods Ready Date input for LS ID:', readyInput.getAttribute('data-id'));
        readyInput.addEventListener('change', function() {
            var lsId = this.getAttribute('data-id');
            var goodsReadyDate = this.value;
            console.log(`Goods Ready Date changed for LS ${lsId}: ${goodsReadyDate}`);
            if (!goodsReadyDate) return;

            var inspectionInput = document.querySelector(`.inspection-input[data-id="${lsId}"]`);
            if (!inspectionInput) return;

            // Calculate last 7 days window
            var maxDate = new Date(goodsReadyDate);
            var minDate = new Date(goodsReadyDate);
            minDate.setDate(maxDate.getDate() - 7);

            // Set min and max on inspection date input
            inspectionInput.min = minDate.toISOString().split('T')[0];
            inspectionInput.max = maxDate.toISOString().split('T')[0];
            console.log(`Set inspection date range for LS ${lsId}: ${inspectionInput.min} to ${inspectionInput.max}`);
            // Optional: Auto-fill with a default date (3 days before max)
            if (!inspectionInput.value) {
                var defaultDate = new Date(maxDate);
                defaultDate.setDate(maxDate.getDate() - 3);
                inspectionInput.value = defaultDate.toISOString().split('T')[0];
            }
        });
    });

    // Existing inspection date change handler
    document.querySelectorAll('.inspection-input').forEach(function(input) {
        input.addEventListener('change', function() {
            var lsId = this.getAttribute('data-id');
            var inspDate = this.value;
            var inputEl = this;

            if (!inspDate) return;

            // Enforce range
            if (this.max && inspDate > this.max) {
                this.value = this.max;
                inspDate = this.max;
            }
            if (this.min && inspDate < this.min) {
                this.value = this.min;
                inspDate = this.min;
            }

            // Save
            saveDate(lsId, 'final_inspection_date', inspDate, function() {
                inputEl.style.borderColor = '#86efac';
                inputEl.style.background = '#f0fdf4';
                setTimeout(() => {
                    inputEl.style.borderColor = '';
                    inputEl.style.background = '';
                }, 1500);
            });
        });
    });
 
 


    })();
</script>
@endpush
@endsection