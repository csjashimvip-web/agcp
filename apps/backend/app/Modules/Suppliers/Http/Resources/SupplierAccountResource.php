<?php
namespace Modules\Suppliers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SupplierAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'provider' => $this->provider,
            'status' => $this->status->value,
            'priority' => (int) $this->priority,
            'timeout_seconds' => (int) $this->timeout_seconds,
            'max_retries' => (int) $this->max_retries,
            'country_codes' => $this->country_codes ?? [],
            'health_status' => $this->health_status,
            'health_score' => (float) $this->health_score,
            'success_rate' => (float) $this->success_rate,
            'average_latency_ms' => (int) $this->average_latency_ms,
            'total_requests' => (int) $this->total_requests,
            'successful_requests' => (int) $this->successful_requests,
            'failed_requests' => (int) $this->failed_requests,
            'consecutive_failures' => (int) $this->consecutive_failures,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'disabled_until' => $this->disabled_until?->toIso8601String(),
            'metadata' => $this->metadata,
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($service) => [
                'id' => $service->id,
                'catalog_variant_id' => $service->catalog_variant_id,
                'supplier_service_code' => $service->supplier_service_code,
                'cost_minor' => (int) $service->cost_minor,
                'currency' => $service->currency,
                'estimated_seconds' => (int) $service->estimated_seconds,
                'priority' => (int) $service->priority,
                'enabled' => (bool) $service->enabled,
                'variant' => $service->relationLoaded('variant') ? [
                    'id' => $service->variant->id,
                    'name' => $service->variant->name,
                    'sku' => $service->variant->sku,
                    'item_name' => $service->variant->item?->name,
                ] : null,
            ])),
        ];
    }
}
