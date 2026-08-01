<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Inventory\Domain\Models\InventoryItem;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class OrderCompensationTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_order_cancellation_restores_wallet_and_inventory_once(): void
    {
        Queue::fake();
        $this->seed();

        $tenant = Tenant::query()->create([
            'name' => 'Compensation Tenant',
            'slug' => 'compensation-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $user = User::factory()->create();

        $membershipId = DB::table('tenant_memberships')->insertGetId([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')
            ->where('slug', 'platform-super-admin')
            ->value('id');

        DB::table('membership_role')->insert([
            'tenant_membership_id' => $membershipId,
            'role_id' => $roleId,
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
            'sku' => 'COMP-001',
            'name' => 'Compensation Service',
            'slug' => 'compensation-service',
            'type' => 'service',
            'status' => 'active',
            'currency' => 'USD',
            'price_minor' => 2500,
            'cost_minor' => 1000,
        ]);

        $inventory = InventoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'on_hand' => 10,
            'reserved' => 0,
            'reorder_level' => 1,
            'track_inventory' => true,
        ]);

        $checkout = $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/checkout', [
                'wallet_id' => $wallet->id,
                'idempotency_key' => 'compensation-checkout-001',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertCreated();

        $orderId = (int) $checkout->json('data.id');

        $this->assertSame(
            5000,
            $wallet->fresh()->available_balance_minor
        );

        $this->assertSame(
            2,
            $inventory->fresh()->reserved
        );

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson("/api/v1/admin/orders/{$orderId}/cancel", [
                'reason' => 'Customer requested cancellation',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(
            10000,
            $wallet->fresh()->available_balance_minor
        );

        $this->assertSame(
            0,
            $inventory->fresh()->reserved
        );

        $this->assertDatabaseCount('financial_compensations', 1);
        $this->assertDatabaseCount('ledger_transactions', 2);

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson("/api/v1/admin/orders/{$orderId}/cancel", [
                'reason' => 'Repeated cancellation request',
            ])
            ->assertOk();

        $this->assertSame(
            10000,
            $wallet->fresh()->available_balance_minor
        );

        $this->assertDatabaseCount('financial_compensations', 1);
        $this->assertDatabaseCount('ledger_transactions', 2);
    }
}