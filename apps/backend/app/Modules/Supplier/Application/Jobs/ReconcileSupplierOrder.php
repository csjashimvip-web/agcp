<?php

namespace App\Modules\Supplier\Application\Jobs;

use App\Modules\Marketplace\Application\CommissionAccrualService;
use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Supplier\Application\Contracts\SupplierProviderFactory;
use App\Modules\Supplier\Domain\Models\Supplier;
use App\Modules\Supplier\Domain\Models\SupplierOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ReconcileSupplierOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $supplierOrderId,
    ) {
        $this->onQueue('supplier');
    }

    public function handle(SupplierProviderFactory $providers): void
    {
        $supplierOrder = SupplierOrder::query()
            ->findOrFail($this->supplierOrderId);

        if (! $supplierOrder->external_order_id) {
            return;
        }

        if (in_array($supplierOrder->status, ['completed', 'failed'], true)) {
            return;
        }

        $supplier = Supplier::query()->findOrFail($supplierOrder->supplier_id);
        $previousStatus = $supplierOrder->status;

        try {
            $result = $providers
                ->make($supplier)
                ->status((string) $supplierOrder->external_order_id);

            $status = $this->normalize((string) ($result['status'] ?? 'pending'));

            $supplierOrder->forceFill([
                'status' => $status === 'processing' ? 'processing' : $status,
                'response_payload' => $result['raw'] ?? $result,
                'completed_at' => $status === 'completed'
                    ? now()
                    : $supplierOrder->completed_at,
                'failed_at' => $status === 'failed'
                    ? now()
                    : $supplierOrder->failed_at,
                'failure_reason' => $status === 'failed'
                    ? (string) ($result['result'] ?? 'Supplier rejected the order.')
                    : null,
            ])->save();

            $item = OrderItem::query()->findOrFail($supplierOrder->order_item_id);

            $itemStatus = match ($status) {
                'completed' => 'completed',
                'failed' => 'failed',
                'processing' => 'processing',
                default => 'submitted',
            };

            $item->forceFill([
                'fulfillment_status' => $itemStatus,
            ])->save();

            $this->reconcileParentOrder((int) $item->order_id);

            if ($previousStatus !== $supplierOrder->status) {
                DB::table('outbox_events')->insert([
                    'tenant_id' => $supplierOrder->tenant_id,
                    'event_id' => (string) Str::uuid(),
                    'event_type' => 'supplier.order.status_changed.v1',
                    'aggregate_type' => 'supplier_order',
                    'aggregate_id' => (string) $supplierOrder->id,
                    'payload' => json_encode([
                        'supplier_order_id' => $supplierOrder->id,
                        'order_item_id' => $supplierOrder->order_item_id,
                        'from' => $previousStatus,
                        'to' => $supplierOrder->status,
                        'result' => $result['result'] ?? null,
                    ], JSON_THROW_ON_ERROR),
                    'occurred_at' => now(),
                    'available_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (Throwable $e) {
            $supplierOrder->forceFill([
                'failure_reason' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    private function reconcileParentOrder(int $orderId): void
    {
        $statuses = DB::table('order_items')
            ->where('order_id', $orderId)
            ->pluck('fulfillment_status');

        if ($statuses->isEmpty()) {
            return;
        }

        if ($statuses->every(fn ($status) => $status === 'completed')) {
            DB::table('orders')->where('id', $orderId)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            app(CommissionAccrualService::class)
                ->accrueForOrder($orderId);

            return;
        }

        if ($statuses->contains('failed')) {
            DB::table('orders')->where('id', $orderId)->update([
                'status' => 'attention_required',
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('orders')->where('id', $orderId)->update([
            'status' => 'processing',
            'updated_at' => now(),
        ]);
    }

    private function normalize(string $status): string
    {
        $value = strtolower(trim($status));

        return match ($value) {
            'completed', 'complete', 'success', 'successful', 'done', '3' => 'completed',
            'failed', 'failure', 'rejected', 'reject', 'cancelled', 'canceled', '2' => 'failed',
            'processing', 'in_process', 'in process', 'progress', '1' => 'processing',
            default => 'pending',
        };
    }
}