<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function view(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->customer->user_id;
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->customer->user_id;
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->customer->user_id;
    }
}
