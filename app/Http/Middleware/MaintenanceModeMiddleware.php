<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\GlobalSetting;

class MaintenanceModeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (GlobalSetting::getByKey('maintenance_mode') === true) {
            $user = $request->user();
            if (!$user || $user->role !== 'admin') {
                return response()->json([
                    'message' => 'System is currently undergoing maintenance. Please try again later.',
                ], 503);
            }
        }

        return $next($request);
    }
}
