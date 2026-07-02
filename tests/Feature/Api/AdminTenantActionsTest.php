<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\FailedPayment;
use App\Models\GlobalSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantActionsTest extends TestCase
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
        ]);
        $this->ownerToken = auth('api')->login($this->owner);
    }

    public function test_admin_can_create_customer_on_behalf_of_tenant(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/admin/users/{$this->owner->id}/customers", [
                'name' => 'John Doe On Behalf',
                'email' => 'johndoe@behalf.com',
                'phone' => '1234567890',
                'status' => 'active',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'John Doe On Behalf')
            ->assertJsonPath('data.user_id', $this->owner->id);

        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe On Behalf',
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_admin_can_create_plan_on_behalf_of_tenant(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/admin/users/{$this->owner->id}/plans", [
                'name' => 'Enterprise On Behalf',
                'price' => 199.99,
                'interval' => 'monthly',
                'currency' => 'USD',
                'trial_days' => 7,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Enterprise On Behalf')
            ->assertJsonPath('data.user_id', $this->owner->id);

        $this->assertDatabaseHas('plans', [
            'name' => 'Enterprise On Behalf',
            'user_id' => $this->owner->id,
            'price' => 199.99,
        ]);
    }

    public function test_admin_can_create_subscription_on_behalf_of_tenant(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->owner->id]);
        $plan = Plan::factory()->create(['user_id' => $this->owner->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/admin/users/{$this->owner->id}/subscriptions", [
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('subscriptions', [
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);
    }

    public function test_admin_cannot_create_subscription_with_mismatched_owner_resource(): void
    {
        $otherOwner = User::factory()->create(['role' => 'owner']);
        $customer = Customer::factory()->create(['user_id' => $this->owner->id]);
        $plan = Plan::factory()->create(['user_id' => $otherOwner->id]); // Plan belongs to other owner

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/admin/users/{$this->owner->id}/subscriptions", [
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_log_manual_payment_succeeded(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->owner->id]);
        $plan = Plan::factory()->create(['user_id' => $this->owner->id, 'interval' => 'monthly', 'currency' => 'USD']);
        
        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'past_due',
            'start_date' => now()->subMonth(),
            'next_billing_date' => now()->subDays(2),
        ]);

        // Insert unresolved failed payment
        $failedPayment = FailedPayment::create([
            'subscription_id' => $subscription->id,
            'amount' => 50.00,
            'currency' => 'USD',
            'reason' => 'Failed Stripe payment',
            'retry_count' => 1,
            'next_retry_at' => now()->addDay(),
        ]);

        // Change commission rate to 1.5%
        GlobalSetting::setByKey('platform_commission_rate', 1.5);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/admin/subscriptions/{$subscription->id}/payment", [
                'amount' => 100.00,
                'currency' => 'USD',
                'status' => 'succeeded',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount', '100.00')
            ->assertJsonPath('data.commission_rate', '0.0150')
            ->assertJsonPath('data.commission_amount', '1.50')
            ->assertJsonPath('data.provider', 'offline');

        $this->assertEquals('active', $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->next_billing_date);
        
        // Assert that the unresolved failed payment was marked as resolved
        $this->assertNotNull($failedPayment->fresh()->resolved_at);
        $this->assertEquals('payment_succeeded', $failedPayment->fresh()->resolution);
    }

    public function test_admin_can_log_manual_payment_failed(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->owner->id]);
        $plan = Plan::factory()->create(['user_id' => $this->owner->id]);
        
        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now(),
            'next_billing_date' => now()->addMonth(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/admin/subscriptions/{$subscription->id}/payment", [
                'amount' => 50.00,
                'currency' => 'USD',
                'status' => 'failed',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('past_due', $subscription->fresh()->status);

        $this->assertDatabaseHas('failed_payments', [
            'subscription_id' => $subscription->id,
            'amount' => 50.00,
            'reason' => 'Offline payment failed',
        ]);
    }
}
