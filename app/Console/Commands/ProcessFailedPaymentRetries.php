<?php

namespace App\Console\Commands;

use App\Jobs\RetryFailedPaymentJob;
use App\Models\FailedPayment;
use Illuminate\Console\Command;

class ProcessFailedPaymentRetries extends Command
{
    protected $signature = 'payments:process-retries';
    protected $description = 'Process failed payment retries that are due';

    public function handle(): int
    {
        $retryable = FailedPayment::retryable()->with('subscription')->get();
        $count = 0;

        foreach ($retryable as $failedPayment) {
            RetryFailedPaymentJob::dispatch($failedPayment);
            $count++;
        }

        $this->info("Dispatched {$count} payment retry jobs.");

        return Command::SUCCESS;
    }
}
