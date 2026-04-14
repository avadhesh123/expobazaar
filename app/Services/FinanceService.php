<?php

namespace App\Services;

use App\Models\{FinanceReceivable, Chargeback, VendorPayout, WarehouseCharge, Order, Vendor, ActivityLog, User};
use App\Notifications\{ChargebackNotification, PayoutNotification, FinanceNotification};
use Illuminate\Support\Facades\{DB, Notification};

class FinanceService
{
    /**
     * Update receivable deductions for an order
     */
    public function updateDeductions(FinanceReceivable $receivable, array $data, User $updater): FinanceReceivable
    {
        $receivable->update([
            'platform_commission' => $data['platform_commission'] ?? $receivable->platform_commission,
            'platform_fee' => $data['platform_fee'] ?? $receivable->platform_fee,
            'insurance_charge' => $data['insurance_charge'] ?? $receivable->insurance_charge,
            'chargeback_amount' => $data['chargeback_amount'] ?? $receivable->chargeback_amount,
            'other_deductions' => $data['other_deductions'] ?? $receivable->other_deductions,
            'deduction_notes' => $data['deduction_notes'] ?? $receivable->deduction_notes,
            'updated_by' => $updater->id,
        ]);

        $receivable->update(['net_receivable' => $receivable->calculateNetReceivable()]);

        ActivityLog::log('updated', 'finance_receivable', $receivable, null, $data, 'Deductions updated');
        return $receivable;
    }

    /**
     * Record payment received from marketplace
     */
    public function recordPayment(FinanceReceivable $receivable, array $data): FinanceReceivable
    {
        $receivable->update([
            'amount_received' => $data['amount_received'],
            'payment_date' => $data['payment_date'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'bank_reference' => $data['bank_reference'] ?? null,
            'payment_status' => $data['amount_received'] >= $receivable->net_receivable ? 'paid' : 'partial',
            'updated_by' => auth()->id(),
        ]);

        // Update order payment status
        $receivable->order->update([
            'payment_status' => $receivable->payment_status,
        ]);

        ActivityLog::log('recorded', 'payment', $receivable, null, $data, 'Payment recorded');
        return $receivable;
    }

    /**
     * Raise a chargeback against an order
     */
    public function raiseChargeback(Order $order, array $data): Chargeback
    {
        return DB::transaction(function () use ($order, $data) {
            $vendorId = $order->items->first()?->vendor_id;

            $chargeback = Chargeback::create([
                'order_id' => $order->id,
                'vendor_id' => $vendorId,
                'company_code' => $order->company_code,
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'description' => $data['description'] ?? null,
                'status' => 'pending_confirmation',
                'raised_by' => auth()->id(),
            ]);

            // Update receivable
            $receivable = $order->receivable;
            if ($receivable) {
                $receivable->increment('chargeback_amount', $data['amount']);
                $receivable->update(['net_receivable' => $receivable->calculateNetReceivable()]);
            }

            ActivityLog::log('raised', 'chargeback', $chargeback, null, $data, 'Chargeback raised');

            // Notify sourcing team for confirmation
            $sourcing = User::internal()->byDepartment('sourcing')->active()->get();
            Notification::send($sourcing, new ChargebackNotification($chargeback, 'confirmation_required'));

            // Notify vendor
            $vendor = Vendor::find($vendorId);
            if ($vendor) {
                $vendor->user->notify(new ChargebackNotification($chargeback, 'raised'));
            }

            return $chargeback;
        });
    }

    /**
     * Confirm chargeback (by sourcing)
     */
    public function confirmChargeback(Chargeback $chargeback, User $confirmer, bool $approved, ?string $remarks = null): Chargeback
    {
        $chargeback->update([
            'status' => $approved ? 'confirmed' : 'rejected',
            'confirmed_by' => $confirmer->id,
            'confirmed_at' => now(),
            'confirmation_remarks' => $remarks,
        ]);

        if ($approved) {
            // Reflect in vendor login
            $chargeback->vendor->user->notify(new ChargebackNotification($chargeback, 'confirmed'));
        }

        return $chargeback;
    }

    /**
     * Calculate monthly vendor payout
     */
    public function calculateVendorPayout(Vendor $vendor, int $month, int $year): VendorPayout
    {
        return DB::transaction(function () use ($vendor, $month, $year) {
            $startDate = now()->setYear($year)->setMonth($month)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            // Total sales for vendor this month
            $totalSales = Order::whereHas('items', fn($q) => $q->where('vendor_id', $vendor->id))
                ->whereBetween('order_date', [$startDate, $endDate])
                ->where('company_code', $vendor->company_code)
                ->sum('total_amount');

            // Warehouse charges
            $storageCharges = WarehouseCharge::where('vendor_id', $vendor->id)
                ->byMonth($month, $year)->where('charge_type', 'storage')->sum('calculated_amount');

            $inwardCharges = WarehouseCharge::where('vendor_id', $vendor->id)
                ->byMonth($month, $year)->where('charge_type', 'inward')->sum('calculated_amount');

            $logisticsCharges = WarehouseCharge::where('vendor_id', $vendor->id)
                ->byMonth($month, $year)->whereIn('charge_type', ['pick_pack', 'consumable', 'last_mile'])->sum('calculated_amount');

            // Platform deductions from finance receivables
            $platformDeductions = FinanceReceivable::whereHas('order', fn($q) => $q
                ->whereHas('items', fn($q2) => $q2->where('vendor_id', $vendor->id))
                ->whereBetween('order_date', [$startDate, $endDate])
            )->sum(DB::raw('platform_commission + platform_fee + insurance_charge + other_deductions'));

            // Chargebacks
            $chargebacks = Chargeback::where('vendor_id', $vendor->id)
                ->where('status', 'confirmed')
                ->whereBetween('confirmed_at', [$startDate, $endDate])
                ->sum('amount');

            $netPayout = $totalSales - $storageCharges - $inwardCharges - $logisticsCharges - $platformDeductions - $chargebacks;

            $payout = VendorPayout::updateOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'company_code' => $vendor->company_code,
                    'payout_month' => $month,
                    'payout_year' => $year,
                ],
                [
                    'total_sales' => $totalSales,
                    'total_storage_charges' => $storageCharges,
                    'total_inward_charges' => $inwardCharges,
                    'total_logistics_charges' => $logisticsCharges,
                    'total_platform_deductions' => $platformDeductions,
                    'total_chargebacks' => $chargebacks,
                    'net_payout' => $netPayout,
                    'status' => 'calculated',
                ]
            );

            ActivityLog::log('calculated', 'vendor_payout', $payout, null, null, "Payout calculated for {$month}/{$year}");
            return $payout;
        });
    }

    /**
     * Process vendor payment
     */
    public function processPayment(VendorPayout $payout, array $data): VendorPayout
    {
        $payout->update([
            'status' => 'paid',
            'payment_date' => $data['payment_date'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'paid_by' => auth()->id(),
        ]);

        // Generate payment advice
        ActivityLog::log('paid', 'vendor_payout', $payout, null, $data, 'Vendor payment processed');

        // Notify vendor
        $payout->vendor->user->notify(new PayoutNotification($payout, 'payment_processed'));

        return $payout;
    }
}
