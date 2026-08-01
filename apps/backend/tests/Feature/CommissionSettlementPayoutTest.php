<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Marketplace\Application\CommissionAccrualService;
use App\Modules\Marketplace\Application\CommissionSettlementService;
use App\Modules\Tenancy\Domain\Models\Tenant;
use App\Modules\Wallet\Application\PayoutRequestService;
use App\Modules\Wallet\Domain\Models\LedgerAccount;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CommissionSettlementPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_settlement_and_payout_preserve_wallet_invariants(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Settlement Tenant',
            'slug' => 'settlement-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $customer = User::factory()->create();
        $sellerUser = User::factory()->create();

        $sellerLedger = LedgerAccount::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'seller-wallet-'.$sellerUser->id,
            'name' => 'Seller Wallet',
            'type' => 'asset',
            'currency' => 'USD',
            'status' => 'active',
            'balance_minor' => 0,
        ]);

        $sellerWallet = Wallet::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $sellerUser->id,
            'ledger_account_id' => $sellerLedger->id,
            'currency' => 'USD',
            'status' => 'active',
            'available_balance_minor' => 0,
            'held_balance_minor' => 0,
        ]);

        $productId = DB::table('products')->insertGetId([
            'tenant_id' => $tenant->id,
            'sku' => 'SETTLE-001',
            'name' => 'Settlement Service',
            'slug' => 'settlement-service',
            'type' => 'service',
            'status' => 'active',
            'currency' => 'USD',
            'price_minor' => 2000,
            'cost_minor' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sellerId = DB::table('marketplace_sellers')->insertGetId([
            'tenant_id' => $tenant->id,
            'user_id' => $sellerUser->id,
            'display_name' => 'Seller Settlement',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('marketplace_listings')->insert([
            'tenant_id' => $tenant->id,
            'marketplace_seller_id' => $sellerId,
            'product_id' => $productId,
            'seller_commission_bps' => 1000,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'order_uuid' => 'settlement-order-uuid',
            'order_number' => 'SETTLEMENT-ORDER-001',
            'status' => 'completed',
            'currency' => 'USD',
            'subtotal_minor' => 2000,
            'discount_minor' => 0,
            'surcharge_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => 2000,
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'sku' => 'SETTLE-001',
            'name' => 'Settlement Service',
            'quantity' => 1,
            'unit_price_minor' => 2000,
            'unit_cost_minor' => 1000,
            'line_total_minor' => 2000,
            'fulfillment_status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            1,
            app(CommissionAccrualService::class)->accrueForOrder($orderId)
        );

        $settlement = app(CommissionSettlementService::class)
            ->settleSeller($tenant->id, $sellerId, 'USD');

        $this->assertSame('settled', $settlement->status);
        $this->assertSame(
            200,
            $sellerWallet->fresh()->available_balance_minor
        );

        $payouts = app(PayoutRequestService::class);

        $payout = $payouts->request(
            tenantId: $tenant->id,
            userId: $sellerUser->id,
            walletId: $sellerWallet->id,
            amountMinor: 100,
            method: 'manual_bank',
            destinationLabel: 'Verified destination',
            destination: ['reference' => 'TEST-DESTINATION'],
        );

        $this->assertSame(
            100,
            $sellerWallet->fresh()->available_balance_minor
        );
        $this->assertSame(
            100,
            $sellerWallet->fresh()->held_balance_minor
        );

        $payouts->approve($tenant->id, $payout->id, 'Verified');
        $paid = $payouts->markPaid(
            $tenant->id,
            $payout->id,
            'External transfer confirmed'
        );

        $this->assertSame('paid', $paid->status);
        $this->assertSame(
            100,
            $sellerWallet->fresh()->available_balance_minor
        );
        $this->assertSame(
            0,
            $sellerWallet->fresh()->held_balance_minor
        );

        $this->assertDatabaseHas('wallet_holds', [
            'id' => DB::table('payout_requests')
                ->where('id', $payout->id)
                ->value('wallet_hold_id'),
            'status' => 'captured',
        ]);
    }
}