<?php

declare(strict_types=1);

use DigitalizeLab\CashiPay\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post(
    config('cashipay.webhook.path', 'cashipay/webhook'),
    [WebhookController::class, 'handle'],
)
    ->middleware(config('cashipay.webhook.middleware', []))
    ->name('cashipay.webhook');
