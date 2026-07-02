<?php

namespace App\Providers;

use App\Events\InvoiceGenerated;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Events\SubscriptionCanceled;
use App\Events\SubscriptionCreated;
use App\Listeners\HandlePaymentFailed;
use App\Listeners\HandlePaymentSucceeded;
use App\Listeners\HandleSubscriptionCanceled;
use App\Listeners\HandleSubscriptionCreated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        SubscriptionCreated::class => [
            HandleSubscriptionCreated::class,
        ],
        SubscriptionCanceled::class => [
            HandleSubscriptionCanceled::class,
        ],
        PaymentSucceeded::class => [
            HandlePaymentSucceeded::class,
        ],
        PaymentFailed::class => [
            HandlePaymentFailed::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
