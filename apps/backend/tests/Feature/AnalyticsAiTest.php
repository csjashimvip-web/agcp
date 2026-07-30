<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Analytics\Application\Services\AnalyticsPipelineService;
use Modules\Analytics\Domain\Enums\CustomerSegmentCode;
use Modules\Analytics\Domain\Enums\ModelRunStatus;
use Modules\Analytics\Infrastructure\Models\AiInsight;
use Modules\Analytics\Infrastructure\Models\CustomerSegment;
use Modules\Analytics\Infrastructure\Models\SupplierRecommendation;
use Modules\Commerce\Infrastructure\Models\CatalogItem;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Identity\Infrastructure\Models\TenantMembership;
use Modules\Suppliers\Infrastructure\Models\SupplierAccount;
use Modules\Suppliers\Infrastructure\Models\SupplierService;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Wallet\Application\Services\WalletService;

uses(RefreshDatabase::class);

function analyticsFixture(): array
{
    $tenant = Tenant::query()->create(['name'=>'Analytics Tenant','slug'=>'analytics-tenant','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);
    $customer = User::query()->create(['name'=>'Analytics Customer','email'=>'analytics@example.test','password'=>'Secret123!','status'=>'active','email_verified_at'=>now()]);
    TenantMembership::query()->create(['tenant_id'=>$tenant->id,'user_id'=>$customer->id,'status'=>'active','joined_at'=>now()->subMonths(2)]);
    $wallet = app(WalletService::class)->ensureUserWallet($customer,$tenant->id,'USD');

    foreach ([7,6,5,4,3] as $index => $daysAgo) {
        Order::query()->create([
            'tenant_id'=>$tenant->id,'user_id'=>$customer->id,'wallet_id'=>$wallet->id,'number'=>'AN-'.($index+1),
            'status'=>'completed','payment_status'=>'paid','fulfillment_status'=>'fulfilled','currency'=>'USD',
            'subtotal_minor'=>1000+($index*250),'discount_minor'=>0,'surcharge_minor'=>0,'total_minor'=>1000+($index*250),
            'placed_at'=>now()->subDays($daysAgo),'created_at'=>now()->subDays($daysAgo),'updated_at'=>now()->subDays($daysAgo),
        ]);
    }

    $item = CatalogItem::query()->create(['tenant_id'=>$tenant->id,'type'=>'service','name'=>'Analytics Service','slug'=>'analytics-service','sku'=>'AN-SVC','status'=>'active','fulfillment_mode'=>'supplier','inventory_tracking'=>false,'allow_backorder'=>true,'published_at'=>now()]);
    $variant = CatalogVariant::query()->create(['catalog_item_id'=>$item->id,'name'=>'Default','sku'=>'AN-SVC-STD','status'=>'active','is_default'=>true]);
    $preferred = SupplierAccount::query()->create(['tenant_id'=>$tenant->id,'name'=>'Preferred Supplier','code'=>'preferred','provider'=>'sandbox','status'=>'active','priority'=>10,'health_score'=>99,'success_rate'=>99,'average_latency_ms'=>50]);
    $backup = SupplierAccount::query()->create(['tenant_id'=>$tenant->id,'name'=>'Backup Supplier','code'=>'backup','provider'=>'sandbox','status'=>'active','priority'=>50,'health_score'=>90,'success_rate'=>92,'average_latency_ms'=>250]);
    SupplierService::query()->create(['tenant_id'=>$tenant->id,'supplier_account_id'=>$preferred->id,'catalog_variant_id'=>$variant->id,'supplier_service_code'=>'AN-1','cost_minor'=>200,'currency'=>'USD','estimated_seconds'=>10,'priority'=>10,'enabled'=>true]);
    SupplierService::query()->create(['tenant_id'=>$tenant->id,'supplier_account_id'=>$backup->id,'catalog_variant_id'=>$variant->id,'supplier_service_code'=>'AN-2','cost_minor'=>250,'currency'=>'USD','estimated_seconds'=>20,'priority'=>50,'enabled'=>true]);

    return compact('tenant','customer','preferred','backup','variant');
}

it('builds an auditable analytics snapshot forecast and customer segment', function () {
    $fixture = analyticsFixture();
    $result = app(AnalyticsPipelineService::class)->run($fixture['tenant']->id,'USD',30,7);

    expect($result['run']->status)->toBe(ModelRunStatus::Completed)
        ->and($result['snapshot']->orders_count)->toBe(5)
        ->and($result['snapshot']->gross_revenue_minor)->toBe(7500)
        ->and($result['forecast']->points)->toHaveCount(7)
        ->and(CustomerSegment::query()->where('user_id',$fixture['customer']->id)->firstOrFail()->segment_code)->toBe(CustomerSegmentCode::Loyal);
});

it('creates an explainable supplier recommendation and AI-assisted insight', function () {
    $fixture = analyticsFixture();
    app(AnalyticsPipelineService::class)->run($fixture['tenant']->id,'USD',30,7);

    $recommendation = SupplierRecommendation::query()->where('catalog_variant_id',$fixture['variant']->id)->firstOrFail();
    expect($recommendation->recommended_supplier_account_id)->toBe($fixture['preferred']->id)
        ->and($recommendation->candidates)->toHaveCount(2)
        ->and($recommendation->confidence)->toBeGreaterThan(50)
        ->and(AiInsight::query()->where('tenant_id',$fixture['tenant']->id)->where('type','supplier')->where('status','active')->exists())->toBeTrue();
});
