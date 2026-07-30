<?php
namespace Modules\Tenancy\Infrastructure;
use Illuminate\Support\ServiceProvider;
use Modules\Tenancy\Application\TenantContext;
class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void { $this->app->singleton(TenantContext::class); }
}
