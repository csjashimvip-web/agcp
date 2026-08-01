<?php

namespace App\Providers;

use App\Modules\Supplier\Application\Contracts\SupplierProviderFactory;
use App\Modules\Supplier\Infrastructure\SupplierProviderFactory as DefaultSupplierProviderFactory;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        $this->app->bind(
            SupplierProviderFactory::class,
            DefaultSupplierProviderFactory::class,
        );
    }

    public function boot(): void
    {
        //
    }
}