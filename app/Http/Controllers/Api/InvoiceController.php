<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $invoices = $this->invoiceService->list(
            auth()->id(),
            $request->only(['status', 'sort_by', 'sort_dir', 'per_page'])
        );

        return response()->json($invoices);
    }

    public function show(int $id): JsonResponse
    {
        $invoice = $this->invoiceService->find($id, auth()->id());

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        return response()->json([
            'data' => $invoice,
        ]);
    }

    public function download(int $id)
    {
        $invoice = $this->invoiceService->find($id, auth()->id());

        if (!$invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        $path = $this->invoiceService->downloadPdf($invoice);

        if (!$path || !Storage::disk('s3')->exists($path)) {
            return response()->json(['message' => 'PDF not available'], 404);
        }

        return Storage::disk('s3')->download($path, $invoice->number . '.pdf');
    }
}
