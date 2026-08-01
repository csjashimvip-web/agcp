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
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TransactionalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_authenticated_user_and_resolved_tenant(): void
    {
        Queue::fake();

        [$tenant, $user, $wallet, $product] = $this->fixture();

        $response = $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/checkout', [
                'wallet_id' => $wallet->id,
                'idempotency_key' => 'checkout-test-001',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertCreated();

        $order = Order::query()->firstOrFail();

        $this->assertSame($tenant->id, $order->tenant_id);
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame(5000, $order->total_minor);
        $this->assertSame(5000, $wallet->fresh()->available_balance_minor);

        $debits = (int) DB::table('ledger_entries')
            ->where('ledger_transaction_id', $order->ledger_transaction_id)
            ->where('direction', 'debit')
            ->sum('amount_minor');

        $credits = (int) DB::table('ledger_entries')
            ->where('ledger_transaction_id', $order->ledger_transaction_id)
            ->where('direction', 'credit')
            ->sum('amount_minor');

        $this->assertSame($debits, $credits);
        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'commerce.order.confirmed.v1',
            'aggregate_id' => (string) $order->id,
        ]);
    }

    public function test_checkout_requires_an_active_tenant_membership(): void
    {
        Queue::fake();

        [$tenant, $user, $wallet, $product] = $this->fixture();
        DB::table('tenant_memberships')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->update(['status' => 'inactive']);

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/checkout', [
                'wallet_id' => $wallet->id,
                'idempotency_key' => 'checkout-blocked-001',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_idempotency_does_not_double_debit(): void
    {
        Queue::fake();

        [$tenant, $user, $wallet, $product] = $this->fixture();

        $payload = [
            'wallet_id' => $wallet->id,
            'idempotency_key' => 'checkout-replay-001',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ];

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/checkout', $payload)
            ->assertCreated();

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/checkout', $payload)
            ->assertCreated();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(7500, $wallet->fresh()->available_balance_minor);
        $this->assertSame(1, DB::table('ledger_transactions')->count());
    }

    /**
     * @return array{Tenant,User,Wallet,Product}
     */
    private function fixture(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-'.uniqid(),
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $user = User::factory()->create();

        DB::table('tenant_memberships')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            'sku' => 'TEST-'.uniqid(),
            'name' => 'Test Service',
            'slug' => 'test-service-'.uniqid(),
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

        return [$tenant, $user, $wallet, $product];
    }
}