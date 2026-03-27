# CashiPay Laravel

Laravel package for the **CashiPay** wallet payment gateway — QR and OTP payment flows, webhook handling, and Laravel event integration.

**Supports:** Laravel 10 / 11 / 12 · PHP 8.1+

---

## Quick Start

### 1. Install

```bash
composer require amolood/cashipay-laravel
php artisan cashipay:install
```

The install command publishes `config/cashipay.php` and prints exactly what to add to your `.env` and how to exclude the webhook from CSRF.

### 2. Add to `.env`

```dotenv
CASHIPAY_ENV=staging
CASHIPAY_STAGING_KEY=your-staging-api-key
CASHIPAY_PRODUCTION_KEY=your-production-api-key
CASHIPAY_WEBHOOK_SECRET=your-webhook-hmac-secret
```

### 3. Exclude webhook from CSRF

**Laravel 11+ (`bootstrap/app.php`):**
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['cashipay/webhook']);
})
```

**Laravel 10 (`app/Http/Middleware/VerifyCsrfToken.php`):**
```php
protected $except = ['cashipay/webhook'];
```

### 4. Create a payment

```php
use CashiPay;

$response = CashiPay::request()
    ->merchantOrderId('ORD-1001')
    ->amount(250.00)
    ->customerEmail('user@example.com')
    ->callbackUrl(route('cashipay.webhook'))
    ->returnUrl(route('orders.show', 1001))
    ->send();

if ($response->success) {
    $reference = $response->referenceNumber;
    $qrCode    = $response->qrDataUrl; // <img src="{{ $qrCode }}">
}
```

### 5. Handle the webhook

Register a listener for payment events:

```php
// app/Providers/AppServiceProvider.php
use DigitalizeLab\CashiPay\Events\PaymentCompleted;
use DigitalizeLab\CashiPay\Events\PaymentFailed;

public function boot(): void
{
    Event::listen(PaymentCompleted::class, function ($event) {
        // $event->referenceNumber, $event->merchantOrderId, $event->payload
        Order::where('cashipay_reference', $event->referenceNumber)
            ->first()
            ?->markAsPaid();
    });

    Event::listen(PaymentFailed::class, function ($event) {
        // $event->referenceNumber, $event->reason, $event->payload
    });
}
```

---

## Payment Flows

### QR Payment

```php
$response = CashiPay::request()
    ->merchantOrderId('ORD-' . $order->id)
    ->amount($order->total)
    ->customerEmail($order->customer->email)
    ->callbackUrl(route('cashipay.webhook'))
    ->returnUrl(route('orders.show', $order))
    ->send();

// Display QR code
// <img src="{{ $response->qrDataUrl }}" width="250">

// Store reference to match against webhook
$order->update(['cashipay_reference' => $response->referenceNumber]);
```

### OTP Payment

```php
// Step 1 — create request with wallet number
$response = CashiPay::request()
    ->merchantOrderId('ORD-' . $order->id)
    ->amount($order->total)
    ->customerEmail($order->customer->email)
    ->walletAccountNumber($request->wallet_phone)
    ->callbackUrl(route('cashipay.webhook'))
    ->returnUrl(route('orders.show', $order))
    ->send();

// Step 2 — confirm OTP entered by customer
$confirm = CashiPay::confirmOtp(
    referenceNumber: $response->referenceNumber,
    amount: $order->total,
    otp: $request->otp,
    walletPhone: $request->wallet_phone,
);

if ($confirm->isCompleted()) {
    $order->markAsPaid();
}
```

---

## API Reference

### `CashiPay::request()` — fluent builder

```php
CashiPay::request()
    ->merchantOrderId(string)     // required
    ->amount(float)               // required, > 0
    ->customerEmail(string)       // required
    ->callbackUrl(string)         // required
    ->returnUrl(string)           // required
    ->currency(string)            // optional, default: SDG
    ->description(string)         // optional
    ->customerPhone(string)       // optional
    ->walletAccountNumber(string) // optional, enables OTP flow
    ->metadata(array)             // optional, arbitrary key-value
    ->send();                     // returns PaymentResponse
```

### `PaymentResponse` properties

| Property | Type | Description |
|---|---|---|
| `$r->success` | `bool` | HTTP call succeeded |
| `$r->referenceNumber` | `?string` | CashiPay payment reference |
| `$r->status` | `?string` | Raw status (`PENDING`, `COMPLETED`, …) |
| `$r->qrDataUrl` | `?string` | Base64 QR image (QR flow only) |
| `$r->rawData` | `array` | Full API response |

### `PaymentResponse` methods

```php
$response->isCompleted(); // COMPLETED, PAID, SUCCESS, APPROVED
$response->isFailed();    // EXPIRED, CANCELLED, FAILED
$response->isPending();   // success=true, not completed, not failed
$response->get('metadata.order_id'); // dot-notation access to rawData
```

### Other methods

```php
CashiPay::getPaymentStatus(string $referenceNumber): PaymentResponse
CashiPay::cancelPaymentRequest(string $referenceNumber): PaymentResponse
CashiPay::confirmOtp(string $ref, float $amount, string $otp, string $walletPhone): PaymentResponse
```

---

## Webhooks

The package auto-registers `POST /cashipay/webhook` (path configurable via `CASHIPAY_WEBHOOK_PATH`).

Inbound signatures are verified automatically via `X-CashiPay-Signature` (HMAC-SHA256). Invalid signatures get a `401`. If `CASHIPAY_WEBHOOK_SECRET` is empty, verification is skipped (dev only).

### Events

| Event | When |
|---|---|
| `PaymentCompleted` | Status is COMPLETED / PAID / SUCCESS / APPROVED |
| `PaymentFailed` | Status is EXPIRED / CANCELLED / FAILED |
| `WebhookReceived` | Every inbound webhook (useful for logging) |

---

## Polling Status

```php
$status = CashiPay::getPaymentStatus($order->cashipay_reference);

match (true) {
    $status->isCompleted() => $order->markAsPaid(),
    $status->isFailed()    => $order->markAsFailed(),
    default                => null,
};
```

---

## Testing

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    '*/payment-requests' => Http::response([
        'referenceNumber' => 'REF-001',
        'status'          => 'PENDING',
        'qrCode'          => ['dataUrl' => 'data:image/png;base64,abc'],
    ], 200),
]);

$response = CashiPay::request()
    ->merchantOrderId('ORD-1')
    ->amount(100.00)
    ->customerEmail('test@example.com')
    ->callbackUrl('https://myapp.test/cashipay/webhook')
    ->returnUrl('https://myapp.test/orders/1')
    ->send();

$this->assertTrue($response->success);
$this->assertSame('REF-001', $response->referenceNumber);
```

Simulate a webhook:

```php
Event::fake();

$body      = json_encode(['referenceNumber' => 'REF-001', 'status' => 'COMPLETED', 'merchantOrderId' => 'ORD-1']);
$signature = hash_hmac('sha256', $body, config('cashipay.webhook.secret'));

$this->withHeaders(['X-CashiPay-Signature' => $signature])
    ->postJson('/cashipay/webhook', json_decode($body, true))
    ->assertOk();

Event::assertDispatched(PaymentCompleted::class);
```

---

## Configuration

Full config reference (`config/cashipay.php`):

```php
return [
    'environment' => env('CASHIPAY_ENV', 'staging'), // "staging" or "production"

    'environments' => [
        'staging' => [
            'base_url' => env('CASHIPAY_STAGING_URL', 'https://stg-cashi-services.alsoug.com/cashipay'),
            'api_key'  => env('CASHIPAY_STAGING_KEY', ''),
        ],
        'production' => [
            'base_url' => env('CASHIPAY_PRODUCTION_URL', ''),
            'api_key'  => env('CASHIPAY_PRODUCTION_KEY', ''),
        ],
    ],

    'webhook' => [
        'secret'     => env('CASHIPAY_WEBHOOK_SECRET', null),
        'path'       => env('CASHIPAY_WEBHOOK_PATH', 'cashipay/webhook'),
        'middleware' => [],
    ],

    'currency'     => env('CASHIPAY_CURRENCY', 'SDG'),
    'expiry_hours' => (int) env('CASHIPAY_EXPIRY_HOURS', 24),
    'timeout'      => (int) env('CASHIPAY_TIMEOUT', 30),
];
```

---

## License

MIT. See [LICENSE](LICENSE) for details.
