<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Event as StripeEvent;

class AdminWebhookController extends Controller
{
    /**
     * List all logged webhook events.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WebhookEvent::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('provider_event_id', 'like', "%{$search}%")
                  ->orWhere('event_type', 'like', "%{$search}%")
                  ->orWhere('error', 'like', "%{$search}%");
            });
        }

        $events = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($events);
    }

    /**
     * Show details of a specific webhook event.
     */
    public function show(WebhookEvent $event): JsonResponse
    {
        return response()->json([
            'data' => $event
        ]);
    }

    /**
     * Re-process a webhook event manually.
     */
    public function reprocess(Request $request, WebhookEvent $event, WebhookService $webhookService): JsonResponse
    {
        try {
            // Reconstruct the Stripe Event object from the stored payload
            $stripeEvent = StripeEvent::constructFrom($event->payload);

            // Temporarily set status to bypass WebhookService's processed idempotency check
            $event->update([
                'status' => 'processing',
                'error' => null,
            ]);

            // Call WebhookService to process the event
            $webhookService->processEvent($stripeEvent);

            AuditLog::log('admin.webhook.reprocessed', $event, null, [
                'admin_id' => auth()->id(),
                'event_type' => $event->event_type,
                'provider_event_id' => $event->provider_event_id,
            ]);

            // Refresh model to return updated values
            $event->refresh();

            return response()->json([
                'message' => 'Webhook event re-processed successfully.',
                'data' => $event
            ]);
        } catch (\Exception $e) {
            // Restore failure status if processEvent fails and doesn't update it
            $event->markFailed($e->getMessage());

            AuditLog::log('admin.webhook.reprocess_failed', $event, null, [
                'admin_id' => auth()->id(),
                'error' => 'Reprocess failed',
            ]);

            return response()->json([
                'message' => 'Failed to re-process webhook event.',
                'error' => 'Reprocess failed'
            ], 500);
        }
    }
}
