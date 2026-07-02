<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
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

    public function test_admin_can_list_business_owners(): void
    {
        // Create standard owners
        User::factory()->count(3)->create(['role' => 'owner']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/users');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'customers_count',
                        'plans_count',
                        'total_subscriptions_count',
                        'active_subscriptions_count',
                    ]
                ]
            ]);
    }

    public function test_admin_can_list_owners_with_search(): void
    {
        User::factory()->create(['name' => 'John Doe', 'role' => 'owner']);
        User::factory()->create(['name' => 'Jane Smith', 'role' => 'owner']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/admin/users?search=Jane');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jane Smith');
    }

    public function test_admin_can_get_user_details(): void
    {
        $user = User::factory()->create(['role' => 'owner']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'customers_count',
                    'plans_count',
                    'total_subscriptions_count',
                    'active_subscriptions_count',
                ]
            ]);
    }

    public function test_admin_cannot_view_another_admin_details(): void
    {
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/admin/users/{$otherAdmin->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_override_limitations(): void
    {
        $user = User::factory()->create([
            'role' => 'owner',
            'subscriber_limit' => 50,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/admin/users/{$user->id}/limit", [
                'subscriber_limit' => 2,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.subscriber_limit', 2);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'subscriber_limit' => 2,
        ]);
    }

    public function test_overridden_limitations_are_enforced_for_subscriptions(): void
    {
        // 1. Create owner and set limit to 1
        $user = User::factory()->create([
            'role' => 'owner',
            'subscriber_limit' => 1,
        ]);

        // Create plan and customers for this owner
        $plan = Plan::factory()->create(['user_id' => $user->id]);
        $customer1 = Customer::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $customer2 = Customer::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        // Log in as the owner
        $userToken = auth('api')->login($user);

        // 2. Create the first subscription (limit is 1, so this should succeed)
        $response1 = $this->withHeader('Authorization', "Bearer {$userToken}")
            ->postJson('/api/subscriptions', [
                'customer_id' => $customer1->id,
                'plan_id' => $plan->id,
            ]);

        $response1->assertStatus(201);

        // 3. Create the second subscription (should fail because limit is 1)
        $response2 = $this->withHeader('Authorization', "Bearer {$userToken}")
            ->postJson('/api/subscriptions', [
                'customer_id' => $customer2->id,
                'plan_id' => $plan->id,
            ]);

        $response2->assertStatus(422)
            ->assertJsonPath('message', 'Free tier limit reached. You can manage up to 1 active subscribers on the free plan. Please upgrade to continue.');
    }

    public function test_admin_can_toggle_locking_accounts(): void
    {
        $user = User::factory()->create([
            'role' => 'owner',
            'is_locked' => false,
        ]);

        // Lock
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/admin/users/{$user->id}/toggle-lock");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', true);

        $this->assertTrue($user->fresh()->is_locked);

        // Unlock
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/admin/users/{$user->id}/toggle-lock");

        $response->assertOk()
            ->assertJsonPath('data.is_locked', false);

        $this->assertFalse($user->fresh()->is_locked);
    }
}
