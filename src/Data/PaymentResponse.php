<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Data;

/**
 * Immutable DTO representing a CashiPay API response.
 */
final class PaymentResponse
{
    /**
     * @param  array<string, mixed>  $rawData  The full decoded response body.
     */
    public function __construct(
        public bool $success,
        public ?string $referenceNumber,
        public ?string $status,
        public ?string $qrDataUrl,
        public array $rawData,
    ) {
    }

    /**
     * Build a PaymentResponse from a decoded API response array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, bool $success): static
    {
        return new static(
            success: $success,
            referenceNumber: $data['referenceNumber'] ?? $data['reference_number'] ?? null,
            status: $data['status'] ?? null,
            qrDataUrl: ($data['qrCode'] ?? [])['dataUrl'] ?? ($data['qr_code'] ?? [])['data_url'] ?? $data['qrDataUrl'] ?? null,
            rawData: $data,
        );
    }

    /**
     * Create a failed response with an error message stored in rawData.
     */
    public static function failure(string $message, mixed $context = null): static
    {
        return new static(
            success: false,
            referenceNumber: null,
            status: null,
            qrDataUrl: null,
            rawData: [
                'error'   => $message,
                'context' => $context,
            ],
        );
    }

    /**
     * Whether the payment has been successfully completed.
     */
    public function isCompleted(): bool
    {
        return in_array(
            strtoupper((string) $this->status),
            ['COMPLETED', 'PAID', 'SUCCESS', 'APPROVED'],
            strict: true,
        );
    }

    /**
     * Whether the payment has failed, expired, or been cancelled.
     */
    public function isFailed(): bool
    {
        return in_array(
            strtoupper((string) $this->status),
            ['EXPIRED', 'CANCELLED', 'FAILED'],
            strict: true,
        );
    }

    /**
     * Whether the payment is still awaiting completion.
     */
    public function isPending(): bool
    {
        return $this->success && ! $this->isCompleted() && ! $this->isFailed();
    }

    /**
     * Retrieve a value from the raw API response by dot-notation key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->rawData, $key, $default);
    }
}
