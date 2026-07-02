<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->adminToken = auth('api')->login($this->admin);
    }

    public function test_admin_can_get_system_health(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/system/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => [
                    'database',
                    'redis',
                    's3',
                    'stripe',
                ]
            ])
            ->assertJsonPath('services.database.status', 'up')
            ->assertJsonPath('services.redis.status', 'up')
            ->assertJsonPath('services.s3.status', 'up')
            ->assertJsonPath('services.stripe.status', 'up');
    }

    public function test_admin_can_get_queue_status(): void
    {
        // Insert a failed job
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException: test in file.php',
            'failed_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/system/queue');

        $response->assertOk()
            ->assertJsonStructure([
                'metrics' => [
                    'queue_sizes',
                    'failed_jobs_count',
                ],
                'failed_jobs' => [
                    'data' => [
                        '*' => [
                            'id',
                            'uuid',
                            'connection',
                            'queue',
                            'exception',
                            'failed_at',
                        ]
                    ]
                ]
            ])
            ->assertJsonPath('metrics.failed_jobs_count', 1);
    }

    public function test_admin_can_retry_specific_failed_job(): void
    {
        // Insert a failed job
        $uuid = (string) Str::uuid();
        $id = DB::table('failed_jobs')->insertGetId([
            'uuid' => $uuid,
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'RuntimeException: test in file.php',
            'failed_at' => now(),
        ]);

        // Mock Artisan queue:retry
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', ['id' => [$uuid]])
            ->andReturn(0);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/system/queue/retry', [
                'id' => (string) $id,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', "Retry request submitted for job {$uuid}.");
    }

    public function test_admin_can_retry_all_failed_jobs(): void
    {
        // Mock Artisan queue:retry for all
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:retry', ['id' => ['all']])
            ->andReturn(0);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/system/queue/retry', [
                'id' => 'all',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Retry request submitted for all failed jobs.');
    }

    public function test_retry_non_existent_failed_job_returns_404(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/admin/system/queue/retry', [
                'id' => '999999',
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Failed job with ID/UUID 999999 not found.');
    }
}
