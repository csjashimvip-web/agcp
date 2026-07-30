<?php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Application\Services\CartService;
use Modules\Commerce\Application\Services\CheckoutService;
use Modules\Commerce\Infrastructure\Models\CatalogItem;
use Modules\Commerce\Infrastructure\Models\CatalogPrice;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Commerce\Infrastructure\Models\PriceList;
use Modules\Fraud\Application\Services\FraudRiskEngine;
use Modules\Rules\Application\Services\DynamicPricingService;
use Modules\Rules\Infrastructure\Models\Rule;
use Modules\Rules\Infrastructure\Models\RuleExecution;
use Modules\Rules\Infrastructure\Models\RuleVersion;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Wallet\Application\Services\DepositService;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\DepositStatus;
use Modules\Wallet\Infrastructure\Models\DepositRequest;
uses(RefreshDatabase::class);
function phase6Fixture(int $funding=300000): array {
    $tenant=Tenant::query()->create(['name'=>'Phase 6 Tenant','slug'=>'phase6','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);
    $user=User::query()->create(['name'=>'Rule Customer','email'=>'rule@example.test','password'=>'Secret123!','status'=>'active','email_verified_at'=>now(),'created_at'=>now()->subDays(5)]);
    $reviewer=User::query()->create(['name'=>'Reviewer','email'=>'reviewer6@example.test','password'=>'Secret123!','status'=>'active','email_verified_at'=>now()]);
    $wallet=app(WalletService::class)->ensureUserWallet($user,$tenant->id,'USD');
    $deposit=DepositRequest::query()->create(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'wallet_id'=>$wallet->id,'amount_minor'=>$funding,'currency'=>'USD','method'=>'manual','status'=>DepositStatus::Pending,'submitted_at'=>now()]);
    app(DepositService::class)->approve($deposit,$reviewer,null,'phase6-funding');
    $item=CatalogItem::query()->create(['tenant_id'=>$tenant->id,'type'=>'digital','name'=>'Rule Product','slug'=>'rule-product','sku'=>'RULE-PRODUCT','status'=>'active','fulfillment_mode'=>'manual','inventory_tracking'=>false,'allow_backorder'=>true,'published_at'=>now()]);
    $variant=CatalogVariant::query()->create(['catalog_item_id'=>$item->id,'name'=>'Default','sku'=>'RULE-PRODUCT-STD','status'=>'active','is_default'=>true]);
    $list=PriceList::query()->create(['tenant_id'=>$tenant->id,'name'=>'Retail','slug'=>'retail','currency'=>'USD','priority'=>100,'status'=>'active']);
    CatalogPrice::query()->create(['price_list_id'=>$list->id,'catalog_variant_id'=>$variant->id,'amount_minor'=>1000,'min_quantity'=>1]);
    return compact('tenant','user','reviewer','wallet','item','variant');
}
function publishRule(string $tenantId,string $slug,string $scope,array $conditions,array $actions,int $priority=100,bool $stop=false): Rule {
    $rule=Rule::query()->create(['tenant_id'=>$tenantId,'name'=>$slug,'slug'=>$slug,'scope'=>$scope,'status'=>'active','priority'=>$priority,'stop_on_match'=>$stop,'published_version'=>1]);
    $payload=['condition_mode'=>'all','conditions'=>$conditions,'actions'=>$actions];
    RuleVersion::query()->create(['rule_id'=>$rule->id,'version'=>1]+$payload+['checksum'=>hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR)),'published_at'=>now()]);
    return $rule;
}
it('applies an auditable dynamic pricing discount',function(){
    $f=phase6Fixture(); $rule=publishRule($f['tenant']->id,'bulk-discount','pricing',[['field'=>'pricing.quantity','operator'=>'gte','value'=>3]],[['type'=>'discount_percent','value'=>10]]);
    $quote=app(DynamicPricingService::class)->quote($f['variant']->load('item'),$f['tenant']->id,'USD',3,$f['user'],[],true);
    expect($quote->baseAmountMinor)->toBe(1000)->and($quote->finalAmountMinor)->toBe(900)->and($quote->matchedRuleIds)->toContain($rule->id)->and(RuleExecution::query()->where('rule_id',$rule->id)->where('matched',true)->exists())->toBeTrue();
});
it('blocks a critical checkout and records the assessment',function(){
    $f=phase6Fixture(); publishRule($f['tenant']->id,'critical-block','fraud',[['field'=>'order.total_minor','operator'=>'gte','value'=>200000]],[['type'=>'decision','value'=>'block']],1,true);
    $result=app(FraudRiskEngine::class)->assessCheckout($f['user'],$f['tenant']->id,200000,'00000000-0000-0000-0000-000000000001',['currency'=>'USD']);
    expect($result->decision->value)->toBe('block')->and($result->score)->toBeGreaterThanOrEqual(45);
});
it('uses dynamic prices during wallet checkout',function(){
    $f=phase6Fixture(); publishRule($f['tenant']->id,'checkout-discount','pricing',[['field'=>'pricing.quantity','operator'=>'gte','value'=>2]],[['type'=>'discount_percent','value'=>10]]);
    $cart=app(CartService::class)->add($f['user'],$f['tenant']->id,$f['variant']->id,2,[],'USD');
    $order=app(CheckoutService::class)->checkout($f['user'],$f['tenant']->id,$cart->id,$f['wallet']->id,'phase6-checkout');
    expect($order->total_minor)->toBe(1800)->and($order->discount_minor)->toBe(200)->and($order->items()->first()->unit_price_minor)->toBe(900);
});
