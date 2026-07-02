<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
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

    public function test_can_list_plans(): void
    {
        Plan::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/plans');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_plan(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/plans', [
                'name' => 'Pro Plan',
                'price' => 29.99,
                'currency' => 'USD',
                'interval' => 'monthly',
                'trial_days' => 14,
                'description' => 'Professional plan',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Pro Plan')
            ->assertJsonPath('data.price', '29.99');
    }

    public function test_can_update_plan(): void
    {
        $plan = Plan::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders($this->authHeader())
            ->putJson("/api/plans/{$plan->id}", [
                'name' => 'Updated Plan',
                'price' => 49.99,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Plan');
    }

    public function test_can_delete_plan(): void
    {
        $plan = Plan::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/plans/{$plan->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/plans', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price']);
    }

    public function test_cannot_access_other_users_plan(): void
    {
        $otherPlan = Plan::factory()->create();

        $response = $this->withHeaders($this->authHeader())
            ->getJson("/api/plans/{$otherPlan->id}");

        $response->assertStatus(403);
    }
}
