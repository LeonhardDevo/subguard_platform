<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebhookService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService,
        private readonly StripeService $stripeService
    ) {}

    public function handleStripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (!$sigHeader) {
            return response()->json(['error' => 'Missing signature'], 400);
        }

        try {
            $event = $this->stripeService->constructWebhookEvent($payload, $sigHeader);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Invalid Stripe webhook signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook error'], 400);
        }

        try {
            $this->webhookService->processEvent($event);
            return response()->json(['status' => 'processed']);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
}
