<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customers = $this->customerService->list(
            auth()->id(),
            $request->only(['status', 'search', 'sort_by', 'sort_dir', 'per_page'])
        );

        return response()->json($customers);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->create(auth()->id(), $request->validated());

        return response()->json([
            'message' => 'Customer created successfully',
            'data' => $customer,
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json([
            'data' => $customer->load('subscriptions.plan'),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $customer = $this->customerService->update($customer, $request->validated());

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer,
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $this->customerService->delete($customer);

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $csvContent = file_get_contents($request->file('file')->getPathname());
        $result = $this->customerService->importCsv(auth()->id(), $csvContent);

        return response()->json([
            'message' => "Import completed. {$result['imported']} customers imported.",
            'data' => $result,
        ]);
    }
}
