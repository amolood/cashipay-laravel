<?php

declare(strict_types=1);

use DigitalizeLab\CashiPay\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CashiPay Webhook Route
|--------------------------------------------------------------------------
|
| This route handles inbound webhook notifications from the CashiPay payment
| gateway. The 'cashipay.webhook' middleware performs HMAC-SHA256 signature
| verification before the controller processes the payload.
|
*/

Route::post(
    config('cashipay.webhook.path', 'cashipay/webhook'),
    [WebhookController::class, 'handle'],
)
    ->middleware(['cashipay.webhook', ...config('cashipay.webhook.middleware', [])])
    ->name('cashipay.webhook');
