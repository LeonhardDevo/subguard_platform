<?php

namespace App\Console\Commands;

use App\Events\SubscriptionCanceled;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessTrialExpirations extends Command
{
    protected $signature = 'subscriptions:process-trial-expirations';
    protected $description = 'Cancel subscriptions with expired trial periods';

    public function handle(): int
    {
        $expiredTrials = Subscription::trialing()
            ->where('next_billing_date', '<=', now())
            ->with(['customer', 'plan'])
            ->get();

        $count = 0;

        foreach ($expiredTrials as $subscription) {
            // If there's no payment method, cancel; otherwise, activate
            if ($subscription->provider_subscription_id) {
                $subscription->update(['status' => 'active']);
                Log::info('Trial converted to active', ['subscription_id' => $subscription->id]);
            } else {
                $subscription->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'cancel_reason' => 'trial_expired_no_payment',
                ]);
                event(new SubscriptionCanceled($subscription));
                Log::info('Trial expired and canceled', ['subscription_id' => $subscription->id]);
            }
            $count++;
        }

        $this->info("Processed {$count} trial expirations.");

        return Command::SUCCESS;
    }
}
