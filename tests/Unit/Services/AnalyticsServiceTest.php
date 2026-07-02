<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnalyticsService();
        $this->user = User::factory()->create();
    }

    public function test_calculates_mrr_for_monthly_plans(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $plan = Plan::factory()->create([
            'user_id' => $this->user->id,
            'price' => 29.99,
            'interval' => 'monthly',
        ]);

        Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $mrr = $this->service->calculateMRR($this->user->id);

        $this->assertEquals(29.99, $mrr);
    }

    public function test_calculates_mrr_for_yearly_plans(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $plan = Plan::factory()->create([
            'user_id' => $this->user->id,
            'price' => 120.00,
            'interval' => 'yearly',
        ]);

        Subscription::factory()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $mrr = $this->service->calculateMRR($this->user->id);

        $this->assertEquals(10.00, $mrr);
    }

    public function test_returns_zero_mrr_with_no_subscriptions(): void
    {
        $mrr = $this->service->calculateMRR($this->user->id);

        $this->assertEquals(0, $mrr);
    }

    public function test_counts_active_subscribers(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $plan = Plan::factory()->create(['user_id' => $this->user->id]);

        Subscription::factory()->count(3)->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        Subscription::factory()->canceled()->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);

        $count = $this->service->getActiveSubscribers($this->user->id);

        $this->assertEquals(3, $count);
    }

    public function test_calculates_churn_rate(): void
    {
        $customer = Customer::factory()->create(['user_id' => $this->user->id]);
        $plan = Plan::factory()->create(['user_id' => $this->user->id]);

        // Active subscriptions started before this month
        Subscription::factory()->count(10)->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonths(2),
        ]);

        // Canceled this month
        Subscription::factory()->count(2)->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'canceled',
            'start_date' => now()->subMonths(2),
            'canceled_at' => now(),
        ]);

        $churnRate = $this->service->calculateChurnRate($this->user->id);

        $this->assertGreaterThan(0, $churnRate);
    }

    public function test_dashboard_returns_all_metrics(): void
    {
        $dashboard = $this->service->getDashboard($this->user->id);

        $this->assertArrayHasKey('mrr', $dashboard);
        $this->assertArrayHasKey('arr', $dashboard);
        $this->assertArrayHasKey('active_subscribers', $dashboard);
        $this->assertArrayHasKey('churn_rate', $dashboard);
        $this->assertArrayHasKey('ltv', $dashboard);
        $this->assertArrayHasKey('total_revenue', $dashboard);
        $this->assertArrayHasKey('subscriber_growth', $dashboard);
    }
}
