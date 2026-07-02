<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'start_date' => now(),
            'next_billing_date' => now()->addMonth(),
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'next_billing_date' => now()->addDays(14),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'canceled_at' => now(),
            'cancel_reason' => 'User requested cancellation',
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'past_due',
        ]);
    }
}
