<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'amount' => fake()->randomFloat(2, 9.99, 299.99),
            'currency' => 'USD',
            'status' => 'succeeded',
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_' . fake()->unique()->uuid(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'failed']);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending']);
    }
}
