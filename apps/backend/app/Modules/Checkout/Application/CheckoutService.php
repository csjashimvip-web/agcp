<?php

namespace App\Modules\Checkout\Application;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Inventory\Domain\Models\InventoryItem;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Supplier\Application\Jobs\ExecuteSupplierOrder;
use App\Modules\Wallet\Application\WalletPostingService;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CheckoutService
{
    public function __construct(
        private readonly WalletPostingService $walletPosting,
    ) {
    }

    /**
     * @param array<int, array{product_id:int,quantity:int,service_input?:array<string,mixed>}> $items
     */
    public function checkout(
        int $tenantId,
        int $userId,
        Wallet $wallet,
        array $items,
        string $idempotencyKey,
    ): Order {
        if ($wallet->tenant_id !== $tenantId || $wallet->user_id !== $userId) {
            throw new InvalidArgumentException('Wallet does not belong to the checkout identity.');
        }

        if ($items === []) {
            throw new InvalidArgumentException('Checkout requires at least one item.');
        }

        return DB::transaction(function () use ($tenantId, $userId, $wallet, $items, $idempotencyKey): Order {
            $existing = Order::query()
                ->where('tenant_id', $tenantId)
                ->where('metadata->checkout_idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $resolved = [];
            $subtotal = 0;

            foreach ($items as $line) {
                $quantity = max(1, (int) $line['quantity']);

                $product = Product::query()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->findOrFail((int) $line['product_id']);

                $inventory = InventoryItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory && $inventory->track_inventory) {
                    $available = $inventory->on_hand - $inventory->reserved;

                    if ($available < $quantity) {
                        throw new InvalidArgumentException("Insufficient inventory for {$product->sku}.");
                    }

                    $inventory->increment('reserved', $quantity);
                }

                $lineTotal = $product->price_minor * $quantity;
                $subtotal += $lineTotal;

                $resolved[] = compact('product', 'quantity', 'lineTotal') + [
                    'service_input' => $line['service_input'] ?? null,
                ];
            }

            $order = Order::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'order_uuid' => (string) Str::uuid(),
                'order_number' => 'AGCP-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'status' => 'pending',
                'currency' => $wallet->currency,
                'subtotal_minor' => $subtotal,
                'discount_minor' => 0,
                'surcharge_minor' => 0,
                'total_minor' => $subtotal,
                'metadata' => [
                    'checkout_idempotency_key' => $idempotencyKey,
                ],
            ]);

            foreach ($resolved as $line) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'sku' => $line['product']->sku,
                    'name' => $line['product']->name,
                    'quantity' => $line['quantity'],
                    'unit_price_minor' => $line['product']->price_minor,
                    'unit_cost_minor' => $line['product']->cost_minor,
                    'line_total_minor' => $line['lineTotal'],
                    'service_input' => $line['service_input'],
                    'fulfillment_status' => 'pending',
                ]);
            }

            $ledger = $this->walletPosting->debitWallet(
                wallet: $wallet,
                amountMinor: $subtotal,
                idempotencyKey: 'checkout:'.$idempotencyKey,
                referenceType: 'order',
                referenceId: (string) $order->id,
                description: 'Checkout debit for '.$order->order_number,
            );

            $order->forceFill([
                'status' => 'confirmed',
                'ledger_transaction_id' => $ledger->id,
                'confirmed_at' => now(),
            ])->save();

            DB::table('outbox_events')->insert([
                'tenant_id' => $tenantId,
                'event_id' => (string) Str::uuid(),
                'event_type' => 'commerce.order.confirmed.v1',
                'aggregate_type' => 'order',
                'aggregate_id' => (string) $order->id,
                'payload' => json_encode([
                    'order_id' => $order->id,
                    'order_uuid' => $order->order_uuid,
                    'total_minor' => $order->total_minor,
                    'currency' => $order->currency,
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'available_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($order->items as $orderItem) {
                ExecuteSupplierOrder::dispatch($orderItem->id)->afterCommit();
            }

            return $order->fresh('items');
        }, 3);
    }
}