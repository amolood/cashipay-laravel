<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a CashiPay webhook indicates a failed, expired, or cancelled payment.
 */
final class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  The fully decoded webhook body.
     */
    public function __construct(
        public string $key,
        public string $referenceNumber,
        public string $reason,
        public array $payload,
    ) {
    }
}
