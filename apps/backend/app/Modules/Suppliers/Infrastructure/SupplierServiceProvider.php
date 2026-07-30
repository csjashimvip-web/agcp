<?php
namespace Modules\Suppliers\Infrastructure;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Infrastructure\Events\OutboxMessagePublished;
use Modules\Suppliers\Application\Services\SupplierProviderRegistry;
use Modules\Suppliers\Infrastructure\Console\CheckSupplierHealth;
use Modules\Suppliers\Infrastructure\Console\PollSupplierOrders;
use Modules\Suppliers\Infrastructure\Listeners\QueueSupplierFulfillment;
use Modules\Suppliers\Infrastructure\Providers\SandboxSupplierProvider;

final class SupplierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupplierProviderRegistry::class, function ($app): SupplierProviderRegistry {
            $registry = new SupplierProviderRegistry();
            $registry->register($app->make(SandboxSupplierProvider::class));
            return $registry;
        });
    }

    public function boot(): void
    {
        Event::listen(OutboxMessagePublished::class, QueueSupplierFulfillment::class);
        if ($this->app->runningInConsole()) $this->commands([PollSupplierOrders::class, CheckSupplierHealth::class]);
    }
}
