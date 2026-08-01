<?php

namespace App\Modules\Reliability\Application;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PerformanceBaselineService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function capture(
        string $environment = 'local',
        int $samples = 25,
    ): array {
        $samples = max(5, min($samples, 200));

        $probes = [
            'database_select_1' => fn () => DB::select('select 1'),
            'cache_roundtrip' => function (): void {
                $key = 'agcp:perf:'.Str::random(8);
                Cache::put($key, 'ok', 30);
                Cache::get($key);
                Cache::forget($key);
            },
            'product_count' => fn () => DB::table('products')->count(),
            'order_count' => fn () => DB::table('orders')->count(),
        ];

        $results = [];

        foreach ($probes as $name => $probe) {
            $durations = [];

            for ($i = 0; $i < $samples; $i++) {
                $start = hrtime(true);
                $probe();
                $durations[] = (hrtime(true) - $start) / 1_000_000;
            }

            sort($durations);

            $result = [
                'probe' => $name,
                'sample_count' => $samples,
                'p50_ms' => $this->percentile($durations, 0.50),
                'p95_ms' => $this->percentile($durations, 0.95),
                'p99_ms' => $this->percentile($durations, 0.99),
                'max_ms' => (int) ceil(max($durations)),
            ];

            DB::table('performance_baselines')->insert([
                'baseline_uuid' => (string) Str::uuid(),
                'environment' => $environment,
                'probe' => $name,
                'sample_count' => $samples,
                'p50_ms' => $result['p50_ms'],
                'p95_ms' => $result['p95_ms'],
                'p99_ms' => $result['p99_ms'],
                'max_ms' => $result['max_ms'],
                'metadata' => json_encode([
                    'php_version' => PHP_VERSION,
                ], JSON_THROW_ON_ERROR),
                'captured_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $results[] = $result;
        }

        return $results;
    }

    /**
     * @param array<int,float> $values
     */
    private function percentile(array $values, float $ratio): int
    {
        $index = (int) floor((count($values) - 1) * $ratio);

        return (int) ceil($values[$index]);
    }
}