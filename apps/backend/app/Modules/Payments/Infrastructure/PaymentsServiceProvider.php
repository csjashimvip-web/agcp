<?php
namespace Modules\Payments\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Modules\Payments\Application\Services\PaymentProviderRegistry;
use Modules\Payments\Infrastructure\Console\ReconcilePayments;
use Modules\Payments\Infrastructure\Providers\SandboxPaymentProvider;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SandboxPaymentProvider::class);
        $this->app->singleton(PaymentProviderRegistry::class, fn ($app) => new PaymentProviderRegistry([
            $app->make(SandboxPaymentProvider::class),
        ]));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) $this->commands([ReconcilePayments::class]);
    }
}
