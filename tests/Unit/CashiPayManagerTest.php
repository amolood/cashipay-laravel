<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Tests\Unit;

use DigitalizeLab\CashiPay\CashiPayManager;
use DigitalizeLab\CashiPay\Tests\TestCase;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery;

final class CashiPayManagerTest extends TestCase
{
    private CashiPayManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->app->make(CashiPayManager::class);
    }

    // ------------------------------------------------------------------ //
    // isCompletedStatus                                                    //
    // ------------------------------------------------------------------ //

    /** @test */
    public function it_returns_true_for_all_completed_statuses(): void
    {
        foreach (['COMPLETED', 'PAID', 'SUCCESS', 'APPROVED'] as $status) {
            $this->assertTrue(
                $this->manager->isCompletedStatus($status),
                "Expected '{$status}' to be a completed status."
            );
        }
    }

    /** @test */
    public function it_is_case_insensitive_for_completed_statuses(): void
    {
        foreach (['completed', 'paid', 'success', 'approved'] as $status) {
            $this->assertTrue(
                $this->manager->isCompletedStatus($status),
                "Expected lowercase '{$status}' to be recognised as a completed status."
            );
        }
    }

    /** @test */
    public function it_returns_false_for_non_completed_statuses(): void
    {
        foreach (['PENDING', 'PROCESSING', 'FAILED', 'CANCELLED', null] as $status) {
            $this->assertFalse(
                $this->manager->isCompletedStatus($status),
                "Expected '{$status}' NOT to be a completed status."
            );
        }
    }

    // ------------------------------------------------------------------ //
    // isFailedStatus                                                       //
    // ------------------------------------------------------------------ //

    /** @test */
    public function it_returns_true_for_all_failed_statuses(): void
    {
        foreach (['EXPIRED', 'CANCELLED', 'FAILED'] as $status) {
            $this->assertTrue(
                $this->manager->isFailedStatus($status),
                "Expected '{$status}' to be a failed status."
            );
        }
    }

    /** @test */
    public function it_is_case_insensitive_for_failed_statuses(): void
    {
        foreach (['expired', 'cancelled', 'failed'] as $status) {
            $this->assertTrue(
                $this->manager->isFailedStatus($status),
                "Expected lowercase '{$status}' to be recognised as a failed status."
            );
        }
    }

    /** @test */
    public function it_returns_false_for_non_failed_statuses(): void
    {
        foreach (['COMPLETED', 'PAID', 'PENDING', null] as $status) {
            $this->assertFalse(
                $this->manager->isFailedStatus($status),
                "Expected '{$status}' NOT to be a failed status."
            );
        }
    }

    // ------------------------------------------------------------------ //
    // environment / baseUrl / apiKey                                       //
    // ------------------------------------------------------------------ //

    /** @test */
    public function it_returns_the_configured_environment(): void
    {
        $this->assertSame('staging', $this->manager->environment());
    }

    /** @test */
    public function it_returns_the_correct_base_url_for_staging(): void
    {
        $this->assertSame(
            'https://stg-cashi-services.alsoug.com/cashipay',
            $this->manager->baseUrl()
        );
    }

    /** @test */
    public function it_returns_the_correct_api_key_for_staging(): void
    {
        $this->assertSame(
            'test-staging-api-key',
            $this->manager->apiKey()
        );
    }
}
