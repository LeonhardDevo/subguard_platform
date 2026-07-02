<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionRequest;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptions = $this->subscriptionService->list(
            auth()->id(),
            $request->only(['status', 'customer_id', 'sort_by', 'sort_dir', 'per_page'])
        );

        return response()->json($subscriptions);
    }

    public function store(SubscriptionRequest $request): JsonResponse
    {
        try {
            $subscription = $this->subscriptionService->create($request->validated());

            return response()->json([
                'message' => 'Subscription created successfully',
                'data' => $subscription,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Subscription $subscription): JsonResponse
    {
        $this->authorize('view', $subscription);

        return response()->json([
            'data' => $subscription->load(['customer', 'plan', 'payments']),
        ]);
    }

    public function portalSession(Request $request): JsonResponse
    {
        $request->validate([
            "return_url" => "required|url|max:255",
        ]);

        $user = auth()->user();
        if (!$user->stripe_customer_id) {
            return response()->json([
                "message" => "No Stripe customer account linked to your account.",
            ], 422);
        }

        $returnUrl = $request->input("return_url");

        try {
            $session = app(\App\Services\StripeService::class)->createBillingPortalSession(
                $user->stripe_customer_id,
                $returnUrl
            );

            return response()->json([
                "message" => "Portal session created.",
                "data" => [
                    "url" => $session->url,
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Stripe portal session failed", [
                "user_id" => $user->id,
                "error" => $e->getMessage(),
            ]);
            return response()->json([
                "message" => "Unable to launch billing portal. Please try again later.",
            ], 500);
        }
    }

    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        $this->authorize('cancel', $subscription);

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $subscription = $this->subscriptionService->cancel(
            $subscription,
            $request->input('reason')
        );

        return response()->json([
            'message' => 'Subscription canceled successfully',
            'data' => $subscription,
        ]);
    }
}
