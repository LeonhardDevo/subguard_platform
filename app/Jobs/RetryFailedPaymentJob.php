<?php

namespace App\Jobs;

use App\Models\FailedPayment;
use App\Services\PaymentRetryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly FailedPayment $failedPayment
    ) {
        $this->queue = 'payments';
    }

    public function handle(PaymentRetryService $retryService): void
    {
        Log::info('Retrying failed payment', [
            'failed_payment_id' => $this->failedPayment->id,
            'retry_count' => $this->failedPayment->retry_count,
        ]);

        $success = $retryService->retry($this->failedPayment);

        if (!$success && $this->failedPayment->fresh()->next_retry_at) {
            // Schedule next retry
            self::dispatch($this->failedPayment->fresh())
                ->delay($this->failedPayment->fresh()->next_retry_at);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RetryFailedPaymentJob failed permanently', [
            'failed_payment_id' => $this->failedPayment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
