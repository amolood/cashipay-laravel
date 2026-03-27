<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched for every inbound CashiPay webhook, regardless of event type.
 * Listen to this event to implement custom logging, auditing, or catch-all handling.
 */
final class WebhookReceived
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  The fully decoded webhook body.
     */
    public function __construct(
        public array $payload,
    ) {
    }
}
