<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Commerce\Application\Services\CartService;
use Modules\Commerce\Application\Services\CheckoutService;
use Modules\Commerce\Application\Services\OrderService;
use Modules\Commerce\Infrastructure\Models\CatalogCategory;
use Modules\Commerce\Infrastructure\Models\CatalogItem;
use Modules\Commerce\Infrastructure\Models\CatalogPrice;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Commerce\Infrastructure\Models\InventoryLevel;
use Modules\Commerce\Infrastructure\Models\InventoryLocation;
use Modules\Commerce\Infrastructure\Models\PriceList;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Wallet\Application\Services\DepositService;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\DepositStatus;
use Modules\Wallet\Infrastructure\Models\DepositRequest;

uses(RefreshDatabase::class);

function commerceFixture(): array
{
    $tenant = Tenant::query()->create(['name'=>'Commerce Tenant','slug'=>'commerce-tenant','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);
    $customer = User::query()->create(['name'=>'Commerce Customer','email'=>'commerce-customer@example.test','password'=>'Secret123!','status'=>'active']);
    $reviewer = User::query()->create(['name'=>'Commerce Reviewer','email'=>'commerce-reviewer@example.test','password'=>'Secret123!','status'=>'active']);
    $wallet = app(WalletService::class)->ensureUserWallet($customer, $tenant->id, 'USD');
    $deposit = DepositRequest::query()->create([
        'tenant_id'=>$tenant->id,'user_id'=>$customer->id,'wallet_id'=>$wallet->id,'amount_minor'=>10000,'currency'=>'USD',
        'method'=>'manual','status'=>DepositStatus::Pending,'submitted_at'=>now(),
    ]);
    app(DepositService::class)->approve($deposit,$reviewer,null,'commerce-fund-key');

    $category=CatalogCategory::query()->create(['tenant_id'=>$tenant->id,'name'=>'Products','slug'=>'products','status'=>'active']);
    $item=CatalogItem::query()->create([
        'tenant_id'=>$tenant->id,'category_id'=>$category->id,'type'=>'physical','name'=>'Cable','slug'=>'cable','sku'=>'CABLE',
        'status'=>'active','fulfillment_mode'=>'manual','inventory_tracking'=>true,'allow_backorder'=>false,'published_at'=>now(),
    ]);
    $variant=CatalogVariant::query()->create(['catalog_item_id'=>$item->id,'name'=>'Default','sku'=>'CABLE-DEFAULT','status'=>'active','is_default'=>true]);
    $list=PriceList::query()->create(['tenant_id'=>$tenant->id,'name'=>'Retail','slug'=>'retail','currency'=>'USD','priority'=>100,'status'=>'active']);
    CatalogPrice::query()->create(['price_list_id'=>$list->id,'catalog_variant_id'=>$variant->id,'amount_minor'=>1000,'min_quantity'=>1]);
    $location=InventoryLocation::query()->create(['tenant_id'=>$tenant->id,'name'=>'Main','code'=>'MAIN','status'=>'active']);
    $level=InventoryLevel::query()->create(['inventory_location_id'=>$location->id,'catalog_variant_id'=>$variant->id,'on_hand'=>10,'reserved'=>0,'safety_stock'=>0]);
    return compact('tenant','customer','reviewer','wallet','item','variant','level');
}

it('checks out a priced cart with a balanced wallet debit and stock reservation', function () {
    $f=commerceFixture();
    $cart=app(CartService::class)->add($f['customer'],$f['tenant']->id,$f['variant']->id,2,[],'USD');
    $order=app(CheckoutService::class)->checkout($f['customer'],$f['tenant']->id,$cart->id,$f['wallet']->id,'checkout-key-1');

    expect($order->total_minor)->toBe(2000)
        ->and($order->payment_status)->toBe('paid')
        ->and($f['wallet']->account()->first()->balance_minor)->toBe(8000)
        ->and($f['level']->fresh()->reserved)->toBe(2)
        ->and($order->ledgerTransaction()->first())->not->toBeNull();
});

it('replays checkout idempotently without a second wallet debit', function () {
    $f=commerceFixture();
    $cart=app(CartService::class)->add($f['customer'],$f['tenant']->id,$f['variant']->id,1,[],'USD');
    $first=app(CheckoutService::class)->checkout($f['customer'],$f['tenant']->id,$cart->id,$f['wallet']->id,'checkout-replay-key');
    $second=app(CheckoutService::class)->checkout($f['customer'],$f['tenant']->id,$cart->id,$f['wallet']->id,'checkout-replay-key');

    expect($second->id)->toBe($first->id)
        ->and($f['wallet']->account()->first()->balance_minor)->toBe(9000)
        ->and($f['level']->fresh()->reserved)->toBe(1);
});

it('cancels a confirmed order, releases inventory and refunds the wallet', function () {
    $f=commerceFixture();
    $cart=app(CartService::class)->add($f['customer'],$f['tenant']->id,$f['variant']->id,3,[],'USD');
    $order=app(CheckoutService::class)->checkout($f['customer'],$f['tenant']->id,$cart->id,$f['wallet']->id,'checkout-cancel-key');
    $canceled=app(OrderService::class)->cancel($order,$f['customer']);

    expect($canceled->status->value)->toBe('canceled')
        ->and($canceled->payment_status)->toBe('refunded')
        ->and($f['wallet']->account()->first()->balance_minor)->toBe(10000)
        ->and($f['level']->fresh()->reserved)->toBe(0);
});

it('requires configured fields for service products', function () {
    $tenant=Tenant::query()->create(['name'=>'Service Tenant','slug'=>'service-tenant','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);
    $user=User::query()->create(['name'=>'Service User','email'=>'service-user@example.test','password'=>'Secret123!','status'=>'active']);
    $item=CatalogItem::query()->create([
        'tenant_id'=>$tenant->id,'type'=>'service','name'=>'IMEI Check','slug'=>'imei-check','sku'=>'IMEI','status'=>'active','fulfillment_mode'=>'manual',
        'inventory_tracking'=>false,'allow_backorder'=>true,'service_schema'=>['fields'=>[['name'=>'imei','required'=>true]]],'published_at'=>now(),
    ]);
    $variant=CatalogVariant::query()->create(['catalog_item_id'=>$item->id,'name'=>'Default','sku'=>'IMEI-STD','status'=>'active','is_default'=>true]);
    $list=PriceList::query()->create(['tenant_id'=>$tenant->id,'name'=>'Retail','slug'=>'retail','currency'=>'USD','priority'=>100,'status'=>'active']);
    CatalogPrice::query()->create(['price_list_id'=>$list->id,'catalog_variant_id'=>$variant->id,'amount_minor'=>500,'min_quantity'=>1]);

    app(CartService::class)->add($user,$tenant->id,$variant->id,1,[],'USD');
})->throws(Illuminate\Validation\ValidationException::class);
