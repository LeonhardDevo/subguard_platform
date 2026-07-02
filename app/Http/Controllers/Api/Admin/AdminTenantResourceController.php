<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Payment;
use App\Services\SubscriptionService;
use App\Events\PaymentSucceeded;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTenantResourceController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {}

    /**
     * Create a customer on behalf of a tenant.
     */
    public function storeCustomer(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'external_id' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $customer = Customer::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'external_id' => $validated['external_id'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Customer created successfully.',
            'data' => $customer
        ], 201);
    }

    /**
     * Create a plan on behalf of a tenant.
     */
    public function storePlan(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'interval' => 'required|string|in:weekly,monthly,yearly',
            'trial_days' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'features' => 'nullable|array',
        ]);

        $plan = Plan::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'price' => $validated['price'],
            'currency' => strtoupper($validated['currency']),
            'interval' => $validated['interval'],
            'trial_days' => $validated['trial_days'] ?? 0,
            'description' => $validated['description'] ?? null,
            'features' => $validated['features'] ?? [],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Plan created successfully.',
            'data' => $plan
        ], 201);
    }

    /**
     * Create a subscription on behalf of a tenant.
     */
    public function storeSubscription(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'plan_id' => 'required|integer',
        ]);

        $customer = Customer::where('user_id', $user->id)->find($validated['customer_id']);
        $plan = Plan::where('user_id', $user->id)->find($validated['plan_id']);

        if (!$customer || !$plan) {
            return response()->json([
                'message' => 'The selected customer or plan is invalid for this user.',
                'errors' => array_filter([
                    'customer_id' => !$customer ? ['The selected customer is invalid.'] : null,
                    'plan_id' => !$plan ? ['The selected plan is invalid.'] : null,
                ])
            ], 422);
        }

        $subscription = $this->subscriptionService->create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);

        return response()->json([
            'message' => 'Subscription created successfully.',
            'data' => $subscription
        ], 201);
    }

    /**
     * Record a manual payment on behalf of a subscription.
     */
    public function storePayment(Request $request, Subscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'status' => 'nullable|string|in:succeeded,failed',
        ]);

        $subscription->load('customer');

        if (!empty($validated['user_id'])) {
            $targetUser = User::findOrFail($validated['user_id']);
            if ($targetUser->role === 'admin') {
                return response()->json(['message' => 'Cannot record payment for admin accounts.'], 403);
            }
            if ($subscription->customer->user_id !== $targetUser->id) {
                return response()->json(['message' => 'Subscription does not belong to the specified user.'], 403);
            }
        } else {
            $targetUser = $subscription->customer->user;
            if (!$targetUser) {
                return response()->json(['message' => 'Could not determine the user for this subscription.'], 422);
            }
        }

        $status = $request->input('status', 'succeeded');
        $currency = strtoupper($validated['currency'] ?? $subscription->plan->currency ?? 'USD');

        if ($status === 'failed') {
            return DB::transaction(function () use ($validated, $subscription, $currency) {
                $subscription->update([
                    'status' => 'past_due',
                ]);

                $failedPayment = \App\Models\FailedPayment::create([
                    'subscription_id' => $subscription->id,
                    'amount' => $validated['amount'],
                    'currency' => $currency,
                    'reason' => 'Offline payment failed',
                    'retry_count' => 0,
                ]);

                return response()->json([
                    'message' => 'Manual payment failure recorded successfully.',
                    'data' => $failedPayment
                ], 201);
            });
        }

        return DB::transaction(function () use ($validated, $subscription, $currency, $targetUser) {
            // Calculate platform commission rate from system settings (default 1%)
            $commissionRate = ((float) \App\Models\GlobalSetting::getByKey('platform_commission_rate', 1.0)) / 100;
            $commissionAmount = round($validated['amount'] * $commissionRate, 2);

            $payment = Payment::create([
                'subscription_id' => $subscription->id,
                'amount' => $validated['amount'],
                'commission_amount' => $commissionAmount,
                'commission_rate' => $commissionRate,
                'currency' => $currency,
                'status' => 'succeeded',
                'provider' => 'offline',
                'provider_payment_id' => 'offline_' . uniqid(),
            ]);

            // Update subscription period & billing dates
            $plan = $subscription->plan;
            $nextBillingDate = $plan->interval === 'monthly' 
                ? now()->addMonth() 
                : ($plan->interval === 'yearly' ? now()->addYear() : now()->addWeek());

            $subscription->update([
                'status' => 'active',
                'next_billing_date' => $nextBillingDate,
            ]);

            // Mark any unresolved failed payments on this subscription as resolved
            \App\Models\FailedPayment::where('subscription_id', $subscription->id)
                ->whereNull('resolved_at')
                ->update([
                    'resolved_at' => now(),
                    'resolution' => 'payment_succeeded',
                ]);

            AuditLog::log('admin.payment.recorded', $payment, null, [
                'admin_id' => auth()->id(),
                'user_id' => $targetUser->id,
                'subscription_id' => $subscription->id,
                'amount' => $validated['amount'],
                'currency' => $currency,
            ]);

            event(new PaymentSucceeded($payment));

            return response()->json([
                'message' => 'Manual payment recorded successfully.',
                'data' => $payment
            ], 201);
        });
    }
}
