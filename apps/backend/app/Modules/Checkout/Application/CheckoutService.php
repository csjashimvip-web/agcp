<?php

namespace App\Modules\Checkout\Application;

use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Fraud\Application\FraudGuard;
use App\Modules\Inventory\Domain\Models\InventoryItem;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Orders\Domain\Models\OrderItem;
use App\Modules\Pricing\Application\PricingEngine;
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
        private readonly PricingEngine $pricing,
        private readonly FraudGuard $fraud,
    ) {
    }

    /**
     * @param array<int, array{
     *   product_id:int,
     *   quantity:int,
     *   service_input?:array<string,mixed>|null
     * }> $items
     */
    public function checkout(
        int $tenantId,
        int $userId,
        Wallet $wallet,
        array $items,
        string $idempotencyKey,
        ?string $couponCode = null,
    ): Order {
        if ($wallet->tenant_id !== $tenantId || $wallet->user_id !== $userId) {
            throw new InvalidArgumentException(
                'Wallet does not belong to the checkout identity.'
            );
        }

        if ($items === []) {
            throw new InvalidArgumentException(
                'Checkout requires at least one item.'
            );
        }

        $existing = Order::query()
            ->where('tenant_id', $tenantId)
            ->where(
                'metadata->checkout_idempotency_key',
                $idempotencyKey
            )
            ->first();

        if ($existing) {
            return $existing->load('items');
        }

        $preview = $this->pricing->quote(
            tenantId: $tenantId,
            userId: $userId,
            items: array_map(
                fn (array $line): array => [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => max(1, (int) $line['quantity']),
                ],
                $items
            ),
            couponCode: $couponCode,
        );

        $this->fraud->assertCheckoutAllowed(
            tenantId: $tenantId,
            userId: $userId,
            quoteTotalMinor: (int) $preview['total_minor'],
            fingerprint: $tenantId.':'.$userId,
        );

        return DB::transaction(function () use (
            $tenantId,
            $userId,
            $wallet,
            $items,
            $idempotencyKey,
            $couponCode,
        ): Order {
            $existing = Order::query()
                ->where('tenant_id', $tenantId)
                ->where(
                    'metadata->checkout_idempotency_key',
                    $idempotencyKey
                )
                ->first();

            if ($existing) {
                return $existing->load('items');
            }

            $quote = $this->pricing->quote(
                tenantId: $tenantId,
                userId: $userId,
                items: array_map(
                    fn (array $line): array => [
                        'product_id' => (int) $line['product_id'],
                        'quantity' => max(1, (int) $line['quantity']),
                    ],
                    $items
                ),
                couponCode: $couponCode,
            );

            $resolved = [];

            foreach ($quote['lines'] as $index => $pricedLine) {
                $sourceLine = $items[$index];

                $product = Product::query()
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->findOrFail((int) $pricedLine['product_id']);

                $quantity = (int) $pricedLine['quantity'];

                $inventory = InventoryItem::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory && $inventory->track_inventory) {
                    $available = $inventory->on_hand - $inventory->reserved;

                    if ($available < $quantity) {
                        throw new InvalidArgumentException(
                            "Insufficient inventory for {$product->sku}."
                        );
                    }

                    $inventory->increment('reserved', $quantity);
                }

                $resolved[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price_minor' => (int) $pricedLine['unit_price_minor'],
                    'line_total_minor' => (int) $pricedLine['line_total_minor'],
                    'service_input' => $sourceLine['service_input'] ?? null,
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
                'subtotal_minor' => $quote['subtotal_minor'],
                'discount_minor' => $quote['discount_minor'],
                'surcharge_minor' => $quote['surcharge_minor'],
                'tax_minor' => $quote['tax_minor'],
                'total_minor' => $quote['total_minor'],
                'coupon_id' => $quote['coupon_id'],
                'pricing_snapshot' => [
                    'tier_id' => $quote['tier_id'],
                    'tier_name' => $quote['tier_name'],
                    'coupon_code' => $quote['coupon_code'],
                    'coupon_discount_minor' => $quote['coupon_discount_minor'],
                    'rule_discount_minor' => $quote['rule_discount_minor'],
                    'pricing_rules' => $quote['pricing_rules'],
                    'lines' => $quote['lines'],
                ],
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
                    'unit_price_minor' => $line['unit_price_minor'],
                    'unit_cost_minor' => $line['product']->cost_minor,
                    'line_total_minor' => $line['line_total_minor'],
                    'service_input' => $line['service_input'],
                    'fulfillment_status' => 'pending',
                ]);
            }

            $ledger = $this->walletPosting->debitWallet(
                wallet: $wallet,
                amountMinor: (int) $quote['total_minor'],
                idempotencyKey: 'checkout:'.$idempotencyKey,
                referenceType: 'order',
                referenceId: (string) $order->id,
                description: 'Checkout debit for '.$order->order_number,
            );

            if ($quote['coupon_id']) {
                DB::table('coupon_redemptions')->insert([
                    'tenant_id' => $tenantId,
                    'coupon_id' => $quote['coupon_id'],
                    'user_id' => $userId,
                    'order_id' => $order->id,
                    'discount_minor' => $quote['discount_minor'],
                    'redeemed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

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
                    'subtotal_minor' => $order->subtotal_minor,
                    'discount_minor' => $order->discount_minor,
                    'tax_minor' => $order->tax_minor,
                    'total_minor' => $order->total_minor,
                    'currency' => $order->currency,
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
                'available_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($order->items as $orderItem) {
                ExecuteSupplierOrder::dispatch($orderItem->id)
                    ->afterCommit();
            }

            return $order->fresh('items');
        }, 3);
    }
}