<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Http\Controllers;

use DigitalizeLab\CashiPay\Contracts\CashiPayInterface;
use DigitalizeLab\CashiPay\Events\PaymentCompleted;
use DigitalizeLab\CashiPay\Events\PaymentFailed;
use DigitalizeLab\CashiPay\Events\WebhookReceived;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles inbound CashiPay webhook notifications.
 *
 * Webhook idempotency: this controller always returns HTTP 200 so that
 * CashiPay does not retry a delivery on application-side processing errors.
 * Fire-and-forget event dispatching means the controller cannot be responsible
 * for downstream failures.
 */
final class WebhookController extends Controller
{
    private CashiPayInterface $cashiPay;
    private Dispatcher $events;
    private ConfigRepository $config;

    public function __construct(
        CashiPayInterface $cashiPay,
        Dispatcher $events,
        ConfigRepository $config,
    ) {
        $this->cashiPay = $cashiPay;
        $this->events   = $events;
        $this->config   = $config;
    }

    /**
     * Process an inbound CashiPay webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        if (empty($payload)) {
            Log::warning('[CashiPay] Received empty or non-JSON webhook payload.', [
                'body' => $request->getContent(),
            ]);

            return response()->json(['received' => true]);
        }

        Log::info('[CashiPay] Webhook received.', [
            'event'  => $payload['event'] ?? 'unknown',
            'ref'    => $payload['referenceNumber'] ?? $payload['reference_number'] ?? null,
        ]);

        try {
            // Always dispatch the generic event first.
            $this->events->dispatch(new WebhookReceived($payload));

            $this->dispatchSpecificEvent($payload);
        } catch (Throwable $e) {
            Log::error('[CashiPay] Exception while dispatching webhook events.', [
                'exception' => $e->getMessage(),
                'payload'   => $payload,
            ]);
        }

        // Always return 200 to acknowledge receipt.
        return response()->json(['received' => true]);
    }

    /**
     * Dispatch a domain-specific event based on the webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function dispatchSpecificEvent(array $payload): void
    {
        $event = strtolower((string) ($payload['event'] ?? ''));
        $status = (string) ($payload['status'] ?? '');
        $referenceNumber = (string) ($payload['referenceNumber'] ?? $payload['reference_number'] ?? '');
        $merchantOrderKey = (string) $this->config->get('cashipay.merchant_order_key', 'merchantOrderId');
        $merchantOrderId = (string) ($payload[$merchantOrderKey] ?? $payload['merchantOrderId'] ?? '');

        // Determine completion: either via explicit event name or status field.
        $isCompleted = str_contains($event, 'complet')
            || str_contains($event, 'paid')
            || str_contains($event, 'success')
            || $this->cashiPay->isCompletedStatus($status ?: null);

        // Determine failure: either via explicit event name or status field.
        $isFailed = str_contains($event, 'fail')
            || str_contains($event, 'expir')
            || str_contains($event, 'cancel')
            || $this->cashiPay->isFailedStatus($status ?: null);

        if ($isCompleted) {
            $this->events->dispatch(new PaymentCompleted(
                referenceNumber: $referenceNumber,
                merchantOrderId: $merchantOrderId,
                payload: $payload,
            ));

            Log::info('[CashiPay] PaymentCompleted event dispatched.', [
                'referenceNumber' => $referenceNumber,
                'merchantOrderId' => $merchantOrderId,
            ]);

            return;
        }

        if ($isFailed) {
            $reason = ucfirst($status ?: $event ?: 'Unknown');

            $this->events->dispatch(new PaymentFailed(
                referenceNumber: $referenceNumber,
                reason: $reason,
                payload: $payload,
            ));

            Log::info('[CashiPay] PaymentFailed event dispatched.', [
                'referenceNumber' => $referenceNumber,
                'reason'          => $reason,
            ]);

            return;
        }

        // Unknown event type — WebhookReceived was already dispatched above.
        Log::info('[CashiPay] Unrecognized webhook event type; only WebhookReceived was dispatched.', [
            'event'  => $event,
            'status' => $status,
        ]);
    }
}
