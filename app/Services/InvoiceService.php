<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function list(int $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::whereHas('subscription.customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['subscription.customer', 'subscription.plan']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $allowedSorts = ['created_at', 'number', 'amount', 'status', 'due_date'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc']) ? $filters['sort_dir'] : 'desc';
        $perPage = min($filters['per_page'] ?? 15, 100);

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    public function find(int $id, int $userId): ?Invoice
    {
        return Invoice::whereHas('subscription.customer', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['subscription.customer', 'subscription.plan'])->find($id);
    }

    public function generate(Subscription $subscription, Payment $payment): Invoice
    {
        $invoice = Invoice::create([
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
            'number' => Invoice::generateNumber(),
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status === 'succeeded' ? 'paid' : 'sent',
            'due_date' => now(),
            'paid_at' => $payment->status === 'succeeded' ? now() : null,
        ]);

        // Generate PDF
        $this->generatePdf($invoice);

        return $invoice;
    }

    public function generatePdf(Invoice $invoice): string
    {
        $invoice->load(['subscription.customer', 'subscription.plan']);

        $pdf = Pdf::loadView('invoices.template', [
            'invoice' => $invoice,
            'customer' => $invoice->subscription->customer,
            'plan' => $invoice->subscription->plan,
        ]);

        $path = "invoices/{$invoice->number}.pdf";

        Storage::disk('s3')->put($path, $pdf->output());

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    public function downloadPdf(Invoice $invoice): ?string
    {
        if (!$invoice->pdf_path || !Storage::disk('s3')->exists($invoice->pdf_path)) {
            $this->generatePdf($invoice);
            $invoice->refresh();
        }

        return $invoice->pdf_path;
    }
}
