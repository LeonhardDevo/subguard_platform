<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * List all business owners.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'owner')
            ->withCount([
                'customers',
                'plans',
                'subscriptions as total_subscriptions_count',
                'subscriptions as active_subscriptions_count' => function ($q) {
                    $q->whereIn('subscriptions.status', ['active', 'trialing']);
                }
            ]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $sanitizedSearch = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $query->where(function ($q) use ($sanitizedSearch) {
                $q->where('name', 'like', "%{$sanitizedSearch}%")
                  ->orWhere('email', 'like', "%{$sanitizedSearch}%")
                  ->orWhere('company_name', 'like', "%{$sanitizedSearch}%");
            });
        }

        $users = $query->paginate($request->input('per_page', 15));

        return response()->json($users);
    }

    /**
     * Show detailed user information.
     */
    public function show(User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Admin details cannot be retrieved via this endpoint.'], 403);
        }

        $user->loadCount([
            'customers',
            'plans',
            'subscriptions as total_subscriptions_count',
            'subscriptions as active_subscriptions_count' => function ($q) {
                $q->whereIn('subscriptions.status', ['active', 'trialing']);
            }
        ]);

        $user->load([
            'customers' => function ($q) {
                $q->orderBy('name', 'asc');
            },
            'plans' => function ($q) {
                $q->orderBy('name', 'asc');
            },
            'subscriptions' => function ($q) {
                $q->with(['customer', 'plan'])->orderBy('created_at', 'desc');
            }
        ]);

        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Override subscription limits.
     */
    public function updateLimit(Request $request, User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cannot override limit for admin accounts.'], 403);
        }

        $validated = $request->validate([
            'subscriber_limit' => 'required|integer|min:0',
        ]);

        $oldValues = ['subscriber_limit' => $user->subscriber_limit];
        $user->update([
            'subscriber_limit' => $validated['subscriber_limit']
        ]);

        AuditLog::log('admin.user.limit_updated', $user, $oldValues, $validated);

        return response()->json([
            'message' => 'Subscriber limit updated successfully.',
            'data' => $user
        ]);
    }

    /**
     * Toggle lock/unlock on a business owner account.
     */
    public function toggleLock(User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json(['message' => 'Cannot lock administrator accounts.'], 403);
        }

        $oldValues = ['is_locked' => $user->is_locked];
        $user->update([
            'is_locked' => !$user->is_locked
        ]);

        AuditLog::log('admin.user.lock_toggled', $user, $oldValues, ['is_locked' => $user->is_locked]);

        return response()->json([
            'message' => $user->is_locked ? 'Account has been locked.' : 'Account has been unlocked.',
            'data' => $user
        ]);
    }
}
