<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function authHeader(): array
    {
        $token = auth('api')->login($this->user);
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_can_get_analytics_dashboard(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/analytics/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'mrr',
                'arr',
                'active_subscribers',
                'churn_rate',
                'ltv',
                'total_revenue',
                'new_subscribers_this_month',
                'canceled_this_month'
            ]
        ]);
    }

    public function test_unauthenticated_cannot_get_dashboard(): void
    {
        $response = $this->getJson('/api/analytics/dashboard');
        $response->assertStatus(401);
    }
}
