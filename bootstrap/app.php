<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'auth' => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'not_locked' => \App\Http\Middleware\CheckUserNotLocked::class,
            'maintenance' => \App\Http\Middleware\MaintenanceModeMiddleware::class,
        ]);

        $middleware->throttleApi('60,1');
    })
    ->withProviders([
        \App\Providers\EventServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
