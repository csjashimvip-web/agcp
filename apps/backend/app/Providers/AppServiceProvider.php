<?php

namespace App\Providers;

use App\Modules\Supplier\Application\Contracts\SupplierProviderFactory;
use App\Modules\Supplier\Infrastructure\SupplierProviderFactory as DefaultSupplierProviderFactory;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('reseller-api', function (Request $request): Limit {
            $client = $request->attributes->get('agcp_api_client');

            $limit = $client
                ? max(1, min((int) $client->rate_limit_per_minute, 3000))
                : 30;

            return Limit::perMinute($limit)
                ->by($client?->id ?? $request->ip());
        });
    }
}