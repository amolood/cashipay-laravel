<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay;

use DigitalizeLab\CashiPay\Contracts\CashiPayInterface;
use DigitalizeLab\CashiPay\Data\PaymentRequest;
use DigitalizeLab\CashiPay\Data\PaymentResponse;
use DigitalizeLab\CashiPay\Exceptions\CashiPayException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Main CashiPay service class.
 *
 * Wraps all CashiPay API interactions and provides a clean, testable interface.
 */
final class CashiPayManager implements CashiPayInterface
{
    /** Status codes that indicate a completed payment. */
    private const COMPLETED_STATUSES = ['COMPLETED', 'PAID', 'SUCCESS', 'APPROVED'];

    /** Status codes that indicate a failed payment. */
    private const FAILED_STATUSES = ['EXPIRED', 'CANCELLED', 'FAILED'];

    private ConfigRepository $config;
    private Dispatcher $events;
    private HttpFactory $http;

    public function __construct(
        ConfigRepository $config,
        Dispatcher $events,
        HttpFactory $http,
    ) {
        $this->config = $config;
        $this->events = $events;
        $this->http   = $http;
    }

    /**
     * Return the currently active environment name (e.g. "staging" or "production").
     */
    public function environment(): string
    {
        return (string) $this->config->get('cashipay.environment', 'staging');
    }

    /**
     * Return the base URL for the active environment.
     *
     * @throws CashiPayException if the base URL is not configured.
     */
    public function baseUrl(): string
    {
        $env = $this->environment();
        $url = $this->config->get("cashipay.environments.{$env}.base_url");

        if (empty($url)) {
            throw CashiPayException::configurationError(
                "Base URL for environment '{$env}' is not configured."
            );
        }

        return rtrim((string) $url, '/');
    }

    /**
     * Return the API key for the active environment.
     *
     * @throws CashiPayException if the API key is not configured.
     */
    public function apiKey(): string
    {
        $env = $this->environment();
        $key = $this->config->get("cashipay.environments.{$env}.api_key");

        if (empty($key)) {
            throw CashiPayException::configurationError(
                "API key for environment '{$env}' is not configured."
            );
        }

        return (string) $key;
    }

    /**
     * Build a pre-configured Laravel HTTP client for the CashiPay API.
     */
    public function client(): PendingRequest
    {
        /** @var PendingRequest $pending */
        $pending = $this->http->baseUrl($this->baseUrl());

        return $pending
            ->withToken($this->apiKey())
            ->acceptJson()
            ->asJson()
            ->timeout((int) $this->config->get('cashipay.timeout', 30));
    }

    /**
     * {@inheritDoc}
     */
    public function createPaymentRequest(array|PaymentRequest $payload): PaymentResponse
    {
        $data = $payload instanceof PaymentRequest ? $payload->toArray() : $payload;

        // Apply default currency when not supplied
        if (empty($data['amount']['currency'])) {
            $data['amount']['currency'] = (string) $this->config->get('cashipay.currency', 'SDG');
        }

        try {
            $response = $this->client()->post('/payment-requests', $data);
            $response->throw();

            return PaymentResponse::fromArray($response->json() ?? [], success: true);
        } catch (RequestException $e) {
            $body = $e->response->json() ?? [];
            $message = $body['message'] ?? $e->getMessage();

            Log::error('[CashiPay] createPaymentRequest failed', [
                'status'  => $e->response->status(),
                'message' => $message,
                'payload' => $data,
            ]);

            return PaymentResponse::fromArray(
                array_merge($body, ['error' => $message]),
                success: false,
            );
        } catch (Throwable $e) {
            Log::error('[CashiPay] createPaymentRequest exception', [
                'exception' => $e->getMessage(),
                'payload'   => $data,
            ]);

            return PaymentResponse::failure($e->getMessage(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getPaymentStatus(string $referenceNumber): PaymentResponse
    {
        try {
            $response = $this->client()->get("/payment-requests/{$referenceNumber}");
            $response->throw();

            return PaymentResponse::fromArray($response->json() ?? [], success: true);
        } catch (RequestException $e) {
            $body = $e->response->json() ?? [];
            $message = $body['message'] ?? $e->getMessage();

            Log::error('[CashiPay] getPaymentStatus failed', [
                'referenceNumber' => $referenceNumber,
                'status'          => $e->response->status(),
                'message'         => $message,
            ]);

            return PaymentResponse::fromArray(
                array_merge($body, ['error' => $message]),
                success: false,
            );
        } catch (Throwable $e) {
            Log::error('[CashiPay] getPaymentStatus exception', [
                'referenceNumber' => $referenceNumber,
                'exception'       => $e->getMessage(),
            ]);

            return PaymentResponse::failure($e->getMessage(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function cancelPaymentRequest(string $referenceNumber): PaymentResponse
    {
        try {
            $response = $this->client()->post("/payment-requests/{$referenceNumber}/cancel");
            $response->throw();

            return PaymentResponse::fromArray($response->json() ?? [], success: true);
        } catch (RequestException $e) {
            $body = $e->response->json() ?? [];
            $message = $body['message'] ?? $e->getMessage();

            Log::error('[CashiPay] cancelPaymentRequest failed', [
                'referenceNumber' => $referenceNumber,
                'status'          => $e->response->status(),
                'message'         => $message,
            ]);

            return PaymentResponse::fromArray(
                array_merge($body, ['error' => $message]),
                success: false,
            );
        } catch (Throwable $e) {
            Log::error('[CashiPay] cancelPaymentRequest exception', [
                'referenceNumber' => $referenceNumber,
                'exception'       => $e->getMessage(),
            ]);

            return PaymentResponse::failure($e->getMessage(), $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function confirmOtp(
        string $referenceNumber,
        float $amount,
        string $otp,
        string $walletPhone,
    ): PaymentResponse {
        $payload = [
            'referenceNumber'     => $referenceNumber,
            'amount'              => $amount,
            'otp'                 => $otp,
            'walletAccountNumber' => $walletPhone,
        ];

        try {
            $response = $this->client()->post('/payment-requests/payment-confirm', $payload);
            $response->throw();

            return PaymentResponse::fromArray($response->json() ?? [], success: true);
        } catch (RequestException $e) {
            $body = $e->response->json() ?? [];
            $message = $body['message'] ?? $e->getMessage();

            Log::error('[CashiPay] confirmOtp failed', [
                'referenceNumber' => $referenceNumber,
                'status'          => $e->response->status(),
                'message'         => $message,
            ]);

            return PaymentResponse::fromArray(
                array_merge($body, ['error' => $message]),
                success: false,
            );
        } catch (Throwable $e) {
            Log::error('[CashiPay] confirmOtp exception', [
                'referenceNumber' => $referenceNumber,
                'exception'       => $e->getMessage(),
            ]);

            return PaymentResponse::failure($e->getMessage(), $e);
        }
    }

    /**
     * {@inheritDoc}
     *
     * Computes HMAC-SHA256 of the raw payload with the configured secret and
     * performs a timing-safe comparison against the provided signature.
     *
     * Returns true if no webhook secret is configured (pass-through mode).
     */
    public function verifyWebhookSignature(string $rawPayload, string $signature): bool
    {
        $secret = $this->config->get('cashipay.webhook.secret');

        if (empty($secret)) {
            return true;
        }

        $expected = hash_hmac('sha256', $rawPayload, (string) $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * {@inheritDoc}
     */
    public function isCompletedStatus(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        return in_array(strtoupper($status), self::COMPLETED_STATUSES, strict: true);
    }

    /**
     * {@inheritDoc}
     */
    public function isFailedStatus(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        return in_array(strtoupper($status), self::FAILED_STATUSES, strict: true);
    }
}
