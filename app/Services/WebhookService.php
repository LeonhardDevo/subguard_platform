<?php

namespace App\Services;

use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Events\SubscriptionCanceled;
use App\Models\FailedPayment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function processEvent(\Stripe\Event $event): void
    {
        // Idempotency check
        $existing = WebhookEvent::where('provider_event_id', $event->id)->first();
        if ($existing && $existing->status === 'processed') {
            Log::info('Webhook event already processed', ['event_id' => $event->id]);
            return;
        }

        $webhookEvent = WebhookEvent::updateOrCreate(
            ['provider_event_id' => $event->id],
            [
                'provider' => 'stripe',
                'event_type' => $event->type,
                'payload' => $event->toArray(),
                'status' => 'processing',
            ]
        );

        try {
            DB::transaction(function () use ($event, $webhookEvent) {
                match ($event->type) {
                    'invoice.paid' => $this->handleInvoicePaid($event),
                    'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
                    'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
                    'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
                    default => Log::info('Unhandled webhook event', ['type' => $event->type]),
                };

                $webhookEvent->markProcessed();
            });
        } catch (\Exception $e) {
            $webhookEvent->markFailed($e->getMessage());

            Log::error('Webhook processing failed', [
                'event_id' => $event->id,
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function handleInvoicePaid(\Stripe\Event $event): void
    {
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription;

        $subscription = Subscription::where('provider_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            Log::warning('Subscription not found for webhook', ['provider_subscription_id' => $subscriptionId]);
            return;
        }

        $amount = $invoice->amount_paid / 100;
        $commissionRate = ((float) \App\Models\GlobalSetting::getByKey('platform_commission_rate', 1.0)) / 100;
        $commissionAmount = round($amount * $commissionRate, 2);

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'commission_amount' => $commissionAmount,
            'commission_rate' => $commissionRate,
            'currency' => strtoupper($invoice->currency),
            'status' => 'succeeded',
            'provider' => 'stripe',
            'provider_payment_id' => $invoice->payment_intent,
        ]);

        // Use Stripe's period_end for accurate next billing date
        $nextBillingDate = isset($invoice->lines->data[0]->period->end)
            ? \Carbon\Carbon::createFromTimestamp($invoice->lines->data[0]->period->end)
            : now()->addMonth();

        $subscription->update([
            'status' => 'active',
            'next_billing_date' => $nextBillingDate,
        ]);

        // Resolve any pending failed payments
        FailedPayment::where('subscription_id', $subscription->id)
            ->unresolved()
            ->update([
                'resolved_at' => now(),
                'resolution' => 'payment_succeeded',
            ]);

        event(new PaymentSucceeded($payment));
    }

    private function handleInvoicePaymentFailed(\Stripe\Event $event): void
    {
        $invoice = $event->data->object;
        $subscriptionId = $invoice->subscription;

        $subscription = Subscription::where('provider_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            return;
        }

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'amount' => $invoice->amount_due / 100,
            'currency' => strtoupper($invoice->currency),
            'status' => 'failed',
            'provider' => 'stripe',
            'provider_payment_id' => $invoice->payment_intent,
        ]);

        $subscription->update(['status' => 'past_due']);

        FailedPayment::create([
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reason' => 'Payment failed via Stripe',
            'retry_count' => 0,
            'next_retry_at' => now()->addHours(24),
        ]);

        event(new PaymentFailed($payment));
    }

    private function handleSubscriptionDeleted(\Stripe\Event $event): void
    {
        $stripeSubscription = $event->data->object;

        $subscription = Subscription::where('provider_subscription_id', $stripeSubscription->id)->first();
        if (!$subscription) {
            return;
        }

        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'cancel_reason' => 'canceled_by_stripe',
        ]);

        event(new SubscriptionCanceled($subscription));
    }

    private function handleSubscriptionUpdated(\Stripe\Event $event): void
    {
        $stripeSubscription = $event->data->object;

        $subscription = Subscription::where('provider_subscription_id', $stripeSubscription->id)->first();
        if (!$subscription) {
            return;
        }

        $statusMap = [
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'trialing' => 'trialing',
            'incomplete' => 'incomplete',
            'paused' => 'paused',
        ];

        $newStatus = $statusMap[$stripeSubscription->status] ?? $subscription->status;

        $subscription->update([
            'status' => $newStatus,
        ]);
    }
}
