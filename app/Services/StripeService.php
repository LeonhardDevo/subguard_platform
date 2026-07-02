<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Facades\Log;
use Stripe\Customer as StripeCustomer;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function createCustomer(array $data): StripeCustomer
    {
        return StripeCustomer::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    public function createPrice(Plan $plan): StripePrice
    {
        // Create product first
        $product = StripeProduct::create([
            'name' => $plan->name,
            'description' => $plan->description ?? $plan->name,
        ]);

        $intervalMap = [
            'monthly' => 'month',
            'yearly' => 'year',
            'weekly' => 'week',
        ];

        return StripePrice::create([
            'product' => $product->id,
            'unit_amount' => (int) ($plan->price * 100),
            'currency' => strtolower($plan->currency),
            'recurring' => [
                'interval' => $intervalMap[$plan->interval] ?? 'month',
            ],
        ]);
    }

    public function createSubscription(
        string $stripeCustomerId,
        string $stripePriceId,
        int $trialDays = 0
    ): StripeSubscription {
        $params = [
            'customer' => $stripeCustomerId,
            'items' => [
                ['price' => $stripePriceId],
            ],
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
        ];

        if ($trialDays > 0) {
            $params['trial_period_days'] = $trialDays;
        }

        return StripeSubscription::create($params);
    }

    public function cancelSubscription(string $stripeSubscriptionId): StripeSubscription
    {
        $subscription = StripeSubscription::retrieve($stripeSubscriptionId);
        return $subscription->cancel();
    }

    public function retryInvoicePayment(string $invoiceId): \Stripe\Invoice
    {
        $invoice = \Stripe\Invoice::retrieve($invoiceId);
        return $invoice->pay();
    }

    public function constructWebhookEvent(string $payload, string $sigHeader): \Stripe\Event
    {
        return Webhook::constructEvent(
            $payload,
            $sigHeader,
            config('services.stripe.webhook_secret')
        );
    }

    public function createBillingPortalSession(string $customerId, string $returnUrl, ?string $configuration = null): \Stripe\BillingPortal\Session
    {
        $params = [
            "customer" => $customerId,
            "return_url" => $returnUrl,
        ];
        if ($configuration) {
            $params["configuration"] = $configuration;
        }
        return \Stripe\BillingPortal\Session::create($params);
    }

    public function getCustomer(string $stripeCustomerId): StripeCustomer
    {
        return StripeCustomer::retrieve($stripeCustomerId);
    }

    public function getSubscription(string $stripeSubscriptionId): StripeSubscription
    {
        return StripeSubscription::retrieve($stripeSubscriptionId);
    }

    public function createCheckoutSession(
        string $stripeCustomerId,
        string $stripePriceId,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): \Stripe\Checkout\Session {
        return \Stripe\Checkout\Session::create([
            'customer' => $stripeCustomerId,
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price' => $stripePriceId,
                    'quantity' => 1,
                ],
            ],
            'mode' => 'subscription',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => $metadata,
        ]);
    }

    public function createCustomerPortalSession(
        string $customerId,
        string $returnUrl,
        ?string $configuration = null
    ): \Stripe\BillingPortal\Session {
        $params = [
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ];
        if ($configuration) {
            $params['configuration'] = $configuration;
        }
        return \Stripe\BillingPortal\Session::create($params);
    }

    public function createSetupIntent(string $stripeCustomerId): \Stripe\SetupIntent
    {
        return \Stripe\SetupIntent::create([
            'customer' => $stripeCustomerId,
            'payment_method_types' => ['card'],
        ]);
    }
}
