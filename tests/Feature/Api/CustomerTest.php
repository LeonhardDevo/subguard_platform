<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
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

    public function test_can_list_customers(): void
    {
        Customer::factory()->count(5)->create(['user_id' => $this->user->id]);
        Customer::factory()->count(3)->create(); // Other user's customers

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/customers');

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_create_customer(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/customers', [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '+1234567890',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Jane Smith');

        $this->assertDatabaseHas('customers', [
            'email' => 'jane@example.com',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders($this->authHeader())
            ->putJson("/api/customers/{$customer->id}", [
                'name' => 'Updated Name',
                'email' => $customer->email,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_can_delete_customer(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);

        $response = $this->withHeaders($this->authHeader())
            ->deleteJson("/api/customers/{$customer->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_cannot_access_other_users_customer(): void
    {
        $otherCustomer = Customer::factory()->create();

        $response = $this->withHeaders($this->authHeader())
            ->getJson("/api/customers/{$otherCustomer->id}");

        $response->assertStatus(403);
    }

    public function test_can_filter_customers_by_status(): void
    {
        Customer::factory()->count(3)->create(['user_id' => $this->user->id, 'status' => 'active']);
        Customer::factory()->count(2)->create(['user_id' => $this->user->id, 'status' => 'blocked']);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/customers?status=active');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_search_customers(): void
    {
        Customer::factory()->create(['user_id' => $this->user->id, 'name' => 'John Doe']);
        Customer::factory()->create(['user_id' => $this->user->id, 'name' => 'Jane Smith']);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/customers?search=John');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->postJson('/api/customers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }
}
