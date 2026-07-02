<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Jobs\SendEmailJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionCreated implements ShouldQueue
{
    public function handle(SubscriptionCreated $event): void
    {
        $subscription = $event->subscription->load(['customer', 'plan']);

        Log::info('Subscription created', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'plan_id' => $subscription->plan_id,
        ]);

        SendEmailJob::dispatch(
            $subscription->customer->email,
            'Subscription Created',
            'subscription_created',
            [
                'customer_name' => $subscription->customer->name,
                'plan_name' => $subscription->plan->name,
                'start_date' => $subscription->start_date->format('Y-m-d'),
            ]
        );
    }
}
