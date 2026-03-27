<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Exceptions;

use RuntimeException;

/**
 * Base exception for all CashiPay package errors.
 */
class CashiPayException extends RuntimeException
{
    /**
     * Create an exception for a failed API call.
     */
    public static function apiError(string $message, int $code = 0, ?\Throwable $previous = null): static
    {
        return new static("CashiPay API error: {$message}", $code, $previous);
    }

    /**
     * Create an exception for a configuration problem.
     */
    public static function configurationError(string $message): static
    {
        return new static("CashiPay configuration error: {$message}");
    }
}
