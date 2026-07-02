<?php

namespace App\Listeners;

use App\Events\SubscriptionCanceled;
use App\Jobs\SendEmailJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionCanceled implements ShouldQueue
{
    public function handle(SubscriptionCanceled $event): void
    {
        $subscription = $event->subscription->load(['customer', 'plan']);

        Log::info('Subscription canceled', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'reason' => $subscription->cancel_reason,
        ]);

        SendEmailJob::dispatch(
            $subscription->customer->email,
            'Subscription Canceled',
            'subscription_canceled',
            [
                'customer_name' => $subscription->customer->name,
                'plan_name' => $subscription->plan->name,
                'canceled_at' => $subscription->canceled_at->format('Y-m-d H:i'),
                'reason' => $subscription->cancel_reason,
            ]
        );
    }
}
