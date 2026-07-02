<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\GlobalSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $adminToken;
    private User $owner;
    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->adminToken = auth('api')->login($this->admin);

        $this->owner = User::factory()->create([
            'role' => 'owner',
            'subscriber_limit' => 50, // default limit
        ]);
        $this->ownerToken = auth('api')->login($this->owner);
    }

    public function test_admin_can_retrieve_global_settings(): void
    {
        $this->withoutExceptionHandling();
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/settings');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'platform_commission_rate',
                    'default_free_tier_limit',
                    'maintenance_mode',
                    'stripe_mock_mode',
                ],
            ])
            ->assertJsonPath('data.platform_commission_rate', 1.0)
            ->assertJsonPath('data.default_free_tier_limit', 50)
            ->assertJsonPath('data.maintenance_mode', false)
            ->assertJsonPath('data.stripe_mock_mode', false);
    }

    public function test_admin_can_update_global_settings(): void
    {
        $this->withoutExceptionHandling();
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson('/api/admin/settings', [
                'platform_commission_rate' => 2.5,
                'default_free_tier_limit' => 10,
                'maintenance_mode' => true,
                'stripe_mock_mode' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.platform_commission_rate', 2.5)
            ->assertJsonPath('data.default_free_tier_limit', 10)
            ->assertJsonPath('data.maintenance_mode', true)
            ->assertJsonPath('data.stripe_mock_mode', true);

        $this->assertEquals(2.5, GlobalSetting::getByKey('platform_commission_rate'));
        $this->assertEquals(10, GlobalSetting::getByKey('default_free_tier_limit'));
        $this->assertTrue(GlobalSetting::getByKey('maintenance_mode'));
        $this->assertTrue(GlobalSetting::getByKey('stripe_mock_mode'));
    }

    public function test_maintenance_mode_blocks_owners_but_allows_admin(): void
    {
        // 1. Set maintenance mode to true
        GlobalSetting::setByKey('maintenance_mode', true);

        // 2. Try owner request -> should get 503
        $responseOwner = $this->withHeader('Authorization', "Bearer {$this->ownerToken}")
            ->getJson('/api/customers');
        $responseOwner->assertStatus(503)
            ->assertJsonPath('message', 'System is currently undergoing maintenance. Please try again later.');

        // 3. Try admin request -> should get 200
        $responseAdmin = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/users');
        $responseAdmin->assertOk();
    }

    public function test_global_subscriber_limit_is_enforced(): void
    {
        // 1. Update global default limit to 1
        GlobalSetting::setByKey('default_free_tier_limit', 1);

        // Create standard user with NULL subscriber_limit or 50 (standard default)
        // Wait, our fallback checks if userLimit === 50 and globalLimit !== 50, use globalLimit.
        $this->owner->update(['subscriber_limit' => 50]);

        $plan = Plan::factory()->create(['user_id' => $this->owner->id]);
        $customer1 = Customer::factory()->create(['user_id' => $this->owner->id, 'status' => 'active']);
        $customer2 = Customer::factory()->create(['user_id' => $this->owner->id, 'status' => 'active']);

        // Create first subscription -> should succeed
        $response1 = $this->withHeader('Authorization', "Bearer {$this->ownerToken}")
            ->postJson('/api/subscriptions', [
                'customer_id' => $customer1->id,
                'plan_id' => $plan->id,
            ]);
        $response1->assertStatus(201);

        // Create second subscription -> should fail
        $response2 = $this->withHeader('Authorization', "Bearer {$this->ownerToken}")
            ->postJson('/api/subscriptions', [
                'customer_id' => $customer2->id,
                'plan_id' => $plan->id,
            ]);
        $response2->assertStatus(422)
            ->assertJsonFragment(['message' => 'Free tier limit reached. You can manage up to 1 active subscribers on the free plan. Please upgrade to continue.']);
    }

    public function test_custom_commission_rate_is_applied_to_webhook_payments(): void
    {
        // 1. Set custom commission rate (e.g. 3.5%)
        GlobalSetting::setByKey('platform_commission_rate', 3.5);

        // Create customer, plan, subscription
        $customer = Customer::factory()->create([
            'user_id' => $this->owner->id,
            'stripe_customer_id' => 'cus_test123',
        ]);
        $plan = Plan::factory()->create([
            'user_id' => $this->owner->id,
            'stripe_price_id' => 'price_test123',
        ]);
        $subscription = \App\Models\Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now(),
            'next_billing_date' => now()->addMonth(),
            'provider_subscription_id' => 'sub_test123',
        ]);

        // Mock Webhook event data for invoice.paid
        $eventData = [
            'id' => 'evt_paid_test_' . uniqid(),
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'subscription' => 'sub_test123',
                    'amount_paid' => 10000, // $100.00
                    'currency' => 'usd',
                    'payment_intent' => 'pi_test123',
                    'lines' => [
                        'data' => [
                            [
                                'period' => [
                                    'end' => now()->addMonth()->timestamp,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Process webhook event using constructor
        $stripeEvent = \Stripe\Event::constructFrom($eventData);

        // Call WebhookService directly
        $webhookService = app(\App\Services\WebhookService::class);
        $webhookService->processEvent($stripeEvent);

        // Verify payment commission is calculated at 3.5%
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'amount' => 100.00,
            'commission_rate' => 0.035,
            'commission_amount' => 3.50,
            'status' => 'succeeded',
        ]);
    }
}
