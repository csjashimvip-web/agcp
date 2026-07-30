<?php
namespace Modules\Analytics\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Analytics\Domain\Enums\ModelRunStatus;
use Modules\Analytics\Infrastructure\Models\AiModelRun;
use Modules\Audit\Application\AuditLogger;
use Throwable;

final class AnalyticsPipelineService
{
    public function __construct(
        private readonly AnalyticsSnapshotService $snapshots,
        private readonly CustomerSegmentationService $segments,
        private readonly SalesForecastService $forecasts,
        private readonly SupplierRecommendationService $supplierRecommendations,
        private readonly InsightGenerationService $insights,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array<string, mixed> */
    public function run(string $tenantId, string $currency = 'USD', int $windowDays = 30, int $horizonDays = 14): array
    {
        $end = CarbonImmutable::today('UTC');
        $start = $end->subDays(max(1, $windowDays) - 1);
        $run = AiModelRun::query()->create([
            'tenant_id' => $tenantId,
            'run_type' => 'tenant-analytics-refresh',
            'provider_key' => 'deterministic',
            'model_version' => 'agcp-explainable-v1',
            'status' => ModelRunStatus::Running,
            'input_window_start' => $start,
            'input_window_end' => $end,
            'started_at' => now(),
        ]);

        try {
            $snapshot = $this->snapshots->calculate($tenantId, $start, $end, $currency);
            $segments = $this->segments->refresh($tenantId);
            $forecast = $this->forecasts->generate($tenantId, $currency, $horizonDays, $windowDays);
            $recommendations = $this->supplierRecommendations->refresh($tenantId);
            $insights = $this->insights->generate($tenantId, $snapshot, $forecast, $segments, $recommendations);
            $processed = 2 + $segments->count() + $recommendations->count() + $insights->count();
            $metrics = [
                'snapshot_id' => $snapshot->id,
                'forecast_id' => $forecast->id,
                'segments' => $segments->count(),
                'supplier_recommendations' => $recommendations->count(),
                'insights' => $insights->count(),
            ];
            $run->update([
                'status' => ModelRunStatus::Completed,
                'completed_at' => now(),
                'records_processed' => $processed,
                'metrics' => $metrics,
                'error_message' => null,
            ]);
            $this->audit->record('analytics.pipeline.completed', AiModelRun::class, $run->id, $metrics, [], $tenantId, 'system');
            return ['run' => $run->fresh(), 'snapshot' => $snapshot, 'forecast' => $forecast, 'segments' => $segments, 'supplier_recommendations' => $recommendations, 'insights' => $insights];
        } catch (Throwable $exception) {
            $run->update(['status' => ModelRunStatus::Failed, 'completed_at' => now(), 'error_message' => mb_substr($exception->getMessage(), 0, 4000)]);
            $this->audit->record('analytics.pipeline.failed', AiModelRun::class, $run->id, ['error' => $exception->getMessage()], [], $tenantId, 'system');
            throw $exception;
        }
    }
}
