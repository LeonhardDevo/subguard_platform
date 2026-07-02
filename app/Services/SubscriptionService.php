<?php

namespace App\Services;

use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Events\SubscriptionCanceled;
use App\Events\SubscriptionCreated;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\FailedPayment;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function __construct(
        private readonly StripeService $stripeService
    ) {}

    public function list(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Subscription::whereHas('customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['customer', 'plan']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        $allowedSorts = ['created_at', 'start_date', 'next_billing_date', 'status'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc']) ? $filters['sort_dir'] : 'desc';
        $perPage = min($filters['per_page'] ?? 15, 100);

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    public function create(array $data): Subscription
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::findOrFail($data['customer_id']);
            $plan = Plan::findOrFail($data['plan_id']);

            // Freemium limit enforcement: 50 active subscribers free by default, or overridden by admin
            $activeSubscriptionCount = Subscription::whereHas('customer', fn($q) => $q->where('user_id', $customer->user_id))
                ->whereIn('status', ['active', 'trialing'])
                ->count();

            $globalLimit = \App\Models\GlobalSetting::getByKey('default_free_tier_limit') ?? 50;
            $userLimit = $customer->user->subscriber_limit;
            $limit = ($userLimit === 50 && $globalLimit !== 50) ? $globalLimit : ($userLimit ?? $globalLimit);

            if ($activeSubscriptionCount >= $limit) {
                throw new \RuntimeException("Free tier limit reached. You can manage up to {$limit} active subscribers on the free plan. Please upgrade to continue.");
            }

            // Create Stripe subscription if configured
            $providerSubscriptionId = null;
            if (config('services.stripe.secret')) {
                try {
                    // Ensure customer has a Stripe customer ID
                    if (!$customer->stripe_customer_id) {
                        $stripeCustomer = $this->stripeService->createCustomer([
                            'name' => $customer->name,
                            'email' => $customer->email,
                        ]);
                        $customer->update(['stripe_customer_id' => $stripeCustomer->id]);
                    }

                    // Ensure plan has a Stripe price ID
                    if (!$plan->stripe_price_id) {
                        $stripePrice = $this->stripeService->createPrice($plan);
                        $plan->update(['stripe_price_id' => $stripePrice->id]);
                    }

                    $stripeSubscription = $this->stripeService->createSubscription(
                        $customer->stripe_customer_id,
                        $plan->stripe_price_id,
                        $plan->trial_days
                    );

                    $providerSubscriptionId = $stripeSubscription->id;
                } catch (\Exception $e) {
                    Log::error('Stripe subscription creation failed', [
                        'customer_id' => $customer->id,
                        'plan_id' => $plan->id,
                        'error' => $e->getMessage(),
                    ]);
                    throw new \RuntimeException('Failed to create Stripe subscription: ' . $e->getMessage());
                }
            }

            $status = $plan->trial_days > 0 ? 'trialing' : 'active';
            $startDate = now();
            $nextBillingDate = $plan->trial_days > 0
                ? now()->addDays($plan->trial_days)
                : ($plan->interval === 'monthly' ? now()->addMonth() : ($plan->interval === 'yearly' ? now()->addYear() : now()->addWeek()));

            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'start_date' => $startDate,
                'next_billing_date' => $nextBillingDate,
                'provider_subscription_id' => $providerSubscriptionId,
            ]);

            AuditLog::log('subscription.created', $subscription, null, $data);

            event(new SubscriptionCreated($subscription));

            return $subscription->load(['customer', 'plan']);
        });
    }

    public function cancel(Subscription $subscription, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $reason) {
            // Cancel in Stripe
            if ($subscription->provider_subscription_id && config('services.stripe.secret')) {
                try {
                    $this->stripeService->cancelSubscription($subscription->provider_subscription_id);
                } catch (\Exception $e) {
                    Log::error('Stripe subscription cancellation failed', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $oldValues = $subscription->toArray();

            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            AuditLog::log('subscription.canceled', $subscription, $oldValues, [
                'cancel_reason' => $reason,
            ]);

            event(new SubscriptionCanceled($subscription));

            return $subscription->fresh(['customer', 'plan']);
        });
    }
}
