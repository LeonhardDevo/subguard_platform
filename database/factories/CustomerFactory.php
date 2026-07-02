<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'external_id' => fake()->optional()->uuid(),
            'status' => fake()->randomElement(['active', 'inactive', 'blocked']),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'blocked']);
    }
}
