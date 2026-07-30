<?php
namespace Modules\Analytics\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Analytics\Application\Services\AnalyticsPipelineService;
use Modules\Tenancy\Infrastructure\Models\Tenant;

final class RefreshAnalytics extends Command
{
    protected $signature = 'analytics:refresh {--tenant=} {--currency=USD}';
    protected $description = 'Refresh explainable analytics, forecasts, segments and AI-assisted insights.';

    public function handle(AnalyticsPipelineService $pipeline): int
    {
        $query = Tenant::query()->where('status', 'active');
        if ($tenant = $this->option('tenant')) $query->where(fn ($q) => $q->where('id', $tenant)->orWhere('slug', $tenant));
        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->warn('No active tenant matched.');
            return self::FAILURE;
        }
        foreach ($tenants as $item) {
            $this->info("Refreshing analytics for {$item->slug}...");
            $result = $pipeline->run($item->id, strtoupper((string) $this->option('currency')));
            $this->line(sprintf('Completed run %s with %d insight(s).', $result['run']->id, $result['insights']->count()));
        }
        return self::SUCCESS;
    }
}
