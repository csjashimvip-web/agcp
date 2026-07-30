<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Modules\Reliability\Infrastructure\Models\RuntimeHeartbeat;
use Throwable;

final class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $components = [
            'database' => $this->probe(fn () => DB::select('SELECT 1')),
            'redis' => $this->probe(fn () => Redis::connection()->ping()),
            'scheduler' => $this->schedulerReady() ? 'passed' : 'failed',
        ];
        $ready = collect($components)->every(fn (string $status): bool => $status === 'passed');

        return response()->json([
            'status' => $ready ? 'passed' : 'failed',
            'service' => 'agcp-api',
            'version' => config('app.version'),
            'timestamp' => now()->toIso8601String(),
            'components' => $components,
        ], $ready ? 200 : 503);
    }

    private function schedulerReady(): bool
    {
        try {
            $heartbeat = RuntimeHeartbeat::query()->find('scheduler');
            return $heartbeat?->last_seen_at?->greaterThanOrEqualTo(
                now()->subMinutes(max(1, (int) config('reliability.heartbeat_ttl_minutes', 3))),
            ) ?? false;
        } catch (Throwable) {
            return false;
        }
    }

    private function probe(callable $probe): string
    {
        try {
            $probe();
            return 'passed';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
