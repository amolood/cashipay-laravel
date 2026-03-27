<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay;

use DigitalizeLab\CashiPay\Data\PaymentRequest;
use DigitalizeLab\CashiPay\Data\PaymentResponse;
use Illuminate\Support\Facades\Facade;

/**
 * CashiPay Facade.
 *
 * @method static PaymentRequest  request()
 * @method static PaymentResponse createPaymentRequest(array|PaymentRequest $payload)
 * @method static PaymentResponse getPaymentStatus(string $referenceNumber)
 * @method static PaymentResponse cancelPaymentRequest(string $referenceNumber)
 * @method static PaymentResponse confirmOtp(string $referenceNumber, float $amount, string $otp, string $walletPhone)
 * @method static bool            verifyWebhookSignature(string $rawPayload, string $signature)
 * @method static bool            isCompletedStatus(?string $status)
 * @method static bool            isFailedStatus(?string $status)
 * @method static string          environment()
 * @method static string          baseUrl()
 * @method static string          apiKey()
 *
 * @see \DigitalizeLab\CashiPay\CashiPayManager
 */
final class CashiPayFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'cashipay';
    }
}
