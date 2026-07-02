<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'payment_id' => Payment::factory(),
            'number' => 'INV-' . date('Ym') . '-' . str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'amount' => fake()->randomFloat(2, 9.99, 299.99),
            'currency' => 'USD',
            'status' => 'paid',
            'due_date' => now(),
            'paid_at' => now(),
        ];
    }
}
