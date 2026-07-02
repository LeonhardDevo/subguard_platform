<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    private const CACHE_TTL = 300; // 5 minutes

    public function getDashboard(int $userId): array
    {
        return Cache::remember("analytics.dashboard.{$userId}", self::CACHE_TTL, function () use ($userId) {
            $mrr = $this->calculateMRR($userId);

            return [
                'mrr' => $mrr,
                'arr' => $mrr * 12,
                'active_subscribers' => $this->getActiveSubscribers($userId),
                'churn_rate' => $this->calculateChurnRate($userId),
                'ltv' => $this->calculateLTV($userId),
                'total_revenue' => $this->getTotalRevenue($userId),
                'new_subscribers_this_month' => $this->getNewSubscribersThisMonth($userId),
                'canceled_this_month' => $this->getCanceledThisMonth($userId),
                'trial_conversions' => $this->getTrialConversions($userId),
                'revenue_by_plan' => $this->getRevenueByPlan($userId),
                'subscriber_growth' => $this->getSubscriberGrowth($userId),
                'total_commission' => $this->getTotalCommission($userId),
                'nrr' => $this->calculateNRR($userId),
                'expansion_mrr' => $this->getExpansionMRR($userId),
            ];
        });
    }

    /**
     * MRR: Only sums active, non-trial subscriptions (TS requirement).
     */
    public function calculateMRR(int $userId): float
    {
        return (float) Subscription::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->active() // Only 'active' status, excludes 'trialing'
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->selectRaw("SUM(CASE WHEN plans.interval = 'monthly' THEN plans.price WHEN plans.interval = 'yearly' THEN plans.price / 12 WHEN plans.interval = 'weekly' THEN plans.price * 4.33 ELSE 0 END) as mrr")
            ->value('mrr') ?? 0;
    }

    /**
     * Active subscribers: counts active + trialing.
     */
    public function getActiveSubscribers(int $userId): int
    {
        return Subscription::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->whereIn('status', ['active', 'trialing'])
            ->count();
    }

    /**
     * Churn Rate: Canceled / (Active + Canceled) within the current period (TS formula).
     */
    public function calculateChurnRate(int $userId): float
    {
        $startOfMonth = now()->startOfMonth();

        $active = Subscription::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->where('status', 'active')
            ->count();

        $canceledThisMonth = Subscription::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->where('canceled_at', '>=', $startOfMonth)
            ->count();

        $denominator = $active + $canceledThisMonth;

        if ($denominator === 0) {
            return 0;
        }

        return round(($canceledThisMonth / $denominator) * 100, 2);
    }

    /**
     * LTV: Sum of all payments per customer, averaged across all customers (TS requirement).
     */
    public function calculateLTV(int $userId): float
    {
        $result = DB::table('payments')
            ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->join('customers', 'subscriptions.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->where('payments.status', 'succeeded')
            ->selectRaw('COUNT(DISTINCT customers.id) as customer_count, SUM(payments.amount) as total_revenue')
            ->first();

        if (!$result || $result->customer_count == 0) {
            return 0;
        }

        return round($result->total_revenue / $result->customer_count, 2);
    }

    public function getTotalRevenue(int $userId): float
    {
        return (float) DB::table('payments')
            ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->join('customers', 'subscriptions.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->where('payments.status', 'succeeded')
            ->sum('payments.amount') ?? 0;
    }

    public function getTotalCommission(int $userId): float
    {
        return (float) DB::table('payments')
            ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->join('customers', 'subscriptions.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->where('payments.status', 'succeeded')
            ->sum('payments.commission_amount') ?? 0;
    }

    public function calculateNRR(int $userId): float
    {
        return (float) Cache::remember("analytics.nrr.{$userId}", self::CACHE_TTL, function () use ($userId) {
            $startOfPrevMonth = now()->subMonth()->startOfMonth();
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            $startingMrr = $this->calculateMRRAtDate($userId, $startOfPrevMonth);

            if ($startingMrr <= 0) {
                return 100.0;
            }

            ['expansion' => $expansionMrr, 'contraction' => $contractionMrr] =
                $this->getExpansionContractionMRR($userId, $startOfMonth, $endOfMonth);

            $churnMrr = $this->getChurnMRRForPeriod($userId, $startOfMonth, $endOfMonth);

            return round((($startingMrr + $expansionMrr - $contractionMrr - $churnMrr) / $startingMrr) * 100, 2);
        });
    }

    public function getExpansionMRR(int $userId): float
    {
        return (float) Cache::remember("analytics.expansion_mrr.{$userId}", self::CACHE_TTL, function () use ($userId) {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            ['expansion' => $expansionMrr] = $this->getExpansionContractionMRR($userId, $startOfMonth, $endOfMonth);

            return $expansionMrr;
        });
    }

    private function calculateMRRAtDate(int $userId, $date): float
    {
        return (float) Subscription::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->where('created_at', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('canceled_at')
                    ->orWhere('canceled_at', '>', $date);
            })
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->selectRaw("SUM(CASE WHEN plans.interval = 'monthly' THEN plans.price WHEN plans.interval = 'yearly' THEN plans.price / 12 WHEN plans.interval = 'weekly' THEN plans.price * 4.33 ELSE 0 END) as mrr")
            ->value('mrr') ?? 0;
    }

    private function getMonthlyEquivalent(float $amount, string $interval): float
    {
        return match ($interval) {
            'monthly' => $amount,
            'yearly' => $amount / 12,
            'weekly' => $amount * 4.33,
            default => $amount,
        };
    }

    private function getExpansionContractionMRR(int $userId, $startDate, $endDate): array
    {
        $currentPayments = DB::table('payments')
            ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->join('customers', 'subscriptions.customer_id', '=', 'customers.id')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('customers.user_id', $userId)
            ->where('payments.status', 'succeeded')
            ->whereBetween('payments.created_at', [$startDate, $endDate])
            ->select('payments.subscription_id', 'payments.amount', 'plans.interval')
            ->get();

        $expansion = 0;
        $contraction = 0;

        foreach ($currentPayments as $payment) {
            $previousPayment = DB::table('payments')
                ->where('subscription_id', $payment->subscription_id)
                ->where('status', 'succeeded')
                ->where('created_at', '<', $startDate)
                ->orderByDesc('created_at')
                ->first();

            if ($previousPayment) {
                $currentMonthly = $this->getMonthlyEquivalent((float) $payment->amount, $payment->interval);
                $previousMonthly = $this->getMonthlyEquivalent((float) $previousPayment->amount, $payment->interval);

                if ($currentMonthly > $previousMonthly) {
                    $expansion += $currentMonthly - $previousMonthly;
                } elseif ($currentMonthly < $previousMonthly) {
                    $contraction += $previousMonthly - $currentMonthly;
                }
            }
        }

        return ['expansion' => round($expansion, 2), 'contraction' => round($contraction, 2)];
    }

    private function getChurnMRRForPeriod(int $userId, $startDate, $endDate): float
    {
        return (float) Subscription::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->whereNotNull('canceled_at')
            ->whereBetween('canceled_at', [$startDate, $endDate])
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->selectRaw("SUM(CASE WHEN plans.interval = 'monthly' THEN plans.price WHEN plans.interval = 'yearly' THEN plans.price / 12 WHEN plans.interval = 'weekly' THEN plans.price * 4.33 ELSE 0 END) as churn_mrr")
            ->value('churn_mrr') ?? 0;
    }

    private function getNewSubscribersThisMonth(int $userId): int
    {
        return Subscription::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    private function getCanceledThisMonth(int $userId): int
    {
        return Subscription::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->where('canceled_at', '>=', now()->startOfMonth())
            ->count();
    }

    private function getTrialConversions(int $userId): array
    {
        $totalTrials = Subscription::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->where('start_date', '>=', now()->subMonths(3))
            ->whereNotNull('plan_id')
            ->whereHas('plan', fn($q) => $q->where('trial_days', '>', 0))
            ->count();

        $converted = Subscription::whereHas('customer', fn($q) => $q->where('user_id', $userId))
            ->where('start_date', '>=', now()->subMonths(3))
            ->whereHas('plan', fn($q) => $q->where('trial_days', '>', 0))
            ->where('status', 'active')
            ->count();

        return [
            'total_trials' => $totalTrials,
            'converted' => $converted,
            'rate' => $totalTrials > 0 ? round(($converted / $totalTrials) * 100, 2) : 0,
        ];
    }

    private function getRevenueByPlan(int $userId): array
    {
        return DB::table('payments')
            ->join('subscriptions', 'payments.subscription_id', '=', 'subscriptions.id')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->join('customers', 'subscriptions.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->where('payments.status', 'succeeded')
            ->groupBy('plans.id', 'plans.name')
            ->select('plans.name', DB::raw('SUM(payments.amount) as total_revenue'), DB::raw('COUNT(DISTINCT subscriptions.id) as subscriber_count'))
            ->get()
            ->toArray();
    }

    /**
     * Fixed N+1: Uses 2 aggregate queries instead of 24 individual queries in a loop.
     */
    private function getSubscriberGrowth(int $userId): array
    {
        $startDate = now()->subMonths(11)->startOfMonth();

        // Single query for new subscriptions
        $newDates = DB::table('subscriptions')
            ->join('customers', 'subscriptions.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->where('subscriptions.created_at', '>=', $startDate)
            ->pluck('subscriptions.created_at');

        $newByMonth = $newDates->countBy(function ($date) {
            return \Carbon\Carbon::parse($date)->format('Y-m');
        })->toArray();

        // Single query for canceled subscriptions
        $canceledDates = DB::table('subscriptions')
            ->join('customers', 'subscriptions.customer_id', '=', 'customers.id')
            ->where('customers.user_id', $userId)
            ->whereNotNull('subscriptions.canceled_at')
            ->where('subscriptions.canceled_at', '>=', $startDate)
            ->pluck('subscriptions.canceled_at');

        $canceledByMonth = $canceledDates->countBy(function ($date) {
            return \Carbon\Carbon::parse($date)->format('Y-m');
        })->toArray();

        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $months[] = [
                'month' => $key,
                'label' => $date->format('M Y'),
                'new' => $newByMonth[$key] ?? 0,
                'canceled' => $canceledByMonth[$key] ?? 0,
            ];
        }

        return $months;
    }
}
