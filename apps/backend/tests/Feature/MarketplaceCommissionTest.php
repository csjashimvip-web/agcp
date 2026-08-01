<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Marketplace\Application\CommissionAccrualService;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MarketplaceCommissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_marketplace_order_accrues_commission_once(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Marketplace Tenant',
            'slug' => 'marketplace-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $customer = User::factory()->create();
        $sellerUser = User::factory()->create();

        $productId = DB::table('products')->insertGetId([
            'tenant_id' => $tenant->id,
            'sku' => 'MARKET-001',
            'name' => 'Marketplace Service',
            'slug' => 'marketplace-service',
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
            'display_name' => 'Seller One',
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
            'order_uuid' => 'market-order-uuid',
            'order_number' => 'MARKET-ORDER-001',
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

        $itemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'product_id' => $productId,
            'sku' => 'MARKET-001',
            'name' => 'Marketplace Service',
            'quantity' => 1,
            'unit_price_minor' => 2000,
            'unit_cost_minor' => 1000,
            'line_total_minor' => 2000,
            'fulfillment_status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(CommissionAccrualService::class);

        $this->assertSame(1, $service->accrueForOrder($orderId));
        $this->assertSame(0, $service->accrueForOrder($orderId));

        $this->assertDatabaseHas('commission_accruals', [
            'order_id' => $orderId,
            'order_item_id' => $itemId,
            'marketplace_seller_id' => $sellerId,
            'beneficiary_user_id' => $sellerUser->id,
            'amount_minor' => 200,
            'currency' => 'USD',
            'rate_bps' => 1000,
            'status' => 'accrued',
        ]);
    }
}