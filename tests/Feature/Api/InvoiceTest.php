<?php

namespace Tests\Feature\Api;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Str;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        $customer = Customer::create([
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ]);

        $plan = Plan::create([
            'user_id' => $this->user->id,
            'name' => 'Pro Plan',
            'price' => 29.99,
            'currency' => 'USD',
            'interval' => 'monthly',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now(),
            'provider_subscription_id' => 'sub_' . Str::random(10),
        ]);

        $this->invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'number' => 'INV-001',
            'amount' => 29.99,
            'currency' => 'USD',
            'status' => 'paid',
            'due_date' => now(),
            'paid_at' => now(),
            'pdf_url' => 'https://example.com/inv.pdf',
        ]);
    }

    private function authHeader(): array
    {
        $token = auth('api')->login($this->user);
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_can_list_invoices(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/invoices');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_show_invoice(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->getJson("/api/invoices/{$this->invoice->id}");

        $response->assertOk();
        $this->assertEquals($this->invoice->id, $response->json('data.id'));
    }

    public function test_missing_invoice_returns_404(): void
    {
        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/invoices/9999');

        $response->assertStatus(404);
    }
}
