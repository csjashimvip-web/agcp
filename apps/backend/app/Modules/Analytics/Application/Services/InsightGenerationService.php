<?php
namespace Modules\Analytics\Application\Services;

use Illuminate\Support\Collection;
use Modules\Analytics\Domain\Contracts\AiInsightProvider;
use Modules\Analytics\Infrastructure\Models\AiInsight;
use Modules\Analytics\Infrastructure\Models\AnalyticsSnapshot;
use Modules\Analytics\Infrastructure\Models\CustomerSegment;
use Modules\Analytics\Infrastructure\Models\SalesForecast;
use Modules\Analytics\Infrastructure\Models\SupplierRecommendation;

final class InsightGenerationService
{
    public function __construct(private readonly AiInsightProvider $provider) {}

    /** @return Collection<int, AiInsight> */
    public function generate(string $tenantId, AnalyticsSnapshot $snapshot, SalesForecast $forecast, Collection $segments, Collection $recommendations): Collection
    {
        $segmentCounts = $segments->groupBy(fn (CustomerSegment $segment): string => $segment->segment_code->value)->map->count()->all();
        $payloads = $this->provider->generate([
            'snapshot' => $snapshot->toArray(),
            'forecast' => $forecast->toArray(),
            'segments' => $segmentCounts,
            'supplier_recommendations' => $recommendations->map(fn (SupplierRecommendation $recommendation): array => $recommendation->toArray())->all(),
        ]);

        $activeFingerprints = collect($payloads)->map(fn (array $payload): string => hash('sha256', $payload['fingerprint']))->all();
        AiInsight::query()->where('tenant_id', $tenantId)->where('provider_key', $this->provider->key())
            ->when($activeFingerprints !== [], fn ($query) => $query->whereNotIn('fingerprint', $activeFingerprints))
            ->update(['status' => 'superseded']);

        return collect($payloads)->map(function (array $payload) use ($tenantId): AiInsight {
            $insight = AiInsight::query()->firstOrNew([
                'tenant_id' => $tenantId,
                'fingerprint' => hash('sha256', $payload['fingerprint']),
            ]);
            $dismissed = $insight->exists && $insight->status === 'dismissed';
            $insight->fill([
                'type' => $payload['type'],
                'severity' => $payload['severity'],
                'title' => $payload['title'],
                'summary' => $payload['summary'],
                'recommendations' => $payload['recommendations'],
                'evidence' => $payload['evidence'],
                'provider_key' => $this->provider->key(),
                'model_version' => $this->provider->version(),
                'status' => $dismissed ? 'dismissed' : 'active',
                'generated_at' => now(),
                'expires_at' => now()->addDay(),
            ]);
            $insight->save();
            return $insight;
        });
    }
}
