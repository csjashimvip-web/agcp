<?php

namespace App\Modules\Reliability\Application;

use Illuminate\Support\Facades\DB;

final class SloEvaluator
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function evaluate(?int $tenantId = null): array
    {
        $objectives = DB::table('service_level_objectives')
            ->where('status', 'active')
            ->where(function ($query) use ($tenantId): void {
                $query->whereNull('tenant_id');

                if ($tenantId) {
                    $query->orWhere('tenant_id', $tenantId);
                }
            })
            ->orderBy('id')
            ->get();

        $results = [];

        foreach ($objectives as $objective) {
            $since = now()->subMinutes((int) $objective->window_minutes);

            $snapshots = DB::table('reliability_snapshots')
                ->where('captured_at', '>=', $since)
                ->where(function ($query) use ($tenantId): void {
                    $query->whereNull('tenant_id');

                    if ($tenantId) {
                        $query->orWhere('tenant_id', $tenantId);
                    }
                })
                ->get();

            $observed = $snapshots->isEmpty()
                ? null
                : (int) round($snapshots->avg('health_bps'));

            $results[] = [
                'id' => (int) $objective->id,
                'name' => (string) $objective->name,
                'metric_key' => (string) $objective->metric_key,
                'target_bps' => (int) $objective->target_bps,
                'observed_bps' => $observed,
                'met' => $observed !== null
                    && $observed >= (int) $objective->target_bps,
                'window_minutes' => (int) $objective->window_minutes,
            ];
        }

        return $results;
    }
}