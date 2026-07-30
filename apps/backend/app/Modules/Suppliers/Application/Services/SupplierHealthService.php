<?php
namespace Modules\Suppliers\Application\Services;

use Modules\Suppliers\Domain\Enums\SupplierAccountStatus;
use Modules\Suppliers\Infrastructure\Models\SupplierAccount;
use Modules\Suppliers\Infrastructure\Models\SupplierHealthCheck;
use Throwable;

final class SupplierHealthService
{
    public function __construct(private readonly SupplierProviderRegistry $providers) {}

    public function check(SupplierAccount $supplier): SupplierAccount
    {
        $started = hrtime(true);
        try {
            $result = $this->providers->get($supplier->provider)->health($supplier);
            $latency = (int) ($result['latency_ms'] ?? round((hrtime(true) - $started) / 1_000_000));
            $healthy = ($result['status'] ?? 'unhealthy') === 'healthy';
            $score = max(0.0, min(100.0, (float) ($result['score'] ?? ($healthy ? 100 : 0))));

            SupplierHealthCheck::query()->create([
                'supplier_account_id' => $supplier->id,
                'status' => $healthy ? 'healthy' : 'unhealthy',
                'score' => $score,
                'latency_ms' => $latency,
                'response_payload' => $result,
                'checked_at' => now(),
            ]);

            $supplier->forceFill([
                'health_status' => $healthy ? 'healthy' : 'unhealthy',
                'health_score' => $score,
                'average_latency_ms' => $this->averageLatency($supplier, $latency),
                'last_checked_at' => now(),
                'status' => $healthy ? SupplierAccountStatus::Active : SupplierAccountStatus::Degraded,
            ])->save();
        } catch (Throwable $exception) {
            SupplierHealthCheck::query()->create([
                'supplier_account_id' => $supplier->id,
                'status' => 'unhealthy',
                'score' => 0,
                'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'checked_at' => now(),
            ]);
            $supplier->forceFill([
                'health_status' => 'unhealthy',
                'health_score' => 0,
                'last_checked_at' => now(),
                'last_failure_at' => now(),
                'status' => SupplierAccountStatus::Degraded,
            ])->save();
        }

        return $supplier->fresh();
    }

    public function recordSuccess(SupplierAccount $supplier, int $latencyMs): void
    {
        $total = (int) $supplier->total_requests + 1;
        $successful = (int) $supplier->successful_requests + 1;
        $supplier->forceFill([
            'total_requests' => $total,
            'successful_requests' => $successful,
            'success_rate' => round($successful / $total * 100, 2),
            'average_latency_ms' => $this->averageLatency($supplier, $latencyMs),
            'consecutive_failures' => 0,
            'last_success_at' => now(),
            'health_status' => 'healthy',
            'health_score' => min(100, max((float) $supplier->health_score, 85)),
            'status' => SupplierAccountStatus::Active,
            'disabled_until' => null,
        ])->save();
    }

    public function recordFailure(SupplierAccount $supplier): void
    {
        $total = (int) $supplier->total_requests + 1;
        $failed = (int) $supplier->failed_requests + 1;
        $consecutive = (int) $supplier->consecutive_failures + 1;
        $fields = [
            'total_requests' => $total,
            'failed_requests' => $failed,
            'success_rate' => round(((int) $supplier->successful_requests) / $total * 100, 2),
            'consecutive_failures' => $consecutive,
            'last_failure_at' => now(),
            'health_score' => max(0, (float) $supplier->health_score - 15),
            'health_status' => $consecutive >= 3 ? 'unhealthy' : 'degraded',
            'status' => SupplierAccountStatus::Degraded,
        ];
        if ($consecutive >= 3) $fields['disabled_until'] = now()->addMinutes(min(60, 2 ** min($consecutive, 5)));
        $supplier->forceFill($fields)->save();
    }

    private function averageLatency(SupplierAccount $supplier, int $latencyMs): int
    {
        $previous = (int) $supplier->average_latency_ms;
        $count = max(0, (int) $supplier->total_requests);
        return $count === 0 ? $latencyMs : (int) round(($previous * $count + $latencyMs) / ($count + 1));
    }
}
