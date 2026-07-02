<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    private function authHeader(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_can_list_subscriptions(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $plan = Plan::factory()->create(['user_id' => $this->user->id]);

        Subscription::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/subscriptions');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_subscription(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $plan = Plan::factory()->create(['user_id' => $this->user->id, 'trial_days' => 0]);

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/subscriptions', [
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('subscriptions', [
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    public function test_subscription_with_trial_starts_as_trialing(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $plan = Plan::factory()->create(['user_id' => $this->user->id, 'trial_days' => 14]);

        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/subscriptions', [
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'trialing');
    }

    public function test_can_cancel_subscription(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $plan = Plan::factory()->create(['user_id' => $this->user->id]);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->putJson("/api/subscriptions/{$subscription->id}/cancel", [
                'reason' => 'No longer needed',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'canceled');
    }

    public function test_can_filter_subscriptions_by_status(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $plan = Plan::factory()->create(['user_id' => $this->user->id]);

        Subscription::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        Subscription::factory()->canceled()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/subscriptions?status=active');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/subscriptions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'plan_id']);
    }
}
