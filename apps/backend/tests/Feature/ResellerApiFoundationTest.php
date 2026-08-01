<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ResellerApiFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hashed_api_key_can_read_services_and_place_idempotent_order(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Reseller Tenant',
            'slug' => 'reseller-tenant',
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
            'code' => 'reseller-wallet-'.$user->id,
            'name' => 'Reseller Wallet',
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

        Wallet::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'ledger_account_id' => $walletAccount->id,
            'currency' => 'USD',
            'status' => 'active',
            'available_balance_minor' => 10000,
            'held_balance_minor' => 0,
        ]);

        $productId = DB::table('products')->insertGetId([
            'tenant_id' => $tenant->id,
            'sku' => 'API-SVC-001',
            'name' => 'API Service',
            'slug' => 'api-service',
            'type' => 'service',
            'status' => 'active',
            'currency' => 'USD',
            'price_minor' => 2500,
            'cost_minor' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventory_items')->insert([
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'on_hand' => 100,
            'reserved' => 0,
            'reorder_level' => 0,
            'track_inventory' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $publicId = (string) Str::ulid();
        $secret = Str::random(64);

        DB::table('reseller_api_clients')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'public_id' => $publicId,
            'name' => 'Test Client',
            'secret_hash' => hash('sha256', $secret),
            'abilities' => json_encode([
                'services:read',
                'wallet:read',
                'orders:create',
                'orders:read',
            ], JSON_THROW_ON_ERROR),
            'status' => 'active',
            'rate_limit_per_minute' => 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = 'agcp_'.$publicId.'.'.$secret;

        $this
            ->withToken($token)
            ->getJson('/api/reseller/v1/services')
            ->assertOk()
            ->assertJsonPath('data.0.sku', 'API-SVC-001');

        $payload = [
            'external_reference' => 'EXT-ORDER-001',
            'sku' => 'API-SVC-001',
            'quantity' => 1,
        ];

        $this
            ->withToken($token)
            ->postJson('/api/reseller/v1/orders', $payload)
            ->assertCreated();

        $this
            ->withToken($token)
            ->postJson('/api/reseller/v1/orders', $payload)
            ->assertCreated();

        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(7500, DB::table('wallets')->value('available_balance_minor'));

        $storedSecret = DB::table('reseller_api_clients')->value('secret_hash');

        $this->assertSame(hash('sha256', $secret), $storedSecret);
        $this->assertNotSame($secret, $storedSecret);
    }

    public function test_revoked_api_key_is_rejected(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Revoked Tenant',
            'slug' => 'revoked-tenant',
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

        $publicId = (string) Str::ulid();
        $secret = Str::random(64);

        DB::table('reseller_api_clients')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'public_id' => $publicId,
            'name' => 'Revoked Client',
            'secret_hash' => hash('sha256', $secret),
            'abilities' => json_encode(['services:read']),
            'status' => 'revoked',
            'rate_limit_per_minute' => 120,
            'revoked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->withToken('agcp_'.$publicId.'.'.$secret)
            ->getJson('/api/reseller/v1/services')
            ->assertUnauthorized();
    }
}