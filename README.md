# CashiPay Laravel

A production-ready Laravel package for integrating the **CashiPay** wallet payment gateway into any Laravel application.

- QR code and OTP payment flows
- Webhook handling with HMAC-SHA256 signature verification
- Laravel event system integration (`PaymentCompleted`, `PaymentFailed`, `WebhookReceived`)
- Fluent `PaymentRequest` DTO builder with built-in validation
- Immutable `PaymentResponse` DTO with convenience status methods
- Environment-aware (staging / production) with zero code changes
- Fully testable via Laravel's `Http::fake()`
- Compatible with Laravel 10, 11, and 12

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Dependency Injection](#dependency-injection)
  - [Facade](#facade)
- [PaymentRequest Builder](#paymentrequest-builder)
- [PaymentResponse Reference](#paymentresponse-reference)
- [Payment Flows](#payment-flows)
  - [QR Payment](#qr-payment)
  - [OTP Payment](#otp-payment)
- [Polling Payment Status](#polling-payment-status)
- [Cancelling a Payment](#cancelling-a-payment)
- [Webhooks](#webhooks)
  - [CSRF Exclusion](#csrf-exclusion)
  - [Available Events](#available-events)
  - [Registering Listeners](#registering-listeners)
  - [Example Listeners](#example-listeners)
- [Signature Verification](#signature-verification)
- [Status Helpers](#status-helpers)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [License](#license)

---

## Requirements

| Dependency | Version        |
|------------|----------------|
| PHP        | `^8.1`         |
| Laravel    | `^10.0 \| ^11.0 \| ^12.0` |

---

## Installation

```bash
composer require amolood/cashipay-laravel
```

The service provider and `CashiPay` facade alias are auto-discovered by Laravel.

Publish the configuration file:

```bash
php artisan vendor:publish --tag=cashipay-config
```

This creates `config/cashipay.php` in your application.

---

## Configuration

### Environment variables

Add the following to your `.env` file:

```dotenv
# ── Environment ────────────────────────────────────────────────────────────────
# Supported: "staging", "production"
CASHIPAY_ENV=staging

# ── Staging credentials ────────────────────────────────────────────────────────
CASHIPAY_STAGING_URL=https://stg-cashi-services.alsoug.com/cashipay
CASHIPAY_STAGING_KEY=your-staging-api-key

# ── Production credentials ─────────────────────────────────────────────────────
CASHIPAY_PRODUCTION_URL=https://cashi-services.alsoug.com/cashipay
CASHIPAY_PRODUCTION_KEY=your-production-api-key

# ── Webhook ────────────────────────────────────────────────────────────────────
# HMAC-SHA256 secret shared with CashiPay. Leave empty to disable verification
# (not recommended for production).
CASHIPAY_WEBHOOK_SECRET=your-webhook-hmac-secret

# URI path on which the webhook endpoint is registered (default shown).
CASHIPAY_WEBHOOK_PATH=cashipay/webhook

# ── Optional ───────────────────────────────────────────────────────────────────
CASHIPAY_CURRENCY=SDG
CASHIPAY_EXPIRY_HOURS=24
CASHIPAY_TIMEOUT=30
```

### Full config reference (`config/cashipay.php`)

```php
return [

    // Active environment: "staging" or "production"
    'environment' => env('CASHIPAY_ENV', 'staging'),

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
        // HMAC-SHA256 secret for verifying inbound webhook signatures.
        'secret' => env('CASHIPAY_WEBHOOK_SECRET', null),

        // URI path registered for the webhook endpoint.
        'path' => env('CASHIPAY_WEBHOOK_PATH', 'cashipay/webhook'),

        // Additional middleware applied to the webhook route (beyond the
        // built-in signature verifier).
        'middleware' => [],
    ],

    // Default ISO 4217 currency code used when none is provided on a request.
    'currency' => env('CASHIPAY_CURRENCY', 'SDG'),

    // Hours before a payment request is considered expired.
    'expiry_hours' => (int) env('CASHIPAY_EXPIRY_HOURS', 24),

    // Maximum seconds to wait for a CashiPay API response.
    'timeout' => (int) env('CASHIPAY_TIMEOUT', 30),

    // Key used to carry your internal order ID in webhook payloads.
    'merchant_order_key' => env('CASHIPAY_MERCHANT_ORDER_KEY', 'merchantOrderId'),

];
```

---

## Usage

### Dependency Injection

Inject `CashiPayInterface` into any controller, job, or service:

```php
use DigitalizeLab\CashiPay\Contracts\CashiPayInterface;

class CheckoutController extends Controller
{
    public function __construct(private CashiPayInterface $cashiPay)
    {
    }

    public function pay(Order $order): JsonResponse
    {
        $response = $this->cashiPay->createPaymentRequest(
            PaymentRequest::make()
                ->merchantOrderId((string) $order->id)
                ->amount($order->total)
                ->customerEmail($order->customer->email)
                ->callbackUrl(route('cashipay.webhook'))
                ->returnUrl(route('orders.show', $order))
        );

        if (! $response->success) {
            return response()->json(['error' => 'Payment initiation failed.'], 502);
        }

        return response()->json([
            'reference' => $response->referenceNumber,
            'qr'        => $response->qrDataUrl,
        ]);
    }
}
```

### Facade

```php
use DigitalizeLab\CashiPay\CashiPayFacade as CashiPay;

$response = CashiPay::getPaymentStatus($referenceNumber);
```

Or with the auto-registered alias:

```php
use CashiPay;

$response = CashiPay::createPaymentRequest(...);
```

---

## PaymentRequest Builder

The `PaymentRequest` class is a fluent builder that validates all required fields before sending to the API.

```php
use DigitalizeLab\CashiPay\Data\PaymentRequest;

$request = PaymentRequest::make()
    ->merchantOrderId('ORD-1001')   // required — your internal order/reference ID
    ->amount(250.00)                 // required — must be > 0
    ->currency('SDG')               // optional — falls back to config('cashipay.currency')
    ->description('Order #1001')    // optional — shown to the customer
    ->customerEmail('user@example.com') // required
    ->customerPhone('0912345678')   // optional — customer's phone number
    ->walletAccountNumber('0912345678') // optional — wallet number for OTP flow
    ->callbackUrl(route('cashipay.webhook')) // required — your webhook URL
    ->returnUrl(route('orders.show', 1001)) // required — redirect after payment
    ->metadata(['order_type' => 'subscription']); // optional — arbitrary key-value data
```

Calling `toArray()` validates the builder and converts it to the API payload format. `createPaymentRequest()` calls this internally, so you never need to call `toArray()` yourself.

**Validation rules:**
- `merchantOrderId`, `customerEmail`, `callbackUrl`, `returnUrl` — cannot be empty
- `amount` — must be greater than `0`

An `InvalidArgumentException` is thrown if any rule is violated.

---

## PaymentResponse Reference

Every API method returns a `PaymentResponse` object.

| Property | Type | Description |
|---|---|---|
| `$response->success` | `bool` | Whether the API call itself succeeded (HTTP 2xx) |
| `$response->referenceNumber` | `?string` | CashiPay's unique reference for this payment |
| `$response->status` | `?string` | Raw status string returned by CashiPay (e.g. `PENDING`, `COMPLETED`) |
| `$response->qrDataUrl` | `?string` | Base64 data URL of the QR code image (QR flow only) |
| `$response->rawData` | `array` | Full decoded response body |

**Status helper methods:**

```php
$response->isCompleted(); // true for: COMPLETED, PAID, SUCCESS, APPROVED
$response->isFailed();    // true for: EXPIRED, CANCELLED, FAILED
$response->isPending();   // true when success=true and neither completed nor failed
```

**Dot-notation access to raw data:**

```php
$response->get('metadata.order_id'); // equivalent to data_get($response->rawData, 'metadata.order_id')
```

---

## Payment Flows

### QR Payment

The QR flow generates a scannable QR code the customer pays with their CashiPay wallet app.

**Step 1 — Create the payment request:**

```php
use DigitalizeLab\CashiPay\Data\PaymentRequest;
use CashiPay;

$response = CashiPay::createPaymentRequest(
    PaymentRequest::make()
        ->merchantOrderId('ORD-' . $order->id)
        ->amount((float) $order->total)
        ->description('Order #' . $order->order_number)
        ->customerEmail($order->customer->email)
        ->callbackUrl(route('cashipay.webhook'))
        ->returnUrl(route('orders.show', $order))
);

if (! $response->success) {
    // Handle API error — details in $response->rawData['error']
    abort(502, 'Payment gateway unavailable.');
}

// Store the reference so you can poll or match against the webhook later
$order->update([
    'cashipay_reference' => $response->referenceNumber,
    'payment_status'     => 'pending',
]);

// Render the QR code — $response->qrDataUrl is a base64 data URL
return view('checkout.qr', ['qrDataUrl' => $response->qrDataUrl]);
```

**Step 2 — Display the QR code:**

```html
<img src="{{ $qrDataUrl }}" alt="Scan to pay" width="250" height="250">
```

**Step 3 — Poll for completion (Livewire / AJAX):**

```php
$status = CashiPay::getPaymentStatus($order->cashipay_reference);

if ($status->isCompleted()) {
    $order->update(['payment_status' => 'paid']);
    // Trigger fulfilment
} elseif ($status->isFailed()) {
    $order->update(['payment_status' => 'failed']);
}
```

---

### OTP Payment

The OTP flow sends a one-time password to the customer's wallet phone number.

**Step 1 — Create the payment request with a wallet number:**

```php
$response = CashiPay::createPaymentRequest(
    PaymentRequest::make()
        ->merchantOrderId('ORD-' . $order->id)
        ->amount((float) $order->total)
        ->customerEmail($order->customer->email)
        ->walletAccountNumber($request->input('wallet_phone'))
        ->callbackUrl(route('cashipay.webhook'))
        ->returnUrl(route('orders.show', $order))
);

$referenceNumber = $response->referenceNumber;
```

**Step 2 — The customer receives an OTP. Confirm it:**

```php
$confirm = CashiPay::confirmOtp(
    referenceNumber: $referenceNumber,
    amount: (float) $order->total,
    otp: $request->input('otp'),
    walletPhone: $request->input('wallet_phone'),
);

if (! $confirm->success) {
    return back()->withErrors(['otp' => 'Invalid or expired OTP. Please try again.']);
}

if ($confirm->isCompleted()) {
    $order->update(['payment_status' => 'paid']);
}
```

---

## Polling Payment Status

Use this to check payment status at any point, such as in a background job or from a polling endpoint:

```php
$response = CashiPay::getPaymentStatus($referenceNumber);

match (true) {
    $response->isCompleted() => $order->markAsPaid(),
    $response->isFailed()    => $order->markAsFailed(),
    default                  => null, // still pending
};
```

---

## Cancelling a Payment

Cancel a pending payment (e.g. when a customer abandons the checkout):

```php
$response = CashiPay::cancelPaymentRequest($referenceNumber);

if ($response->success) {
    $order->update(['payment_status' => 'cancelled']);
}
```

---

## Webhooks

CashiPay sends a `POST` request to your `callbackUrl` when a payment status changes. The package automatically registers this endpoint at the path set by `cashipay.webhook.path` (default: `/cashipay/webhook`).

### CSRF Exclusion

The webhook endpoint must be excluded from CSRF protection.

**Laravel 11+ (`bootstrap/app.php`):**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        config('cashipay.webhook.path'),
    ]);
})
```

**Laravel 10 (`app/Http/Middleware/VerifyCsrfToken.php`):**

```php
protected $except = [
    'cashipay/webhook',
];
```

> The route is registered with the `cashipay.webhook` middleware alias, which handles HMAC-SHA256 signature verification before your listeners run. Requests with a missing or invalid signature are rejected with `401 Unauthorized`.

### Available Events

| Event | Dispatched when |
|---|---|
| `WebhookReceived` | Every inbound webhook, regardless of type — useful for logging / auditing |
| `PaymentCompleted` | Status is `COMPLETED`, `PAID`, `SUCCESS`, or `APPROVED` |
| `PaymentFailed` | Status is `EXPIRED`, `CANCELLED`, or `FAILED` |

**`WebhookReceived` properties:**

| Property | Type | Description |
|---|---|---|
| `$event->payload` | `array` | Full decoded webhook body |

**`PaymentCompleted` properties:**

| Property | Type | Description |
|---|---|---|
| `$event->referenceNumber` | `string` | CashiPay payment reference |
| `$event->merchantOrderId` | `string` | Your internal order ID |
| `$event->payload` | `array` | Full decoded webhook body |

**`PaymentFailed` properties:**

| Property | Type | Description |
|---|---|---|
| `$event->referenceNumber` | `string` | CashiPay payment reference |
| `$event->reason` | `string` | Human-readable failure reason (e.g. `EXPIRED`) |
| `$event->payload` | `array` | Full decoded webhook body |

### Registering Listeners

**Laravel 11+ (`app/Providers/AppServiceProvider.php`):**

```php
use DigitalizeLab\CashiPay\Events\PaymentCompleted;
use DigitalizeLab\CashiPay\Events\PaymentFailed;
use DigitalizeLab\CashiPay\Events\WebhookReceived;
use App\Listeners\HandlePaymentCompleted;
use App\Listeners\HandlePaymentFailed;

public function boot(): void
{
    Event::listen(PaymentCompleted::class, HandlePaymentCompleted::class);
    Event::listen(PaymentFailed::class,    HandlePaymentFailed::class);
    Event::listen(WebhookReceived::class,  fn ($e) => Log::debug('CashiPay webhook', $e->payload));
}
```

**Laravel 10 (`app/Providers/EventServiceProvider.php`):**

```php
protected $listen = [
    PaymentCompleted::class => [HandlePaymentCompleted::class],
    PaymentFailed::class    => [HandlePaymentFailed::class],
    WebhookReceived::class  => [LogCashiPayWebhook::class],
];
```

### Example Listeners

**Fulfil an order on payment completion:**

```php
namespace App\Listeners;

use App\Models\Order;
use DigitalizeLab\CashiPay\Events\PaymentCompleted;
use Illuminate\Support\Facades\Log;

class HandlePaymentCompleted
{
    public function handle(PaymentCompleted $event): void
    {
        $order = Order::where('cashipay_reference', $event->referenceNumber)->first();

        if (! $order) {
            Log::warning('CashiPay: no order found for reference', [
                'reference'      => $event->referenceNumber,
                'merchantOrderId'=> $event->merchantOrderId,
            ]);
            return;
        }

        $order->markAsPaid();
        // send receipt, trigger shipping, etc.
    }
}
```

**Handle payment failure:**

```php
namespace App\Listeners;

use App\Models\Order;
use DigitalizeLab\CashiPay\Events\PaymentFailed;

class HandlePaymentFailed
{
    public function handle(PaymentFailed $event): void
    {
        $order = Order::where('cashipay_reference', $event->referenceNumber)->first();

        $order?->update(['payment_status' => 'failed']);

        Log::warning('CashiPay payment failed', [
            'reference' => $event->referenceNumber,
            'reason'    => $event->reason,
        ]);
    }
}
```

---

## Signature Verification

The built-in `cashipay.webhook` middleware handles signature verification automatically. If you need to verify a signature manually (e.g. in a custom controller):

```php
$rawBody  = $request->getContent();
$sigHeader = $request->header('X-CashiPay-Signature', '');

if (! CashiPay::verifyWebhookSignature($rawBody, $sigHeader)) {
    abort(401, 'Invalid webhook signature.');
}
```

**How it works:** The middleware computes `hash_hmac('sha256', $rawBody, $secret)` and compares it with `X-CashiPay-Signature` using `hash_equals()` to prevent timing attacks.

> When `CASHIPAY_WEBHOOK_SECRET` is empty, verification is skipped and a warning is logged. This is acceptable for local development but **must not be used in production**.

---

## Status Helpers

Standalone status checks against both completed and failed status sets:

```php
CashiPay::isCompletedStatus('COMPLETED'); // true
CashiPay::isCompletedStatus('paid');      // true  (case-insensitive)
CashiPay::isCompletedStatus('SUCCESS');   // true
CashiPay::isCompletedStatus('APPROVED');  // true
CashiPay::isCompletedStatus('PENDING');   // false

CashiPay::isFailedStatus('EXPIRED');      // true
CashiPay::isFailedStatus('CANCELLED');    // true
CashiPay::isFailedStatus('failed');       // true  (case-insensitive)
CashiPay::isFailedStatus('COMPLETED');    // false
```

These are the same checks used internally by `PaymentResponse::isCompleted()` and `PaymentResponse::isFailed()`.

---

## Error Handling

All API methods catch HTTP errors and network exceptions internally. They never throw — they always return a `PaymentResponse`. A failed response sets `$response->success = false` and stores the error detail in `$response->rawData`.

```php
$response = CashiPay::createPaymentRequest($request);

if (! $response->success) {
    $errorMessage = $response->rawData['error'] ?? 'Unknown error';
    Log::error('CashiPay error', ['detail' => $response->rawData]);

    return back()->withErrors(['payment' => $errorMessage]);
}
```

All errors are also logged automatically at the `error` level under the `[CashiPay]` prefix.

---

## Testing

### Running the package tests

```bash
cd cashi_package
composer install
./vendor/bin/phpunit
```

The test suite covers:

- `isCompletedStatus()` / `isFailedStatus()` — all values, case-insensitive, null handling
- `verifyWebhookSignature()` — valid HMAC, invalid HMAC, no-secret pass-through
- `environment()` / `baseUrl()` / `apiKey()` — config resolution
- Webhook endpoint — correct signature accepted, missing/wrong signature → 401
- `PaymentCompleted` / `PaymentFailed` / `WebhookReceived` event dispatch
- Empty payload resilience

### Faking HTTP calls in your application tests

The package uses Laravel's `Http` client internally, so `Http::fake()` works out of the box:

```php
use Illuminate\Support\Facades\Http;
use DigitalizeLab\CashiPay\Data\PaymentRequest;
use CashiPay;

Http::fake([
    '*/payment-requests' => Http::response([
        'referenceNumber' => 'REF-TEST-001',
        'status'          => 'PENDING',
        'qrCode'          => ['dataUrl' => 'data:image/png;base64,abc123'],
    ], 200),

    '*/payment-requests/REF-TEST-001' => Http::response([
        'referenceNumber' => 'REF-TEST-001',
        'status'          => 'COMPLETED',
    ], 200),
]);

$response = CashiPay::createPaymentRequest(
    PaymentRequest::make()
        ->merchantOrderId('ORD-1')
        ->amount(100.00)
        ->customerEmail('test@example.com')
        ->callbackUrl('https://myapp.test/cashipay/webhook')
        ->returnUrl('https://myapp.test/orders/1')
);

$this->assertTrue($response->success);
$this->assertSame('REF-TEST-001', $response->referenceNumber);
$this->assertSame('data:image/png;base64,abc123', $response->qrDataUrl);

$status = CashiPay::getPaymentStatus('REF-TEST-001');
$this->assertTrue($status->isCompleted());
```

### Simulating inbound webhooks

```php
use Illuminate\Support\Facades\Event;
use DigitalizeLab\CashiPay\Events\PaymentCompleted;
use DigitalizeLab\CashiPay\Events\PaymentFailed;

Event::fake();

$payload = [
    'event'           => 'payment.completed',
    'referenceNumber' => 'REF-001',
    'status'          => 'COMPLETED',
    'merchantOrderId' => 'ORD-001',
];

$body      = json_encode($payload);
$signature = hash_hmac('sha256', $body, config('cashipay.webhook.secret'));

$this->withHeaders(['X-CashiPay-Signature' => $signature])
    ->postJson('/cashipay/webhook', $payload)
    ->assertOk()
    ->assertJson(['received' => true]);

Event::assertDispatched(PaymentCompleted::class, function ($e) {
    return $e->referenceNumber === 'REF-001'
        && $e->merchantOrderId === 'ORD-001';
});

Event::assertNotDispatched(PaymentFailed::class);
```

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

---

## Credits

Built and maintained by [Digitalize Lab](https://github.com/digitalize-lab).
