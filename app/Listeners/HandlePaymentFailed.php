<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Jobs\RetryFailedPaymentJob;
use App\Jobs\SendEmailJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandlePaymentFailed implements ShouldQueue
{
    public function handle(PaymentFailed $event): void
    {
        $payment = $event->payment->load('subscription.customer');

        Log::warning('Payment failed', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'subscription_id' => $payment->subscription_id,
        ]);

        // Send payment failure email
        SendEmailJob::dispatch(
            $payment->subscription->customer->email,
            'Payment Failed',
            'payment_failed',
            [
                'customer_name' => $payment->subscription->customer->name,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]
        );

        // Schedule retry if a FailedPayment record exists
        $failedPayment = $payment->subscription->failedPayments()
            ->where('payment_id', $payment->id)
            ->unresolved()
            ->first();

        if ($failedPayment && $failedPayment->next_retry_at) {
            RetryFailedPaymentJob::dispatch($failedPayment)
                ->delay($failedPayment->next_retry_at);
        }
    }
}
