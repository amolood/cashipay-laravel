<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Tests;

use DigitalizeLab\CashiPay\CashiPayServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

/**
 * Base test case for the CashiPay package.
 *
 * The parent class (Orchestra\Testbench\TestCase) and its trait
 * (Illuminate\Foundation\Testing\Concerns\MakesHttpRequests) are only
 * available after running `composer install` inside the package directory.
 * The annotations below allow IDE tooling to resolve inherited helpers
 * when the package is analysed inside a host Laravel project.
 *
 * @property \Illuminate\Contracts\Foundation\Application $app
 *
 * @method $this withHeaders(array<string, string> $headers)
 * @method \Illuminate\Testing\TestResponse get(string $uri, array<string, string> $headers = [])
 * @method \Illuminate\Testing\TestResponse getJson(string $uri, array<string, string> $headers = [])
 * @method \Illuminate\Testing\TestResponse post(string $uri, array<string, mixed> $data = [], array<string, string> $headers = [])
 * @method \Illuminate\Testing\TestResponse postJson(string $uri, array<string, mixed> $data = [], array<string, string> $headers = [])
 * @method \Illuminate\Testing\TestResponse put(string $uri, array<string, mixed> $data = [], array<string, string> $headers = [])
 * @method \Illuminate\Testing\TestResponse putJson(string $uri, array<string, mixed> $data = [], array<string, string> $headers = [])
 * @method \Illuminate\Testing\TestResponse patch(string $uri, array<string, mixed> $data = [], array<string, string> $headers = [])
 * @method \Illuminate\Testing\TestResponse delete(string $uri, array<string, mixed> $data = [], array<string, string> $headers = [])
 */
abstract class TestCase extends OrchestraTestCase
{
    /**
     * Load the CashiPay service provider for every test.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CashiPayServiceProvider::class,
        ];
    }

    /**
     * Define package aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'CashiPay' => \DigitalizeLab\CashiPay\CashiPayFacade::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cashipay.environment', 'staging');
        $app['config']->set('cashipay.environments.staging.base_url', 'https://stg-cashi-services.alsoug.com/cashipay');
        $app['config']->set('cashipay.environments.staging.api_key', 'test-staging-api-key');
        $app['config']->set('cashipay.webhook.secret', 'test-webhook-secret');
        $app['config']->set('cashipay.webhook.path', 'cashipay/webhook');
        $app['config']->set('cashipay.currency', 'SDG');
        $app['config']->set('cashipay.timeout', 30);
        $app['config']->set('cashipay.expiry_hours', 24);
        $app['config']->set('cashipay.merchant_order_key', 'merchantOrderId');
    }
}
