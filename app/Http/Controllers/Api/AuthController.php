<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'message' => 'Registration successful',
            'data' => $result,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if (!$result) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'data' => $result,
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    public function refresh(): JsonResponse
    {
        $result = $this->authService->refresh();

        return response()->json([
            'message' => 'Token refreshed',
            'data' => $result,
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'data' => $this->authService->me(),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = $this->authService->sendPasswordResetLink($request->email);

        return response()->json([
            'message' => 'If the email exists, a reset link has been sent.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetPassword($request->validated());

        if ($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password reset successful']);
        }

        return response()->json(['message' => 'Password reset failed'], 400);
    }
}
