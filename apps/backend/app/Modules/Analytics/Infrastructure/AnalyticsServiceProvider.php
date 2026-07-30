<?php
namespace Modules\Analytics\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Modules\Analytics\Domain\Contracts\AiInsightProvider;
use Modules\Analytics\Infrastructure\Console\RefreshAnalytics;
use Modules\Analytics\Infrastructure\Providers\DeterministicInsightProvider;

final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiInsightProvider::class, DeterministicInsightProvider::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) $this->commands([RefreshAnalytics::class]);
    }
}
