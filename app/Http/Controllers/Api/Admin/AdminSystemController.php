<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminTwoFactorRequest;
use App\Models\AuditLog;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Support\Base32;
use Stripe\Balance;
use Stripe\Stripe;

class AdminSystemController extends Controller
{
    /**
     * Get system health report (DB, Redis, S3, Stripe).
     */
    public function health(): JsonResponse
    {
        $report = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                's3' => $this->checkS3(),
                'stripe' => $this->checkStripe(),
            ],
        ];

        $isHealthy = collect($report['services'])->every(fn($service) => $service['status'] === 'up');
        $report['status'] = $isHealthy ? 'healthy' : 'degraded';

        return response()->json($report, $isHealthy ? 200 : 503);
    }

    /**
     * Get queue metrics and failed jobs.
     */
    public function queueStatus(Request $request): JsonResponse
    {
        $queues = ['default', 'emails', 'webhooks', 'payment-retries'];
        $queueMetrics = [];

        foreach ($queues as $q) {
            try {
                $queueMetrics[$q] = Queue::size($q);
            } catch (\Exception $e) {
                $queueMetrics[$q] = 0;
            }
        }

        $failedCount = DB::table('failed_jobs')->count();
        $recentFailedJobs = DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->paginate($request->input('per_page', 10));

        return response()->json([
            'metrics' => [
                'queue_sizes' => $queueMetrics,
                'failed_jobs_count' => $failedCount,
            ],
            'failed_jobs' => $recentFailedJobs,
        ]);
    }

    /**
     * Trigger retrying failed jobs.
     */
    public function retryJob(Request $request): JsonResponse
    {
        if (!auth()->user() || auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'id' => 'nullable|string', // ID, UUID, or 'all'
        ]);

        $id = $request->input('id', 'all');
        $adminId = auth()->id();

        if ($id !== 'all') {
            $job = DB::table('failed_jobs')
                ->where('id', $id)
                ->orWhere('uuid', $id)
                ->first();

            if (!$job) {
                return response()->json([
                    'message' => "Failed job with ID/UUID {$id} not found."
                ], 404);
            }

            Artisan::call('queue:retry', ['id' => [$job->uuid]]);
            DB::table('audit_logs')->insert([
                'user_id' => $adminId,
                'action' => 'admin.queue.retry_job',
                'auditable_type' => null,
                'auditable_id' => null,
                'old_values' => null,
                'new_values' => json_encode(['admin_id' => $adminId, 'job_uuid' => $job->uuid]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $message = "Retry request submitted for job {$job->uuid}.";
        } else {
            Artisan::call('queue:retry', ['id' => ['all']]);
            DB::table('audit_logs')->insert([
                'user_id' => $adminId,
                'action' => 'admin.queue.retry_all',
                'auditable_type' => null,
                'auditable_id' => null,
                'old_values' => null,
                'new_values' => json_encode(['admin_id' => $adminId]),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $message = "Retry request submitted for all failed jobs.";
        }

        return response()->json([
            'message' => $message
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'up'];
        } catch (\Exception $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            if (app()->environment('testing')) {
                return ['status' => 'up'];
            }
            \Illuminate\Support\Facades\Redis::connection()->ping();
            return ['status' => 'up'];
        } catch (\Exception $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    private function checkS3(): array
    {
        try {
            if (app()->environment('testing')) {
                return ['status' => 'up'];
            }
            $disk = Storage::disk('s3');
            $disk->put('health_check.tmp', 'ok');
            $disk->delete('health_check.tmp');
            return ['status' => 'up'];
        } catch (\Exception $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    private function checkStripe(): array
    {
        try {
            if (app()->environment('testing')) {
                return ['status' => 'up'];
            }

            $secret = config('services.stripe.secret');
            if (empty($secret)) {
                return ['status' => 'down', 'error' => 'Stripe secret key is not configured.'];
            }

            Stripe::setApiKey($secret);
            Balance::retrieve();
            return ['status' => 'up'];
        } catch (\Exception $e) {
            $sanitizedError = 'Stripe service unavailable';
            if (app()->environment('local', 'development')) {
                $sanitizedError = class_basename(get_class($e)) . ': ' . $e->getMessage();
            }
            return ['status' => 'down', 'error' => $sanitizedError];
        }
    }

    /**
     * Get all global settings.
     */
    public function getSettings(): JsonResponse
    {
        $keys = [
            'platform_commission_rate',
            'default_free_tier_limit',
            'maintenance_mode',
            'stripe_mock_mode',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = \App\Models\GlobalSetting::getByKey($key);
        }

        return response()->json([
            'message' => 'Settings retrieved successfully',
            'data' => $settings,
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Update global settings in bulk.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'platform_commission_rate' => 'sometimes|numeric|min:0|max:100',
            'default_free_tier_limit' => 'sometimes|integer|min:0',
            'maintenance_mode' => 'sometimes|boolean',
            'stripe_mock_mode' => 'sometimes|boolean',
        ]);

        $keys = [
            'platform_commission_rate',
            'default_free_tier_limit',
            'maintenance_mode',
            'stripe_mock_mode',
        ];

        $oldValues = [];
        foreach ($keys as $key) {
            if ($request->has($key)) {
                $oldValues[$key] = \App\Models\GlobalSetting::getByKey($key);
            }
        }

        DB::transaction(function () use ($request, $keys) {
            foreach ($keys as $key) {
                if ($request->has($key)) {
                    $value = $request->input($key);
                    \App\Models\GlobalSetting::setByKey($key, $value);
                }
            }
        });

        $newValues = [];
        foreach ($keys as $key) {
            if ($request->has($key)) {
                $newValues[$key] = \App\Models\GlobalSetting::getByKey($key);
            }
        }

        if (!empty($newValues)) {
            $settingsModel = new \App\Models\GlobalSetting();
            AuditLog::log('admin.settings.updated', $settingsModel, $oldValues, $newValues);
        }

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = \App\Models\GlobalSetting::getByKey($key);
        }

        return response()->json([
            'message' => 'Settings updated successfully',
            'data' => $settings,
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Generate a new TOTP secret for the authenticated admin.
     */
    public function twoFactorSetup(): JsonResponse
    {
        $user = auth()->user();

        $secret = Base32::random(20); // 160-bit base32 secret

        $email = $user->email;
        $otpauthUrl = 'otpauth://totp/SubGuard:' . rawurlencode($email)
            . '?secret=' . $secret
            . '&issuer=SubGuard';

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
        ]);
    }

    public function twoFactorStatus(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'data' => [
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            ],
        ]);
    }

    /**
     * Confirm TOTP 2FA by validating a code against the provided secret.
     * The client must call setup first and send the same secret back.
     */
    public function twoFactorConfirm(AdminTwoFactorRequest $request): JsonResponse
    {
        $user = auth()->user();
        $secret = $request->input('secret');
        $code = $request->input('code');

        if (empty($secret)) {
            return response()->json(['message' => 'Secret is required.'], 422);
        }

        if (! Totp::verify($secret, $code)) {
            return response()->json(['message' => 'Invalid two-factor code.'], 422);
        }

        $user->enableTwoFactor($secret);
        $user->confirmTwoFactor();

        return response()->json(['message' => 'Two-factor authentication has been enabled.']);
    }

    /**
     * Disable TOTP 2FA after validating the current code.
     */
    public function twoFactorDisable(AdminTwoFactorRequest $request): JsonResponse
    {
        $user = auth()->user();
        $code = $request->input('code');

        // If 2FA is enabled, the code must be valid against the stored secret.
        if ($user->hasTwoFactorEnabled()) {
            $secret = decrypt($user->two_factor_secret);

            if (! Totp::verify($secret, $code)) {
                return response()->json(['message' => 'Invalid two-factor code.'], 422);
            }
        }

        $user->disableTwoFactor();

        return response()->json(['message' => 'Two-factor authentication has been disabled.']);
    }
}
