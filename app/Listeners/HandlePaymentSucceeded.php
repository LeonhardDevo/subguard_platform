<?php

namespace App\Listeners;

use App\Events\PaymentSucceeded;
use App\Jobs\SendEmailJob;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class HandlePaymentSucceeded implements ShouldQueue
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment->load('subscription.customer');

        Log::info('Payment succeeded', [
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'subscription_id' => $payment->subscription_id,
        ]);

        // Generate invoice
        $invoice = $this->invoiceService->generate($payment->subscription, $payment);

        // Send payment success email
        SendEmailJob::dispatch(
            $payment->subscription->customer->email,
            'Payment Received',
            'payment_success',
            [
                'customer_name' => $payment->subscription->customer->name,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'invoice_number' => $invoice->number,
            ]
        );
    }
}
