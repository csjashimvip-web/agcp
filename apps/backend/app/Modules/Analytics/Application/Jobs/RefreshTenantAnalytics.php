<?php
namespace Modules\Analytics\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Analytics\Application\Services\AnalyticsPipelineService;

final class RefreshTenantAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public readonly string $tenantId, public readonly string $currency = 'USD')
    {
        $this->onQueue('reports');
    }

    public function handle(AnalyticsPipelineService $pipeline): void
    {
        $pipeline->run($this->tenantId, $this->currency);
    }
}
