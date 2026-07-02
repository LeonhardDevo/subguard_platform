<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_cannot_access_admin_endpoints(): void
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(401);
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create([
            'role' => 'owner',
            'is_locked' => false,
        ]);
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/users');

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden. Admin access required.');
    }

    public function test_admin_can_access_admin_endpoints(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_locked' => false,
        ]);
        $token = auth('api')->login($admin);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/users');

        $response->assertOk();
    }

    public function test_locked_user_cannot_access_protected_endpoints(): void
    {
        $user = User::factory()->create([
            'role' => 'owner',
            'is_locked' => true,
        ]);
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Your account has been locked. Please contact support.');
    }
}
