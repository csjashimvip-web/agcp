<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PricingEngineCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_tier_coupon_and_tax_are_applied_inside_checkout_transaction(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Pricing Tenant',
            'slug' => 'pricing-tenant',
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
            'code' => 'pricing-wallet-'.$user->id,
            'name' => 'Pricing Wallet',
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
            'sku' => 'PRICE-001',
            'name' => 'Pricing Service',
            'slug' => 'pricing-service',
            'type' => 'service',
            'status' => 'active',
            'currency' => 'USD',
            'price_minor' => 1000,
            'cost_minor' => 400,
        ]);

        $tierId = DB::table('reseller_tiers')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Gold',
            'slug' => 'gold',
            'default_discount_bps' => 1000,
            'priority' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('reseller_tier_memberships')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'reseller_tier_id' => $tierId,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coupons')->insert([
            'tenant_id' => $tenant->id,
            'code' => 'SAVE100',
            'name' => 'Save 100',
            'type' => 'fixed',
            'amount_minor' => 100,
            'min_subtotal_minor' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tax_rules')->insert([
            'tenant_id' => $tenant->id,
            'name' => 'Ten Percent',
            'rate_bps' => 1000,
            'priority' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/checkout', [
                'wallet_id' => $wallet->id,
                'idempotency_key' => 'pricing-checkout-001',
                'coupon_code' => 'SAVE100',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertCreated();

        $response
            ->assertJsonPath('data.subtotal_minor', 900)
            ->assertJsonPath('data.discount_minor', 100)
            ->assertJsonPath('data.tax_minor', 80)
            ->assertJsonPath('data.total_minor', 880)
            ->assertJsonPath('data.items.0.unit_price_minor', 900);

        $this->assertSame(
            9120,
            $wallet->fresh()->available_balance_minor
        );

        $this->assertDatabaseHas('coupon_redemptions', [
            'user_id' => $user->id,
            'discount_minor' => 100,
        ]);
    }
}