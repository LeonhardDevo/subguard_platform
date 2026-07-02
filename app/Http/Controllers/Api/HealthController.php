<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
            ],
        ];

        $isHealthy = collect($checks['services'])->every(fn($s) => $s['status'] === 'up');
        $checks['status'] = $isHealthy ? 'healthy' : 'degraded';

        return response()->json($checks, $isHealthy ? 200 : 503);
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

    private function checkCache(): array
    {
        try {
            Cache::put('health_check', true, 10);
            Cache::get('health_check');
            return ['status' => 'up'];
        } catch (\Exception $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            $disk->put('health_check.tmp', 'ok');
            $disk->delete('health_check.tmp');
            return ['status' => 'up'];
        } catch (\Exception $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }
}
