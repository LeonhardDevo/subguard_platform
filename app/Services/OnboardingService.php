<?php

namespace App\Services;

use App\Models\Onboarding;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class OnboardingService
{
    public function __construct(
        private readonly StripeService $stripeService
    ) {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function initiateStripeConnect(User $user): array
    {
        $existingOnboarding = Onboarding::where('user_id', $user->id)
            ->whereNotIn('status', ['completed'])
            ->first();

        if ($existingOnboarding && $existingOnboarding->stripe_account_id) {
            return $this->createAccountLink($existingOnboarding);
        }

        $account = Account::create([
            'type' => 'express',
            'email' => $user->email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'business_type' => 'individual',
            'metadata' => [
                'user_id' => $user->id,
            ],
        ]);

        $onboarding = Onboarding::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status' => 'pending',
                'stripe_account_id' => $account->id,
                'stripe_account_email' => $user->email,
            ]
        );

        return $this->createAccountLink($onboarding);
    }

    public function completeStripeConnect(User $user): Onboarding
    {
        $onboarding = Onboarding::where('user_id', $user->id)
            ->firstOrFail();

        if ($onboarding->stripe_account_id) {
            $account = Account::retrieve($onboarding->stripe_account_id);

            $onboarding->update([
                'stripe_onboarding_complete' => $account->details_submitted ?? false,
                'stripe_capabilities_enabled' => $account->capabilities->card_payments === 'active',
                'status' => $account->details_submitted ? 'stripe_connected' : $onboarding->status,
            ]);
        }

        return $onboarding->fresh();
    }

    public function getOnboardingStatus(User $user): ?Onboarding
    {
        return Onboarding::where('user_id', $user->id)->first();
    }

    public function isOnboardingComplete(User $user): bool
    {
        $onboarding = $this->getOnboardingStatus($user);

        return $onboarding && $onboarding->isCompleted();
    }

    private function createAccountLink(Onboarding $onboarding): array
    {
        $accountLink = AccountLink::create([
            'account' => $onboarding->stripe_account_id,
            'refresh_url' => config('app.frontend_url') . '/onboarding?refresh=true',
            'return_url' => config('app.frontend_url') . '/onboarding?account_id=' . $onboarding->stripe_account_id,
            'type' => 'account_onboarding',
        ]);

        return [
            'onboarding' => $onboarding,
            'account_link_url' => $accountLink->url,
            'expires_at' => $accountLink->expires_at,
        ];
    }
}
