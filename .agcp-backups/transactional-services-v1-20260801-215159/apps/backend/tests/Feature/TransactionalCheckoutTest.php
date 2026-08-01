<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Inventory\Domain\Models\InventoryItem;
use App\Modules\Orders\Domain\Models\Order;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TransactionalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_posts_a_balanced_wallet_debit_and_creates_an_outbox_event(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $user = User::factory()->create();

        $walletAccount = LedgerAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'wallet-'.$user->id,
            'name' => 'Customer Wallet',
            'type' => 'asset',
            'currency' => 'USD',
            'status' => 'active',
            'balance_minor' => 10000,
        ]);

        LedgerAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'commerce-clearing',
            'name' => 'Commerce Clearing',
            'type' => 'revenue',
            'currency' => 'USD',
            'status' => 'active',
            'balance_minor' => 0,
        ]);

        $wallet = Wallet::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'ledger_account_id' => $walletAccount->id,
            'currency' => 'USD',
            'status' => 'active',
            'available_balance_minor' => 10000,
            'held_balance_minor' => 0,
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'TEST-001',
            'name' => 'Test Service',
            'slug' => 'test-service',
            'type' => 'service',
            'status' => 'active',
            'currency' => 'USD',
            'price_minor' => 2500,
            'cost_minor' => 1000,
        ]);

        InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'on_hand' => 10,
            'reserved' => 0,
            'reorder_level' => 1,
            'track_inventory' => true,
        ]);

        $response = $this->postJson('/api/v1/checkout', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'idempotency_key' => 'checkout-test-001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertCreated();

        $order = Order::query()->firstOrFail();

        $this->assertSame('confirmed', $order->status);
        $this->assertSame(5000, $order->total_minor);
        $this->assertSame(5000, $wallet->fresh()->available_balance_minor);

        $transactionId = $order->ledger_transaction_id;

        $debits = (int) DB::table('ledger_entries')
            ->where('ledger_transaction_id', $transactionId)
            ->where('direction', 'debit')
            ->sum('amount_minor');

        $credits = (int) DB::table('ledger_entries')
            ->where('ledger_transaction_id', $transactionId)
            ->where('direction', 'credit')
            ->sum('amount_minor');

        $this->assertSame($debits, $credits);

        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'commerce.order.confirmed.v1',
            'aggregate_type' => 'order',
            'aggregate_id' => (string) $order->id,
        ]);

        $this->assertSame(
            2,
            InventoryItem::query()->where('product_id', $product->id)->value('reserved'),
        );
    }

    public function test_checkout_idempotency_does_not_double_debit_the_wallet(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Replay Tenant',
            'slug' => 'replay-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $user = User::factory()->create();

        $walletAccount = LedgerAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'wallet-'.$user->id,
            'name' => 'Customer Wallet',
            'type' => 'asset',
            'currency' => 'USD',
            'status' => 'active',
            'balance_minor' => 10000,
        ]);

        LedgerAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'commerce-clearing',
            'name' => 'Commerce Clearing',
            'type' => 'revenue',
            'currency' => 'USD',
            'status' => 'active',
            'balance_minor' => 0,
        ]);

        $wallet = Wallet::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'ledger_account_id' => $walletAccount->id,
            'currency' => 'USD',
            'status' => 'active',
            'available_balance_minor' => 10000,
            'held_balance_minor' => 0,
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'REPLAY-001',
            'name' => 'Replay Service',
            'slug' => 'replay-service',
            'type' => 'service',
            'status' => 'active',
            'currency' => 'USD',
            'price_minor' => 2000,
            'cost_minor' => 1000,
        ]);

        $payload = [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'idempotency_key' => 'checkout-replay-001',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ];

        $this->postJson('/api/v1/checkout', $payload)->assertCreated();
        $this->postJson('/api/v1/checkout', $payload)->assertCreated();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(8000, $wallet->fresh()->available_balance_minor);
        $this->assertSame(1, DB::table('ledger_transactions')->count());
    }
}