<?php
namespace Modules\Suppliers\Application\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Suppliers\Domain\Enums\RoutingStrategy;
use Modules\Suppliers\Infrastructure\Models\SupplierOrder;
use Modules\Suppliers\Infrastructure\Models\SupplierRoutingDecision;
use Modules\Suppliers\Infrastructure\Models\SupplierRoutingProfile;
use Modules\Suppliers\Infrastructure\Models\SupplierService;

final class SupplierRoutingEngine
{
    /**
     * @param list<string> $excludedSupplierIds
     * @return array{service:SupplierService,score:float,strategy:RoutingStrategy,candidates:array<int,array<string,mixed>>,reason:string,profile:?SupplierRoutingProfile}
     */
    public function select(SupplierOrder $supplierOrder, array $excludedSupplierIds = []): array
    {
        $supplierOrder->loadMissing(['order', 'orderItem']);
        $countryCode = strtoupper((string) (
            $supplierOrder->request_payload['country_code']
            ?? $supplierOrder->request_payload['country']
            ?? $supplierOrder->order->metadata['country_code']
            ?? ''
        ));
        $profile = SupplierRoutingProfile::query()
            ->where('tenant_id', $supplierOrder->tenant_id)
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->oldest()
            ->first();
        $strategy = $profile?->strategy ?? RoutingStrategy::Balanced;

        $services = SupplierService::query()
            ->with('supplier')
            ->where('tenant_id', $supplierOrder->tenant_id)
            ->where('catalog_variant_id', $supplierOrder->orderItem->catalog_variant_id)
            ->where('currency', $supplierOrder->order->currency)
            ->where('enabled', true)
            ->when($excludedSupplierIds !== [], fn ($query) => $query->whereNotIn('supplier_account_id', $excludedSupplierIds))
            ->get()
            ->filter(function (SupplierService $service) use ($countryCode): bool {
                if ($service->supplier?->available() !== true) return false;
                $countries = array_map('strtoupper', $service->supplier->country_codes ?? []);
                return $countryCode === '' || $countries === [] || in_array($countryCode, $countries, true);
            })
            ->values();

        if ($services->isEmpty()) {
            throw ValidationException::withMessages(['supplier' => 'No healthy supplier mapping is available for this order item.']);
        }

        $scored = $this->score($services, $strategy, $profile?->weights ?? []);
        $selected = collect($scored)->sort(function (array $left, array $right): int {
            return ($right['score'] <=> $left['score'])
                ?: ($left['priority'] <=> $right['priority'])
                ?: ($left['cost_minor'] <=> $right['cost_minor'])
                ?: strcmp($left['supplier_id'], $right['supplier_id']);
        })->first();
        /** @var SupplierService $service */
        $service = $services->firstWhere('id', $selected['supplier_service_id']);
        $reason = sprintf(
            '%s selected using %s strategy: score %.3f, cost %d %s minor units, success %.2f%%, latency %dms.',
            $service->supplier->name,
            $strategy->value,
            $selected['score'],
            $selected['cost_minor'],
            $service->currency,
            $selected['success_rate'],
            $selected['latency_ms'],
        );
        if ($countryCode !== '') $reason .= ' Country '.$countryCode.' matched the supplier availability policy.';

        SupplierRoutingDecision::query()->create([
            'supplier_order_id' => $supplierOrder->id,
            'selected_supplier_account_id' => $service->supplier_account_id,
            'selected_supplier_service_id' => $service->id,
            'strategy' => $strategy->value,
            'candidate_scores' => $scored,
            'reason' => $reason,
        ]);

        return [
            'service' => $service,
            'score' => (float) $selected['score'],
            'strategy' => $strategy,
            'candidates' => $scored,
            'reason' => $reason,
            'profile' => $profile,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function score(Collection $services, RoutingStrategy $strategy, array $customWeights): array
    {
        $costs = $services->map(fn (SupplierService $service) => (int) $service->cost_minor);
        $latencies = $services->map(fn (SupplierService $service) => max(1, (int) ($service->supplier->average_latency_ms ?: $service->estimated_seconds * 1000)));
        $minCost = (int) $costs->min();
        $maxCost = (int) $costs->max();
        $minLatency = (int) $latencies->min();
        $maxLatency = (int) $latencies->max();

        $weights = match ($strategy) {
            RoutingStrategy::Cheapest => ['cost' => 0.75, 'success' => 0.10, 'latency' => 0.05, 'health' => 0.05, 'priority' => 0.05],
            RoutingStrategy::Fastest => ['cost' => 0.05, 'success' => 0.10, 'latency' => 0.70, 'health' => 0.10, 'priority' => 0.05],
            RoutingStrategy::HighestSuccess => ['cost' => 0.05, 'success' => 0.70, 'latency' => 0.05, 'health' => 0.15, 'priority' => 0.05],
            RoutingStrategy::Priority => ['cost' => 0.05, 'success' => 0.10, 'latency' => 0.05, 'health' => 0.10, 'priority' => 0.70],
            RoutingStrategy::Balanced => ['cost' => 0.30, 'success' => 0.30, 'latency' => 0.15, 'health' => 0.15, 'priority' => 0.10],
        };
        foreach ($weights as $key => $value) {
            if (array_key_exists($key, $customWeights)) $weights[$key] = max(0.0, min(1.0, (float) $customWeights[$key]));
        }
        $totalWeight = max(0.0001, array_sum($weights));

        return $services->map(function (SupplierService $service) use ($weights, $totalWeight, $minCost, $maxCost, $minLatency, $maxLatency): array {
            $supplier = $service->supplier;
            $cost = (int) $service->cost_minor;
            $latency = max(1, (int) ($supplier->average_latency_ms ?: $service->estimated_seconds * 1000));
            $costScore = $maxCost === $minCost ? 100.0 : 100.0 - (($cost - $minCost) / ($maxCost - $minCost) * 100.0);
            $latencyScore = $maxLatency === $minLatency ? 100.0 : 100.0 - (($latency - $minLatency) / ($maxLatency - $minLatency) * 100.0);
            $priority = min((int) $supplier->priority, (int) $service->priority);
            $priorityScore = max(0.0, 100.0 - min(100, $priority));
            $failurePenalty = min(50.0, (float) $supplier->consecutive_failures * 10.0);
            $score = (
                $costScore * $weights['cost']
                + (float) $supplier->success_rate * $weights['success']
                + $latencyScore * $weights['latency']
                + (float) $supplier->health_score * $weights['health']
                + $priorityScore * $weights['priority']
            ) / $totalWeight - $failurePenalty;

            return [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'supplier_service_id' => $service->id,
                'score' => round(max(0.0, $score), 3),
                'cost_minor' => $cost,
                'currency' => $service->currency,
                'success_rate' => (float) $supplier->success_rate,
                'health_score' => (float) $supplier->health_score,
                'latency_ms' => $latency,
                'priority' => $priority,
                'consecutive_failures' => (int) $supplier->consecutive_failures,
                'country_codes' => $supplier->country_codes ?? [],
            ];
        })->all();
    }
}
