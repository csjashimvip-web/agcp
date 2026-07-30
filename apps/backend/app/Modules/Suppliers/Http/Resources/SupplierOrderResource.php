<?php
namespace Modules\Suppliers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SupplierOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'client_reference' => $this->client_reference,
            'supplier_reference' => $this->supplier_reference,
            'status' => $this->status->value,
            'attempts' => (int) $this->attempts,
            'max_attempts' => (int) $this->max_attempts,
            'request_payload' => $this->request_payload,
            'result_payload' => $this->result_payload,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'next_poll_at' => $this->next_poll_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'code' => $this->supplier->code,
                'provider' => $this->supplier->provider,
            ] : null),
            'service' => $this->whenLoaded('service', fn () => $this->service ? [
                'id' => $this->service->id,
                'supplier_service_code' => $this->service->supplier_service_code,
                'cost_minor' => (int) $this->service->cost_minor,
                'currency' => $this->service->currency,
            ] : null),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'number' => $this->order->number,
                'status' => $this->order->status->value,
                'payment_status' => $this->order->payment_status,
                'fulfillment_status' => $this->order->fulfillment_status,
            ]),
            'item' => $this->whenLoaded('orderItem', fn () => [
                'id' => $this->orderItem->id,
                'name' => $this->orderItem->item_name,
                'sku' => $this->orderItem->sku,
                'status' => $this->orderItem->status,
                'total_minor' => (int) $this->orderItem->total_minor,
            ]),
            'attempt_log' => $this->whenLoaded('attemptLogs', fn () => $this->attemptLogs->map(fn ($attempt) => [
                'id' => $attempt->id,
                'attempt_number' => (int) $attempt->attempt_number,
                'status' => $attempt->status,
                'routing_score' => $attempt->routing_score,
                'latency_ms' => $attempt->latency_ms,
                'error_code' => $attempt->error_code,
                'error_message' => $attempt->error_message,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'finished_at' => $attempt->finished_at?->toIso8601String(),
            ])),
            'routing_decisions' => $this->whenLoaded('decisions', fn () => $this->decisions->map(fn ($decision) => [
                'strategy' => $decision->strategy,
                'reason' => $decision->reason,
                'candidate_scores' => $decision->candidate_scores,
                'created_at' => $decision->created_at?->toIso8601String(),
            ])),
        ];
    }
}
