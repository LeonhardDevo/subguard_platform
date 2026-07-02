<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminWebhookTest extends TestCase
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

    public function test_admin_can_list_webhook_events(): void
    {
        WebhookEvent::create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_1',
            'event_type' => 'invoice.paid',
            'payload' => ['id' => 'evt_1', 'type' => 'invoice.paid'],
            'status' => 'processed',
            'processed_at' => now(),
            'attempts' => 1,
        ]);

        WebhookEvent::create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_2',
            'event_type' => 'invoice.payment_failed',
            'payload' => ['id' => 'evt_2', 'type' => 'invoice.payment_failed'],
            'status' => 'failed',
            'error' => 'Some error',
            'attempts' => 1,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/webhooks');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'provider',
                        'provider_event_id',
                        'event_type',
                        'status',
                        'attempts',
                    ]
                ]
            ]);
    }

    public function test_admin_can_filter_webhook_events_by_status(): void
    {
        WebhookEvent::create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_1',
            'event_type' => 'invoice.paid',
            'payload' => ['id' => 'evt_1', 'type' => 'invoice.paid'],
            'status' => 'processed',
        ]);

        WebhookEvent::create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_2',
            'event_type' => 'invoice.payment_failed',
            'payload' => ['id' => 'evt_2', 'type' => 'invoice.payment_failed'],
            'status' => 'failed',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/webhooks?status=failed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider_event_id', 'evt_2');
    }

    public function test_admin_can_show_webhook_event(): void
    {
        $event = WebhookEvent::create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_1',
            'event_type' => 'invoice.paid',
            'payload' => ['id' => 'evt_1', 'type' => 'invoice.paid'],
            'status' => 'processed',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/webhooks/{$event->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.payload.id', 'evt_1');
    }

    public function test_admin_can_reprocess_webhook_event(): void
    {
        $event = WebhookEvent::create([
            'provider' => 'stripe',
            'provider_event_id' => 'evt_1',
            'event_type' => 'invoice.paid',
            'payload' => ['id' => 'evt_1', 'type' => 'invoice.paid'],
            'status' => 'failed',
            'error' => 'Previous failure',
            'attempts' => 1,
        ]);

        $this->mock(WebhookService::class, function (MockInterface $mock) {
            $mock->shouldReceive('processEvent')
                ->once();
        });

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/admin/webhooks/{$event->id}/reprocess");

        $response->assertOk()
            ->assertJsonPath('message', 'Webhook event re-processed successfully.');
    }
}
