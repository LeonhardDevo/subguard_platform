<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminSystemController;
use App\Http\Controllers\Api\Admin\AdminWebhookController;
use App\Http\Controllers\Api\Admin\AdminTenantResourceController;
use Illuminate\Support\Facades\Route;

// 2FA setup/confirm/disable routes – placed BEFORE the admin group
// so they are accessible at /api/admin/2fa/*.
Route::prefix('admin')
    ->middleware(['auth:api', 'admin', 'throttle:60,1'])
    ->group(function () {
        Route::post('/2fa/setup', [AdminSystemController::class, 'twoFactorSetup']);
        Route::post('/2fa/confirm', [AdminSystemController::class, 'twoFactorConfirm']);
        Route::post('/2fa/disable', [AdminSystemController::class, 'twoFactorDisable']);
        Route::get('/2fa/status', [AdminSystemController::class, 'twoFactorStatus']);
    });

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check (public)
Route::get('/health', HealthController::class);

// Stripe Webhooks (public, no CSRF, no auth)
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripe'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('throttle:30,1');

// Auth routes (public, rate-limited to prevent brute-force)
Route::prefix('auth')->middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Password reset routes (stricter throttling to prevent abuse)
Route::prefix('auth')->group(function () {
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:3,1');
});

// Protected routes
Route::middleware(['auth:api', 'not_locked', 'maintenance', 'throttle:60,1'])->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // Customers
    Route::apiResource('customers', CustomerController::class);
    Route::post('/customers/import', [CustomerController::class, 'import']);

    // Plans
    Route::apiResource('plans', PlanController::class);

    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show']);
    Route::put('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/subscriptions/portal', [SubscriptionController::class, 'portalSession']);

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::get('/invoices/{id}/download', [InvoiceController::class, 'download']);

    // Analytics
    Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('/analytics/dashboard/pdf', [AnalyticsController::class, 'exportPdf']);
    Route::get('/analytics/dashboard/csv', [AnalyticsController::class, 'exportCsv']);

    // Onboarding
    Route::post('/onboarding/stripe-connect', [OnboardingController::class, 'stripeConnect']);
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete']);
    Route::get('/onboarding/status', [OnboardingController::class, 'status']);

    // Checkout
    Route::post('/checkout/subscription', [CheckoutController::class, 'subscription']);
    Route::post('/checkout/portal', [CheckoutController::class, 'portal']);
});

// Admin routes
Route::prefix('admin')
    ->middleware(['auth:api', 'admin', 'throttle:60,1'])
    ->group(function () {
        // User management
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::put('/users/{user}/limit', [AdminUserController::class, 'updateLimit']);
        Route::post('/users/{user}/toggle-lock', [AdminUserController::class, 'toggleLock']);

        // Tenant overrides (on behalf of owners)
        Route::post('/users/{user}/customers', [AdminTenantResourceController::class, 'storeCustomer']);
        Route::post('/users/{user}/plans', [AdminTenantResourceController::class, 'storePlan']);
        Route::post('/users/{user}/subscriptions', [AdminTenantResourceController::class, 'storeSubscription']);
        Route::post('/subscriptions/{subscription}/payment', [AdminTenantResourceController::class, 'storePayment']);

        // System health & queues
        Route::get('/system/health', [AdminSystemController::class, 'health']);
        Route::get('/system/queue', [AdminSystemController::class, 'queueStatus']);
        Route::post('/system/queue/retry', [AdminSystemController::class, 'retryJob']);
        Route::get('/settings', [AdminSystemController::class, 'getSettings']);
        Route::put('/settings', [AdminSystemController::class, 'updateSettings']);

        // Webhook events
        Route::get('/webhooks', [AdminWebhookController::class, 'index']);
        Route::get('/webhooks/{event}', [AdminWebhookController::class, 'show']);
        Route::post('/webhooks/{event}/reprocess', [AdminWebhookController::class, 'reprocess']);
    });
