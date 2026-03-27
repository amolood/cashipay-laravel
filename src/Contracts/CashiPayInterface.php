<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Contracts;

use DigitalizeLab\CashiPay\Data\PaymentRequest;
use DigitalizeLab\CashiPay\Data\PaymentResponse;

interface CashiPayInterface
{
    /**
     * Start building a payment request (fluent shortcut).
     *
     * Example: CashiPay::request()->amount(100)->...->send()
     */
    public function request(): PaymentRequest;

    /**
     * Create a new payment request (QR or OTP).
     *
     * @param  array<string, mixed>|PaymentRequest  $payload
     */
    public function createPaymentRequest(array|PaymentRequest $payload): PaymentResponse;

    /**
     * Retrieve the current status of a payment request.
     */
    public function getPaymentStatus(string $referenceNumber): PaymentResponse;

    /**
     * Cancel an existing payment request.
     */
    public function cancelPaymentRequest(string $referenceNumber): PaymentResponse;

    /**
     * Confirm an OTP-based payment.
     */
    public function confirmOtp(
        string $referenceNumber,
        float $amount,
        string $otp,
        string $walletPhone,
    ): PaymentResponse;

    /**
     * Verify the HMAC-SHA256 signature supplied in a webhook request.
     */
    public function verifyWebhookSignature(string $rawPayload, string $signature): bool;

    /**
     * Determine whether the given status string represents a completed payment.
     */
    public function isCompletedStatus(?string $status): bool;

    /**
     * Determine whether the given status string represents a failed payment.
     */
    public function isFailedStatus(?string $status): bool;
}
