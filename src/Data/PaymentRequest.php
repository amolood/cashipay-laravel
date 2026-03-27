<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Data;

use InvalidArgumentException;

/**
 * Fluent builder for a CashiPay payment request payload.
 *
 * Example:
 *   $request = PaymentRequest::make()
 *       ->merchantOrderId('ORD-001')
 *       ->amount(150.00)
 *       ->currency('SDG')
 *       ->description('Order payment')
 *       ->customerEmail('user@example.com')
 *       ->callbackUrl(route('cashipay.webhook'))
 *       ->returnUrl('https://myapp.com/orders/1');
 */
final class PaymentRequest
{
    private string $merchantOrderId = '';
    private float $amount = 0.0;
    private string $currency = '';
    private string $description = '';
    private string $customerEmail = '';
    private ?string $customerPhone = null;
    private ?string $walletAccountNumber = null;
    private string $callbackUrl = '';
    private string $returnUrl = '';

    /** @var array<string, mixed> */
    private array $metadata = [];

    /**
     * Create a new PaymentRequest builder instance.
     */
    public static function make(): static
    {
        return new static();
    }

    public function merchantOrderId(string $merchantOrderId): static
    {
        $this->merchantOrderId = $merchantOrderId;

        return $this;
    }

    public function amount(float $amount): static
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $this->amount = $amount;

        return $this;
    }

    public function currency(string $currency): static
    {
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function customerEmail(string $email): static
    {
        $this->customerEmail = $email;

        return $this;
    }

    public function customerPhone(string $phone): static
    {
        $this->customerPhone = $phone;

        return $this;
    }

    public function walletAccountNumber(string $walletAccountNumber): static
    {
        $this->walletAccountNumber = $walletAccountNumber;

        return $this;
    }

    public function callbackUrl(string $url): static
    {
        $this->callbackUrl = $url;

        return $this;
    }

    public function returnUrl(string $url): static
    {
        $this->returnUrl = $url;

        return $this;
    }

    /**
     * Attach arbitrary metadata that will be merged into the request payload.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Validate and convert the builder to an API-ready array.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException if required fields are missing.
     */
    public function toArray(): array
    {
        $this->validate();

        $payload = [
            'merchantOrderId' => $this->merchantOrderId,
            'amount'          => [
                'value'    => $this->amount,
                'currency' => $this->currency ?: config('cashipay.currency', 'SDG'),
            ],
            'description'     => $this->description,
            'customerEmail'   => $this->customerEmail,
            'callbackUrl'     => $this->callbackUrl,
            'returnUrl'       => $this->returnUrl,
        ];

        if ($this->customerPhone !== null) {
            $payload['customerPhone'] = $this->customerPhone;
        }

        if ($this->walletAccountNumber !== null) {
            $payload['walletAccountNumber'] = $this->walletAccountNumber;
        }

        return array_merge($payload, $this->metadata);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        $required = [
            'merchantOrderId' => $this->merchantOrderId,
            'customerEmail'   => $this->customerEmail,
            'callbackUrl'     => $this->callbackUrl,
            'returnUrl'       => $this->returnUrl,
        ];

        foreach ($required as $field => $value) {
            if (empty($value)) {
                throw new InvalidArgumentException("PaymentRequest: '{$field}' is required and cannot be empty.");
            }
        }

        if ($this->amount <= 0) {
            throw new InvalidArgumentException("PaymentRequest: 'amount' must be greater than zero.");
        }
    }
}
