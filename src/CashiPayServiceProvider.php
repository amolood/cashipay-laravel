<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay;

use DigitalizeLab\CashiPay\Console\InstallCommand;
use DigitalizeLab\CashiPay\Contracts\CashiPayInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider that bootstraps the CashiPay package into Laravel.
 */
final class CashiPayServiceProvider extends ServiceProvider
{
    /**
     * Register package bindings into the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../config/cashipay.php',
            key: 'cashipay',
        );

        // Register the manager as a singleton under both the short key and the interface.
        $this->app->singleton('cashipay', function ($app): CashiPayManager {
            return new CashiPayManager(
                config: $app->make(ConfigRepository::class),
                events: $app->make(Dispatcher::class),
                http:   $app->make(HttpFactory::class),
            );
        });

        $this->app->alias('cashipay', CashiPayManager::class);
        $this->app->alias('cashipay', CashiPayInterface::class);
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerPublishables();
    }

    /**
     * Register the webhook route.
     *
     * Routes are only registered when the application is not running in the console
     * (to avoid polluting artisan output) or when routes are explicitly cached.
     */
    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }

    /**
     * Register publishable assets.
     */
    private function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes(
            paths: [
                __DIR__ . '/../config/cashipay.php' => config_path('cashipay.php'),
            ],
            groups: 'cashipay-config',
        );

        $this->commands([InstallCommand::class]);
    }

    /**
     * Declare the services provided by this provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'cashipay',
            CashiPayManager::class,
            CashiPayInterface::class,
        ];
    }
}
