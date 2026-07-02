<?php

namespace App\Http\Middleware;

use App\Support\Totp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Routes that are exempt from 2FA validation (setup / confirm / disable flows).
     */
    private const TWO_FACTOR_EXEMPT_ROUTES = [
        'admin/2fa/setup',
        'admin/2fa/confirm',
        'admin/2fa/disable',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden. Admin access required.',
            ], 403);
        }

        // Skip 2FA check when user hasn't enabled it.
        if (! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        // Skip 2FA check for the setup / confirm / disable routes themselves.
        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        // Require a valid 6-digit code.
        $code = $request->header('X-2FA-Code');

        if (empty($code)) {
            return response()->json([
                'message' => 'Two-factor authentication code required.',
            ], 401);
        }

        $secret = decrypt($user->two_factor_secret);

        if (! Totp::verify($secret, $code)) {
            return response()->json([
                'message' => 'Two-factor authentication code is invalid.',
            ], 401);
        }

        return $next($request);
    }

    private function isExemptRoute(Request $request): bool
    {
        $path = rtrim($request->path(), '/');

        foreach (self::TWO_FACTOR_EXEMPT_ROUTES as $route) {
            if ($path === $route) {
                return true;
            }
        }

        return false;
    }
}
