<?php

declare(strict_types=1);

namespace DigitalizeLab\CashiPay\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'cashipay:install';

    protected $description = 'Publish the CashiPay config and display setup instructions';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'cashipay-config', '--force' => false]);

        $this->newLine();
        $this->info('Add the following to your .env file:');
        $this->newLine();
        $this->line('CASHIPAY_ENV=staging');
        $this->line('CASHIPAY_STAGING_KEY=your-staging-api-key');
        $this->line('CASHIPAY_PRODUCTION_KEY=your-production-api-key');
        $this->line('CASHIPAY_WEBHOOK_SECRET=your-webhook-hmac-secret');
        $this->newLine();

        $laravelVersion = (int) app()->version();

        if ($laravelVersion >= 11) {
            $this->info('Exclude the webhook route from CSRF (bootstrap/app.php):');
            $this->newLine();
            $this->line('->withMiddleware(function (Middleware $middleware) {');
            $this->line("    \$middleware->validateCsrfTokens(except: ['cashipay/webhook']);");
            $this->line('})');
        } else {
            $this->info('Exclude the webhook route from CSRF (app/Http/Middleware/VerifyCsrfToken.php):');
            $this->newLine();
            $this->line("protected \$except = ['cashipay/webhook'];");
        }

        $this->newLine();
        $this->info('Done! See README for usage examples.');

        return self::SUCCESS;
    }
}
