<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule recurring commands
Schedule::command('subscriptions:process-trial-expirations')->daily();
Schedule::command('payments:process-retries')->hourly();
