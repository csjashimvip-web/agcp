<?php
namespace Modules\SaaS\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\SaaS\Infrastructure\Models\TenantUsageCounter;

final class UsageQuotaService
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    public function consume(string $tenantId, string $metric, int $amount = 1, array $metadata = []): TenantUsageCounter
    {
        if ($amount < 1) throw ValidationException::withMessages(['amount' => 'Usage amount must be positive.']);
        [$start, $end] = $this->period();
        return DB::transaction(function () use ($tenantId, $metric, $amount, $metadata, $start, $end): TenantUsageCounter {
            TenantUsageCounter::query()->firstOrCreate([
                'tenant_id' => $tenantId, 'metric' => $metric, 'period_start' => $start, 'period_end' => $end,
            ], ['quantity' => 0, 'limit_snapshot' => $this->entitlements->limit($tenantId, $metric), 'metadata' => []]);
            $counter = TenantUsageCounter::query()->where('tenant_id', $tenantId)->where('metric', $metric)
                ->where('period_start', $start)->where('period_end', $end)->lockForUpdate()->firstOrFail();
            $limit = $counter->limit_snapshot;
            if ($limit !== null && $counter->quantity + $amount > $limit) {
                throw ValidationException::withMessages(['quota' => "The {$metric} quota has been reached."]);
            }
            $counter->quantity += $amount;
            $counter->metadata = array_merge($counter->metadata ?? [], $metadata);
            $counter->save();
            return $counter->fresh();
        });
    }

    public function current(string $tenantId): array
    {
        [$start, $end] = $this->period();
        return TenantUsageCounter::query()->where('tenant_id', $tenantId)->where('period_start', $start)->where('period_end', $end)
            ->orderBy('metric')->get()->map(fn (TenantUsageCounter $counter) => [
                'metric' => $counter->metric, 'quantity' => $counter->quantity, 'limit' => $counter->limit_snapshot,
                'remaining' => $counter->limit_snapshot === null ? null : max(0, $counter->limit_snapshot - $counter->quantity),
                'period_end' => $counter->period_end->toIso8601String(),
            ])->all();
    }

    private function period(): array
    {
        $now = CarbonImmutable::now();
        return [$now->startOfMonth(), $now->addMonth()->startOfMonth()];
    }
}
