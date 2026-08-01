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

final class AdvancedPricingFraudTest extends TestCase
{
    use RefreshDatabase;

    public function test_advanced_surcharge_is_applied_in_checkout(): void
    {
        Queue::fake();
        [$tenant, $user, $wallet, $product] = $this->fixture();

        DB::table('pricing_rules')->insert([
            'tenant_id' => $tenant->id,
            'name' => 'Five Percent Surcharge',
            'code' => 'SURCHARGE5',
            'effect' => 'surcharge',
            'value_type' => 'percent',
            'rate_bps' => 500,
            'min_subtotal_minor' => 0,
            'priority' => 10,
            'stackable' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/checkout', [
                'wallet_id' => $wallet->id,
                'idempotency_key' => 'advanced-price-001',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.subtotal_minor', 1000)
            ->assertJsonPath('data.surcharge_minor', 50)
            ->assertJsonPath('data.total_minor', 1050);

        $this->assertSame(
            8950,
            $wallet->fresh()->available_balance_minor
        );
    }

    public function test_fraud_block_occurs_before_wallet_mutation(): void
    {
        Queue::fake();
        [$tenant, $user, $wallet, $product] = $this->fixture();

        DB::table('fraud_rules')->insert([
            'tenant_id' => $tenant->id,
            'name' => 'Block Large Checkout',
            'code' => 'BLOCK-LARGE',
            'metric' => 'order_total_minor',
            'threshold_value' => 900,
            'risk_points' => 100,
            'action' => 'block',
            'priority' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/checkout', [
                'wallet_id' => $wallet->id,
                'idempotency_key' => 'fraud-block-001',
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]],
            ])
            ->assertUnprocessable();

        $this->assertSame(
            10000,
            $wallet->fresh()->available_balance_minor
        );

        $this->assertDatabaseHas('fraud_assessments', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'decision' => 'block',
        ]);
    }

    private function fixture(): array
    {
        $unique = str_replace('.', '', uniqid('', true));

        $tenant = Tenant::query()->create([
            'name' => 'Risk Tenant '.$unique,
            'slug' => 'risk-tenant-'.$unique,
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

        $ledger = LedgerAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'risk-wallet-'.$user->id,
            'name' => 'Risk Wallet',
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
            'ledger_account_id' => $ledger->id,
            'currency' => 'USD',
            'status' => 'active',
            'available_balance_minor' => 10000,
            'held_balance_minor' => 0,
        ]);

        $product = Product::query()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'RISK-'.$unique,
            'name' => 'Risk Service',
            'slug' => 'risk-service-'.$unique,
            'type' => 'service',
            'status' => 'active',
            'currency' => 'USD',
            'price_minor' => 1000,
            'cost_minor' => 400,
        ]);

        return [$tenant, $user, $wallet, $product];
    }
}