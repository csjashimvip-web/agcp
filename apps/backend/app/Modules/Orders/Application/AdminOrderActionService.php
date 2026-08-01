<?php

namespace App\Modules\Orders\Application;

use App\Modules\Inventory\Domain\Models\InventoryItem;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Supplier\Application\Jobs\ExecuteSupplierOrder;
use App\Modules\Wallet\Application\OrderCompensationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class AdminOrderActionService
{
    public function __construct(
        private readonly OrderCompensationService $compensation,
    ) {
    }

    public function cancel(
        int $tenantId,
        int $orderId,
        string $reason,
    ): Order {
        return DB::transaction(function () use ($tenantId, $orderId, $reason): Order {
            $order = Order::query()
                ->with('items')
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($order->status === 'cancelled') {
                return $order;
            }

            $unsafeStatuses = ['submitted', 'processing', 'completed'];

            foreach ($order->items as $item) {
                if (in_array($item->fulfillment_status, $unsafeStatuses, true)) {
                    throw new RuntimeException(
                        'This order cannot be automatically cancelled because supplier fulfillment has already started.'
                    );
                }
            }

            if ($order->ledger_transaction_id) {
                $this->compensation->refundWalletOrder($order, $reason);
            }

            foreach ($order->items as $item) {
                $inventory = InventoryItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory && $inventory->track_inventory && $inventory->reserved > 0) {
                    $release = min(
                        (int) $inventory->reserved,
                        (int) $item->quantity
                    );

                    $inventory->decrement('reserved', $release);
                }

                $item->forceFill([
                    'fulfillment_status' => 'cancelled',
                ])->save();
            }

            $order->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();

            DB::table('order_status_events')->insert([
                'order_id' => $order->id,
                'from_status' => 'confirmed',
                'to_status' => 'cancelled',
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('outbox_events')->insert([
                'tenant_id' => $tenantId,
                'event_id' => (string) Str::uuid(),
                'event_type' => 'commerce.order.cancelled.v1',
                'aggregate_type' => 'order',
                'aggregate_id' => (string) $order->id,
                'payload' => json_encode([
                    'order_id' => $order->id,
                    'order_uuid' => $order->order_uuid,
                    'reason' => $reason,
                    'compensated' => (bool) $order->ledger_transaction_id,
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'available_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $order->fresh('items');
        }, 3);
    }

    public function retryFulfillment(
        int $tenantId,
        int $orderId,
    ): Order {
        $order = Order::query()
            ->with('items')
            ->where('tenant_id', $tenantId)
            ->findOrFail($orderId);

        if (in_array($order->status, ['cancelled', 'completed'], true)) {
            throw new RuntimeException(
                'Completed or cancelled orders cannot be retried.'
            );
        }

        foreach ($order->items as $item) {
            if ($item->fulfillment_status !== 'failed') {
                continue;
            }

            $item->forceFill([
                'fulfillment_status' => 'pending',
            ])->save();

            ExecuteSupplierOrder::dispatch($item->id)
                ->afterCommit()
                ->onQueue('supplier');
        }

        $order->forceFill([
            'status' => 'processing',
        ])->save();

        return $order->fresh('items');
    }
}