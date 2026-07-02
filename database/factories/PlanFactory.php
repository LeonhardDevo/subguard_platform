<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Basic', 'Pro', 'Enterprise', 'Starter', 'Premium']),
            'price' => fake()->randomFloat(2, 9.99, 299.99),
            'currency' => 'USD',
            'interval' => fake()->randomElement(['monthly', 'yearly']),
            'trial_days' => fake()->randomElement([0, 7, 14, 30]),
            'is_active' => true,
            'description' => fake()->sentence(),
            'features' => ['Feature 1', 'Feature 2', 'Feature 3'],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => ['interval' => 'monthly']);
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => ['interval' => 'yearly']);
    }
}
