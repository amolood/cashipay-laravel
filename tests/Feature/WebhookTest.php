<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Tests\Feature;

use DigitalizeLab\CashiPay\Events\PaymentCompleted;
use DigitalizeLab\CashiPay\Events\PaymentFailed;
use DigitalizeLab\CashiPay\Events\WebhookReceived;
use DigitalizeLab\CashiPay\Tests\TestCase;
use Illuminate\Support\Facades\Event;

final class WebhookTest extends TestCase
{
    private const WEBHOOK_URL = '/cashipay/webhook';

    // ------------------------------------------------------------------ //
    // PaymentCompleted event                                               //
    // ------------------------------------------------------------------ //

    /** @test */
    public function it_dispatches_payment_completed_event_for_completed_status(): void
    {
        Event::fake();

        $payload = [
            'event'           => 'payment.completed',
            'referenceNumber' => 'REF-001',
            'status'          => 'COMPLETED',
            'merchantOrderId' => 'ORD-001',
        ];

        $this->postJson(self::WEBHOOK_URL, $payload)
            ->assertOk()
            ->assertJson(['received' => true]);

        Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $e) use ($payload): bool {
            return $e->payload === $payload;
        });

        Event::assertDispatched(PaymentCompleted::class, function (PaymentCompleted $e): bool {
            return $e->referenceNumber === 'REF-001'
                && $e->merchantOrderId === 'ORD-001';
        });

        Event::assertNotDispatched(PaymentFailed::class);
    }

    /** @test */
    public function it_dispatches_payment_completed_event_for_paid_status(): void
    {
        Event::fake();

        $this->postJson(self::WEBHOOK_URL, [
            'event'           => 'payment.paid',
            'referenceNumber' => 'REF-002',
            'status'          => 'PAID',
            'merchantOrderId' => 'ORD-002',
        ])->assertOk();

        Event::assertDispatched(PaymentCompleted::class, function (PaymentCompleted $e): bool {
            return $e->referenceNumber === 'REF-002';
        });
    }

    /** @test */
    public function it_dispatches_payment_completed_event_for_approved_status(): void
    {
        Event::fake();

        $this->postJson(self::WEBHOOK_URL, [
            'event'           => 'some.event',
            'referenceNumber' => 'REF-003',
            'status'          => 'APPROVED',
            'merchantOrderId' => 'ORD-003',
        ])->assertOk();

        Event::assertDispatched(PaymentCompleted::class);
    }

    // ------------------------------------------------------------------ //
    // PaymentFailed event                                                  //
    // ------------------------------------------------------------------ //

    /** @test */
    public function it_dispatches_payment_failed_event_for_failed_status(): void
    {
        Event::fake();

        $payload = [
            'event'           => 'payment.failed',
            'referenceNumber' => 'REF-004',
            'status'          => 'FAILED',
            'merchantOrderId' => 'ORD-004',
        ];

        $this->postJson(self::WEBHOOK_URL, $payload)
            ->assertOk()
            ->assertJson(['received' => true]);

        Event::assertDispatched(WebhookReceived::class);

        Event::assertDispatched(PaymentFailed::class, function (PaymentFailed $e): bool {
            return $e->referenceNumber === 'REF-004'
                && str_contains(strtoupper($e->reason), 'FAIL');
        });

        Event::assertNotDispatched(PaymentCompleted::class);
    }

    /** @test */
    public function it_dispatches_payment_failed_event_for_expired_status(): void
    {
        Event::fake();

        $this->postJson(self::WEBHOOK_URL, [
            'event'           => 'payment.expired',
            'referenceNumber' => 'REF-005',
            'status'          => 'EXPIRED',
            'merchantOrderId' => 'ORD-005',
        ])->assertOk();

        Event::assertDispatched(PaymentFailed::class, function (PaymentFailed $e): bool {
            return $e->referenceNumber === 'REF-005';
        });
    }

    /** @test */
    public function it_dispatches_payment_failed_event_for_cancelled_status(): void
    {
        Event::fake();

        $this->postJson(self::WEBHOOK_URL, [
            'event'           => 'payment.cancelled',
            'referenceNumber' => 'REF-006',
            'status'          => 'CANCELLED',
            'merchantOrderId' => 'ORD-006',
        ])->assertOk();

        Event::assertDispatched(PaymentFailed::class, function (PaymentFailed $e): bool {
            return $e->referenceNumber === 'REF-006';
        });
    }

    // ------------------------------------------------------------------ //
    // Unknown events                                                       //
    // ------------------------------------------------------------------ //

    /** @test */
    public function it_dispatches_only_webhook_received_for_unknown_events(): void
    {
        Event::fake();

        $this->postJson(self::WEBHOOK_URL, [
            'event'           => 'payment.processing',
            'referenceNumber' => 'REF-007',
            'status'          => 'PENDING',
            'merchantOrderId' => 'ORD-007',
        ])
            ->assertOk()
            ->assertJson(['received' => true]);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertNotDispatched(PaymentCompleted::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    /** @test */
    public function it_returns_200_for_empty_payload(): void
    {
        Event::fake();

        $this->post(self::WEBHOOK_URL, [], ['Content-Type' => 'application/json'])
            ->assertOk()
            ->assertJson(['received' => true]);
    }
}
