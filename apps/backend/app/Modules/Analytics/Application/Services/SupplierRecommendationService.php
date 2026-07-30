<?php
namespace Modules\Analytics\Application\Services;

use Illuminate\Support\Collection;
use Modules\Analytics\Infrastructure\Models\SupplierRecommendation;
use Modules\Suppliers\Infrastructure\Models\SupplierService;

final class SupplierRecommendationService
{
    /** @return Collection<int, SupplierRecommendation> */
    public function refresh(string $tenantId): Collection
    {
        $variantIds = SupplierService::query()
            ->where('tenant_id', $tenantId)
            ->where('enabled', true)
            ->distinct()
            ->pluck('catalog_variant_id');

        return $variantIds->map(fn (string $variantId): ?SupplierRecommendation => $this->recommend($tenantId, $variantId))->filter()->values();
    }

    public function recommend(string $tenantId, string $variantId): ?SupplierRecommendation
    {
        $services = SupplierService::query()
            ->with('supplier')
            ->where('tenant_id', $tenantId)
            ->where('catalog_variant_id', $variantId)
            ->where('enabled', true)
            ->get()
            ->filter(fn (SupplierService $service): bool => $service->supplier !== null && $service->supplier->available());

        if ($services->isEmpty()) return null;

        $minCost = max(1, (int) $services->min('cost_minor'));
        $maxCost = max($minCost, (int) $services->max('cost_minor'));
        $candidates = $services->map(function (SupplierService $service) use ($minCost, $maxCost): array {
            $supplier = $service->supplier;
            $costRange = max(1, $maxCost - $minCost);
            $costScore = $maxCost === $minCost ? 100.0 : 100 - ((($service->cost_minor - $minCost) / $costRange) * 100);
            $latencyScore = max(0, 100 - min(100, ($supplier->average_latency_ms / 50)));
            $priorityScore = max(0, 100 - min(100, $service->priority));
            $score = ($supplier->health_score * 0.30)
                + ($supplier->success_rate * 0.30)
                + ($costScore * 0.20)
                + ($latencyScore * 0.15)
                + ($priorityScore * 0.05);

            return [
                'supplier_account_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'supplier_service_id' => $service->id,
                'cost_minor' => $service->cost_minor,
                'currency' => $service->currency,
                'health_score' => (float) $supplier->health_score,
                'success_rate' => (float) $supplier->success_rate,
                'average_latency_ms' => (int) $supplier->average_latency_ms,
                'score' => round($score, 3),
            ];
        })->sortByDesc('score')->values();

        $winner = $candidates->first();
        $runnerUp = $candidates->get(1);
        $confidence = $runnerUp === null ? 90.0 : min(99, 55 + max(0, ((float) $winner['score'] - (float) $runnerUp['score'])) * 4);

        SupplierRecommendation::query()
            ->where('tenant_id', $tenantId)
            ->where('catalog_variant_id', $variantId)
            ->delete();

        return SupplierRecommendation::query()->create([
            'tenant_id' => $tenantId,
            'catalog_variant_id' => $variantId,
            'recommended_supplier_account_id' => $winner['supplier_account_id'],
            'strategy' => 'explainable-balanced-v1',
            'score' => $winner['score'],
            'confidence' => round($confidence, 2),
            'candidates' => $candidates->all(),
            'reason' => sprintf('%s leads on the weighted combination of health, success, cost and latency.', $winner['supplier_name']),
            'generated_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);
    }
}
