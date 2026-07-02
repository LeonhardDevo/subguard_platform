<?php

namespace App\Services;

use App\Events\SubscriptionCanceled;
use App\Models\FailedPayment;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class PaymentRetryService
{
    public function __construct(
        private readonly StripeService $stripeService
    ) {}

    public function retry(FailedPayment $failedPayment): bool
    {
        $subscription = $failedPayment->subscription;

        if ($failedPayment->retry_count >= FailedPayment::MAX_RETRIES) {
            $this->cancelSubscription($subscription, $failedPayment);
            return false;
        }

        try {
            if ($subscription->provider_subscription_id && config('services.stripe.secret')) {
                $stripeSubscription = $this->stripeService->getSubscription($subscription->provider_subscription_id);

                if ($stripeSubscription->latest_invoice) {
                    $this->stripeService->retryInvoicePayment($stripeSubscription->latest_invoice);
                }
            }

            // Success
            $failedPayment->update([
                'resolved_at' => now(),
                'resolution' => 'retry_succeeded',
            ]);

            $subscription->update(['status' => 'active']);

            Log::info('Payment retry succeeded', [
                'subscription_id' => $subscription->id,
                'retry_count' => $failedPayment->retry_count,
            ]);

            return true;
        } catch (\Exception $e) {
            $failedPayment->increment('retry_count');

            $nextInterval = $failedPayment->getNextRetryInterval();

            if ($nextInterval) {
                $failedPayment->update([
                    'next_retry_at' => now()->addHours($nextInterval),
                ]);
            } else {
                $this->cancelSubscription($subscription, $failedPayment);
            }

            Log::warning('Payment retry failed', [
                'subscription_id' => $subscription->id,
                'retry_count' => $failedPayment->retry_count,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function cancelSubscription(Subscription $subscription, FailedPayment $failedPayment): void
    {
        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'cancel_reason' => 'payment_failed_max_retries',
        ]);

        $failedPayment->update([
            'resolved_at' => now(),
            'resolution' => 'subscription_canceled',
        ]);

        event(new SubscriptionCanceled($subscription));

        Log::info('Subscription canceled due to max payment retries', [
            'subscription_id' => $subscription->id,
        ]);
    }
}
