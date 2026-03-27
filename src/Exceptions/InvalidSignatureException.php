<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Exceptions;

/**
 * Thrown when a webhook HMAC-SHA256 signature cannot be verified.
 */
class InvalidSignatureException extends CashiPayException
{
    public function __construct(string $message = 'The webhook signature could not be verified.')
    {
        parent::__construct($message);
    }
}
