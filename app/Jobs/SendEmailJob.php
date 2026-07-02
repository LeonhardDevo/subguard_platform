<?php

namespace App\Jobs;

use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private readonly string $to,
        private readonly string $subject,
        private readonly string $template,
        private readonly array $data = []
    ) {
        $this->queue = 'emails';
    }

    public function handle(): void
    {
        $emailLog = EmailLog::create([
            'to' => $this->to,
            'subject' => $this->subject,
            'template' => $this->template,
            'status' => 'queued',
            'metadata' => $this->data,
        ]);

        try {
            Mail::send("emails.{$this->template}", $this->data, function ($message) {
                $message->to($this->to)
                    ->subject($this->subject);
            });

            $emailLog->markSent();

            Log::info('Email sent successfully', [
                'to' => $this->to,
                'template' => $this->template,
            ]);
        } catch (\Exception $e) {
            $emailLog->markFailed($e->getMessage());

            Log::error('Email sending failed', [
                'to' => $this->to,
                'template' => $this->template,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendEmailJob failed permanently', [
            'to' => $this->to,
            'template' => $this->template,
            'error' => $exception->getMessage(),
        ]);
    }
}
