<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CustomerCommerceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_and_authenticated_deposit_request_are_tenant_scoped(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Customer Tenant',
            'slug' => 'customer-tenant',
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

        DB::table('products')->insert([
            'tenant_id' => $tenant->id,
            'sku' => 'PUBLIC-001',
            'name' => 'Public Service',
            'slug' => 'public-service',
            'type' => 'service',
            'status' => 'active',
            'currency' => 'USD',
            'price_minor' => 1000,
            'cost_minor' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $walletAccount = LedgerAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'customer-wallet-'.$user->id,
            'name' => 'Customer Wallet',
            'type' => 'asset',
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
            'available_balance_minor' => 0,
            'held_balance_minor' => 0,
        ]);

        $this
            ->getJson('/api/v1/storefront/customer-tenant/catalog')
            ->assertOk()
            ->assertJsonPath('data.products.0.sku', 'PUBLIC-001');

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/customer/deposits', [
                'wallet_id' => $wallet->id,
                'amount_minor' => 5000,
                'method' => 'manual',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('deposits', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'amount_minor' => 5000,
            'status' => 'pending',
        ]);
    }
}