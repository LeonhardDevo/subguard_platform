<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService
    ) {}

    public function stripeConnect(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->onboardingService->initiateStripeConnect($user);

        return response()->json([
            'message' => 'Stripe Connect onboarding initiated',
            'data' => [
                'onboarding_id' => $result['onboarding']->id,
                'account_link_url' => $result['account_link_url'],
                'expires_at' => $result['expires_at'],
            ],
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $onboarding = $this->onboardingService->completeStripeConnect($user);

        return response()->json([
            'message' => 'Onboarding status updated',
            'data' => [
                'onboarding' => $onboarding,
            ],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $onboarding = $this->onboardingService->getOnboardingStatus($user);

        return response()->json([
            'data' => [
                'onboarding' => $onboarding,
                'is_complete' => $this->onboardingService->isOnboardingComplete($user),
            ],
        ]);
    }
}
