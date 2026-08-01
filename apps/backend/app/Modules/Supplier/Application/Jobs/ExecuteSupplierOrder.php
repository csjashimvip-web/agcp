<?php

namespace App\Modules\Supplier\Application\Jobs;

use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Supplier\Application\Contracts\SupplierProviderFactory;
use App\Modules\Supplier\Application\SupplierRouter;
use App\Modules\Supplier\Domain\Models\Supplier;
use App\Modules\Supplier\Domain\Models\SupplierOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ExecuteSupplierOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $orderItemId,
    ) {
        $this->onQueue('supplier');
    }

    public function handle(
        SupplierRouter $router,
        SupplierProviderFactory $providers,
    ): void {
        $item = OrderItem::query()->with('order')->findOrFail($this->orderItemId);

        if (in_array($item->fulfillment_status, ['submitted', 'completed'], true)) {
            return;
        }

        $order = $item->order;
        $candidates = $router->candidates((int) $order->tenant_id, (int) $item->product_id);

        $attempt = 0;
        $errors = [];

        foreach ($candidates as $service) {
            $attempt++;
            $supplier = Supplier::query()->findOrFail($service->supplier_id);
            $submissionKey = "order-item:{$item->id}:supplier:{$supplier->id}";

            $supplierOrder = SupplierOrder::query()->firstOrCreate(
                ['submission_key' => $submissionKey],
                [
                    'tenant_id' => $order->tenant_id,
                    'order_item_id' => $item->id,
                    'supplier_id' => $supplier->id,
                    'supplier_service_id' => $service->id,
                    'supplier_order_uuid' => (string) Str::uuid(),
                    'status' => 'queued',
                    'attempt' => $attempt,
                    'cost_minor' => $service->cost_minor,
                    'currency' => $service->currency,
                    'request_payload' => [
                        'external_service_id' => $service->external_service_id,
                        'quantity' => $item->quantity,
                        'service_input' => $item->service_input,
                    ],
                ],
            );

            if (in_array($supplierOrder->status, ['submitted', 'completed'], true)) {
                $item->forceFill(['fulfillment_status' => 'submitted'])->save();
                return;
            }

            try {
                $provider = $providers->make($supplier);

                $result = $provider->submit(array_merge(
                    [
                        'ID' => $service->external_service_id,
                        'QNT' => $item->quantity,
                    ],
                    $item->service_input ?? [],
                ));

                $supplierOrder->forceFill([
                    'external_order_id' => $result['external_order_id'],
                    'status' => 'submitted',
                    'response_payload' => $result['raw'] ?? $result,
                    'submitted_at' => now(),
                    'failure_reason' => null,
                ])->save();

                $item->forceFill(['fulfillment_status' => 'submitted'])->save();

                DB::table('outbox_events')->insert([
                    'tenant_id' => $order->tenant_id,
                    'event_id' => (string) Str::uuid(),
                    'event_type' => 'supplier.order.submitted.v1',
                    'aggregate_type' => 'supplier_order',
                    'aggregate_id' => (string) $supplierOrder->id,
                    'payload' => json_encode([
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'supplier_order_id' => $supplierOrder->id,
                    ], JSON_THROW_ON_ERROR),
                    'occurred_at' => now(),
                    'available_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return;
            } catch (Throwable $e) {
                $errors[] = "{$supplier->code}: {$e->getMessage()}";

                $supplierOrder->forceFill([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                    'failed_at' => now(),
                ])->save();
            }
        }

        $item->forceFill(['fulfillment_status' => 'failed'])->save();

        DB::table('outbox_events')->insert([
            'tenant_id' => $order->tenant_id,
            'event_id' => (string) Str::uuid(),
            'event_type' => 'supplier.order.failed.v1',
            'aggregate_type' => 'order_item',
            'aggregate_id' => (string) $item->id,
            'payload' => json_encode([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'errors' => $errors,
            ], JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}