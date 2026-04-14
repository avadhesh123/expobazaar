            @elseif(auth()->user()->isVendor())
                @php $kycApproved = auth()->user()->vendor && auth()->user()->vendor->kyc_status === 'approved'; @endphp
                <div class="section-title">Vendor Panel</div>
                <a href="{{ route('vendor.dashboard') }}" class="{{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="{{ route('vendor.kyc') }}" class="{{ request()->routeIs('vendor.kyc*') ? 'active' : '' }}"><i class="fas fa-id-card"></i> KYC Documents</a>

                @if($kycApproved)
                    <a href="{{ route('vendor.offer-sheets') }}" class="{{ request()->routeIs('vendor.offer-sheets*') ? 'active' : '' }}"><i class="fas fa-file-alt"></i> Offer Sheets</a>
                    <a href="{{ route('vendor.consignments') }}" class="{{ request()->routeIs('vendor.consignments*') ? 'active' : '' }}"><i class="fas fa-box"></i> Consignments</a>
                    <a href="{{ route('vendor.live-sheets') }}" class="{{ request()->routeIs('vendor.live-sheets*') ? 'active' : '' }}"><i class="fas fa-clipboard-list"></i> Live Sheets</a>
                    <a href="{{ route('vendor.sales') }}" class="{{ request()->routeIs('vendor.sales*') ? 'active' : '' }}"><i class="fas fa-chart-line"></i> Sales Report</a>
                    <a href="{{ route('vendor.chargebacks') }}" class="{{ request()->routeIs('vendor.chargebacks*') ? 'active' : '' }}"><i class="fas fa-exclamation-triangle"></i> Chargebacks</a>
                    <a href="{{ route('vendor.payouts') }}" class="{{ request()->routeIs('vendor.payouts*') ? 'active' : '' }}"><i class="fas fa-money-check-alt"></i> Payouts</a>
                @else
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Offer Sheets</span>
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Consignments</span>
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Live Sheets</span>
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Sales Report</span>
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Chargebacks</span>
                    <span style="display:block;padding:.55rem .85rem;color:#94a3b8;font-size:.82rem;cursor:not-allowed;opacity:.5;"><i class="fas fa-lock" style="margin-right:.4rem;font-size:.7rem;"></i> Payouts</span>
                    <div style="margin:.5rem .85rem;padding:.5rem .65rem;background:#fef3c7;border-radius:8px;font-size:.68rem;color:#92400e;line-height:1.3;">
                        <i class="fas fa-info-circle" style="margin-right:.2rem;"></i> Complete KYC & get Finance approval to unlock all modules.
                    </div>
                @endif