<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    const DEFAULT_COMMISSION_RATE = 0.0100;

    public function calculateCommission(float $amount, User $user): array
    {
        $rate = $this->getCommissionRate($user);
        $commissionAmount = round($amount * $rate, 2);

        return [
            'rate' => $rate,
            'amount' => $commissionAmount,
            'net_amount' => round($amount - $commissionAmount, 2),
        ];
    }

    public function applyCommissionToPayment(Payment $payment, User $user): Payment
    {
        $commission = $this->calculateCommission((float) $payment->amount, $user);

        $payment->update([
            'commission_rate' => $commission['rate'],
            'commission_amount' => $commission['amount'],
        ]);

        return $payment->fresh();
    }

    public function getCommissionRate(User $user): float
    {
        $settings = config('services.platform', []);
        $baseRate = $settings['commission_rate'] ?? self::DEFAULT_COMMISSION_RATE;

        return (float) $baseRate;
    }

    public function getCommissionStats(User $user): array
    {
        $totalPayments = Payment::whereHas('subscription.customer', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->succeeded()->sum('amount');

        $totalCommission = Payment::whereHas('subscription.customer', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->succeeded()->sum('commission_amount');

        return [
            'total_payments' => (float) $totalPayments,
            'total_commission' => (float) $totalCommission,
            'net_revenue' => round((float) $totalPayments - (float) $totalCommission, 2),
            'effective_rate' => $totalPayments > 0
                ? round((float) $totalCommission / (float) $totalPayments, 4)
                : 0,
        ];
    }
}
