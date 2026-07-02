<?php

namespace Tests\Feature\Api;

use App\Services\StripeService;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Stripe\Event;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    public function test_webhook_fails_without_signature(): void
    {
        $response = $this->postJson('/api/webhooks/stripe', []);
            
        $response->assertStatus(400);
        $response->assertJson(['error' => 'Missing signature']);
    }

    public function test_webhook_fails_with_invalid_signature(): void
    {
        $this->mock(StripeService::class, function (MockInterface $mock) {
            $mock->shouldReceive('constructWebhookEvent')
                ->andThrow(new \Stripe\Exception\SignatureVerificationException('Invalid sig'));
        });

        $response = $this->withHeaders(['Stripe-Signature' => 'invalid'])
            ->postJson('/api/webhooks/stripe', []);
            
        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    public function test_webhook_processes_valid_event(): void
    {
        $event = new Event(['id' => 'evt_123', 'type' => 'customer.subscription.deleted']);

        $this->mock(StripeService::class, function (MockInterface $mock) use ($event) {
            $mock->shouldReceive('constructWebhookEvent')
                ->once()
                ->andReturn($event);
        });

        $this->mock(WebhookService::class, function (MockInterface $mock) use ($event) {
            $mock->shouldReceive('processEvent')
                ->once()
                ->with($event);
        });

        $response = $this->withHeaders(['Stripe-Signature' => 'valid'])
            ->postJson('/api/webhooks/stripe', []);
            
        $response->assertOk();
        $response->assertJson(['status' => 'processed']);
    }
}
