<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequest;
use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $planService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $plans = $this->planService->list(
            auth()->id(),
            $request->only(['is_active', 'search', 'sort_by', 'sort_dir', 'per_page'])
        );

        return response()->json($plans);
    }

    public function store(PlanRequest $request): JsonResponse
    {
        $plan = $this->planService->create(auth()->id(), $request->validated());

        return response()->json([
            'message' => 'Plan created successfully',
            'data' => $plan,
        ], 201);
    }

    public function show(Plan $plan): JsonResponse
    {
        $this->authorize('view', $plan);

        return response()->json([
            'data' => $plan,
        ]);
    }

    public function update(PlanRequest $request, Plan $plan): JsonResponse
    {
        $this->authorize('update', $plan);

        $plan = $this->planService->update($plan, $request->validated());

        return response()->json([
            'message' => 'Plan updated successfully',
            'data' => $plan,
        ]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $this->authorize('delete', $plan);

        try {
            $this->planService->delete($plan);

            return response()->json([
                'message' => 'Plan deleted successfully',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
