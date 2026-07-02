<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly CommissionService $commissionService
    ) {}

    public function subscription(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($request->plan_id);

        if (!$user->stripe_customer_id) {
            $customer = $this->stripeService->createCustomer([
                'name' => $user->name,
                'email' => $user->email,
                'metadata' => ['user_id' => $user->id],
            ]);
            $user->update(['stripe_customer_id' => $customer->id]);
        }

        if (!$plan->stripe_price_id) {
            $price = $this->stripeService->createPrice($plan);
            $plan->update(['stripe_price_id' => $price->id]);
        }

        $session = $this->stripeService->createCheckoutSession(
            $user->stripe_customer_id,
            $plan->stripe_price_id,
            $request->success_url,
            $request->cancel_url,
            [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ]
        );

        return response()->json([
            'message' => 'Checkout session created',
            'data' => [
                'session_id' => $session->id,
                'url' => $session->url,
            ],
        ]);
    }

    public function portal(Request $request): JsonResponse
    {
        $request->validate([
            'return_url' => 'required|url',
        ]);

        $user = $request->user();

        if (!$user->stripe_customer_id) {
            return response()->json([
                'message' => 'No Stripe customer found',
            ], 400);
        }

        $session = $this->stripeService->createCustomerPortalSession(
            $user->stripe_customer_id,
            $request->return_url
        );

        return response()->json([
            'message' => 'Customer portal session created',
            'data' => [
                'url' => $session->url,
            ],
        ]);
    }
}
