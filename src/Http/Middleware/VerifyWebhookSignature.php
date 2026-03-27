<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Http\Middleware;

use Closure;
use DigitalizeLab\CashiPay\Contracts\CashiPayInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that verifies the HMAC-SHA256 signature on inbound CashiPay webhooks.
 *
 * The signature is expected in the X-CashiPay-Signature request header as a
 * hex-encoded SHA256 digest of the raw request body.
 */
final class VerifyWebhookSignature
{
    private CashiPayInterface $cashiPay;
    private ConfigRepository $config;

    public function __construct(
        CashiPayInterface $cashiPay,
        ConfigRepository $config,
    ) {
        $this->cashiPay = $cashiPay;
        $this->config   = $config;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->config->get('cashipay.webhook.secret');

        // If no secret is configured we emit a warning and let the request through.
        if (empty($secret)) {
            Log::warning(
                '[CashiPay] Webhook secret is not configured. ' .
                'Signature verification is disabled. ' .
                'Set CASHIPAY_WEBHOOK_SECRET in your .env file for production use.'
            );

            return $next($request);
        }

        $signature = $request->header('X-CashiPay-Signature', '');

        if (empty($signature)) {
            Log::warning('[CashiPay] Webhook received without X-CashiPay-Signature header.', [
                'ip'  => $request->ip(),
                'url' => $request->fullUrl(),
            ]);

            abort(Response::HTTP_UNAUTHORIZED, 'Missing webhook signature.');
        }

        $rawBody = $request->getContent();

        if (! $this->cashiPay->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('[CashiPay] Webhook signature verification failed.', [
                'ip'        => $request->ip(),
                'signature' => $signature,
            ]);

            abort(Response::HTTP_UNAUTHORIZED, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
