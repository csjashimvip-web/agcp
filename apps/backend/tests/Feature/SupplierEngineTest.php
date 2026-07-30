<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Commerce\Application\Services\CartService;
use Modules\Commerce\Application\Services\CheckoutService;
use Modules\Commerce\Application\Services\OrderService;
use Modules\Commerce\Infrastructure\Models\CatalogItem;
use Modules\Commerce\Infrastructure\Models\CatalogPrice;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Commerce\Infrastructure\Models\PriceList;
use Modules\Suppliers\Application\Services\SupplierFulfillmentService;
use Modules\Suppliers\Infrastructure\Models\SupplierAccount;
use Modules\Suppliers\Infrastructure\Models\SupplierOrder;
use Modules\Suppliers\Infrastructure\Models\SupplierRoutingProfile;
use Modules\Suppliers\Infrastructure\Models\SupplierService;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Wallet\Application\Services\DepositService;
use Modules\Wallet\Application\Services\WalletService;
use Modules\Wallet\Domain\Enums\DepositStatus;
use Modules\Wallet\Infrastructure\Models\DepositRequest;

uses(RefreshDatabase::class);

function driveSupplierOrder(SupplierFulfillmentService $fulfillment): SupplierOrder
{
    $supplierOrder = SupplierOrder::query()->firstOrFail();
    for ($step = 0; $step < 6 && ! $supplierOrder->status->terminal(); $step++) {
        if (in_array($supplierOrder->status->value, ['queued', 'retrying', 'failed'], true)) {
            $supplierOrder = $fulfillment->submit($supplierOrder->id);
        } elseif (in_array($supplierOrder->status->value, ['submitted', 'processing'], true)) {
            $supplierOrder = $fulfillment->poll($supplierOrder->id);
        } else {
            break;
        }
        $supplierOrder = $supplierOrder->fresh();
    }

    return $supplierOrder->fresh(['attemptLogs', 'decisions']);
}

function supplierEngineFixture(array $supplierDefinitions = []): array
{
    $tenant = Tenant::query()->create([
        'name' => 'Supplier Tenant', 'slug' => 'supplier-tenant', 'status' => 'active',
        'default_currency' => 'USD', 'timezone' => 'UTC',
    ]);
    $customer = User::query()->create([
        'name' => 'Supplier Customer', 'email' => 'supplier-customer@example.test',
        'password' => 'Secret123!', 'status' => 'active',
    ]);
    $reviewer = User::query()->create([
        'name' => 'Supplier Reviewer', 'email' => 'supplier-reviewer@example.test',
        'password' => 'Secret123!', 'status' => 'active',
    ]);
    $wallet = app(WalletService::class)->ensureUserWallet($customer, $tenant->id, 'USD');
    $deposit = DepositRequest::query()->create([
        'tenant_id' => $tenant->id, 'user_id' => $customer->id, 'wallet_id' => $wallet->id,
        'amount_minor' => 10000, 'currency' => 'USD', 'method' => 'manual',
        'status' => DepositStatus::Pending, 'submitted_at' => now(),
    ]);
    app(DepositService::class)->approve($deposit, $reviewer, null, 'supplier-fixture-funding');

    $item = CatalogItem::query()->create([
        'tenant_id' => $tenant->id, 'type' => 'service', 'name' => 'Automated IMEI',
        'slug' => 'automated-imei', 'sku' => 'AUTO-IMEI', 'status' => 'active',
        'fulfillment_mode' => 'supplier_api', 'inventory_tracking' => false,
        'allow_backorder' => true, 'service_schema' => ['fields' => [['name' => 'imei', 'required' => true]]],
        'published_at' => now(),
    ]);
    $variant = CatalogVariant::query()->create([
        'catalog_item_id' => $item->id, 'name' => 'Default', 'sku' => 'AUTO-IMEI-STD',
        'status' => 'active', 'is_default' => true,
    ]);
    $priceList = PriceList::query()->create([
        'tenant_id' => $tenant->id, 'name' => 'Retail', 'slug' => 'retail',
        'currency' => 'USD', 'priority' => 100, 'status' => 'active',
    ]);
    CatalogPrice::query()->create([
        'price_list_id' => $priceList->id, 'catalog_variant_id' => $variant->id,
        'amount_minor' => 500, 'min_quantity' => 1,
    ]);

    SupplierRoutingProfile::query()->create([
        'tenant_id' => $tenant->id, 'name' => 'Priority routing', 'slug' => 'default',
        'strategy' => 'priority', 'is_default' => true, 'status' => 'active',
    ]);

    if ($supplierDefinitions === []) {
        $supplierDefinitions = [[
            'code' => 'primary', 'name' => 'Primary Supplier', 'priority' => 10,
            'cost_minor' => 250, 'metadata' => ['sandbox_latency_ms' => 50],
        ]];
    }

    $suppliers = [];
    foreach ($supplierDefinitions as $index => $definition) {
        $supplier = SupplierAccount::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $definition['name'],
            'code' => $definition['code'],
            'provider' => 'sandbox',
            'status' => 'active',
            'priority' => $definition['priority'],
            'timeout_seconds' => 20,
            'max_retries' => 3,
            'health_status' => 'healthy',
            'health_score' => 100,
            'success_rate' => 99,
            'average_latency_ms' => (int) ($definition['metadata']['sandbox_latency_ms'] ?? 100),
            'metadata' => $definition['metadata'] ?? [],
        ]);
        SupplierService::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_account_id' => $supplier->id,
            'catalog_variant_id' => $variant->id,
            'supplier_service_code' => 'SERVICE-'.$index,
            'cost_minor' => $definition['cost_minor'],
            'currency' => 'USD',
            'estimated_seconds' => 10,
            'priority' => $definition['priority'],
            'enabled' => true,
        ]);
        $suppliers[] = $supplier;
    }

    $cart = app(CartService::class)->add($customer, $tenant->id, $variant->id, 1, ['imei' => '123456789012345'], 'USD');
    $order = app(CheckoutService::class)->checkout($customer, $tenant->id, $cart->id, $wallet->id, 'supplier-checkout-key');

    return compact('tenant', 'customer', 'reviewer', 'wallet', 'item', 'variant', 'suppliers', 'order');
}

it('routes and completes an automated supplier service', function () {
    $fixture = supplierEngineFixture();
    $fulfillment = app(SupplierFulfillmentService::class);
    $fulfillment->createForOrder($fixture['order']);
    $supplierOrder = driveSupplierOrder($fulfillment);
    expect($supplierOrder->status->value)->toBe('completed')
        ->and($supplierOrder->supplier_account_id)->toBe($fixture['suppliers'][0]->id)
        ->and($supplierOrder->attemptLogs)->toHaveCount(1)
        ->and($supplierOrder->decisions)->toHaveCount(1)
        ->and($fixture['order']->items()->first()->fresh()->status)->toBe('completed')
        ->and($fixture['wallet']->account()->first()->balance_minor)->toBe(9500);
});

it('fails over to the next supplier after a submission failure', function () {
    $fixture = supplierEngineFixture([
        ['code' => 'failing', 'name' => 'Failing Supplier', 'priority' => 0, 'cost_minor' => 200, 'metadata' => ['sandbox_fail_submissions' => true, 'sandbox_latency_ms' => 20]],
        ['code' => 'backup', 'name' => 'Backup Supplier', 'priority' => 80, 'cost_minor' => 300, 'metadata' => ['sandbox_latency_ms' => 80]],
    ]);
    $fulfillment = app(SupplierFulfillmentService::class);
    $fulfillment->createForOrder($fixture['order']);
    $supplierOrder = driveSupplierOrder($fulfillment);
    expect($supplierOrder->status->value)->toBe('completed')
        ->and($supplierOrder->supplier_account_id)->toBe($fixture['suppliers'][1]->id)
        ->and($supplierOrder->attemptLogs)->toHaveCount(2)
        ->and($supplierOrder->attemptLogs->first()->status)->toBe('failed')
        ->and($supplierOrder->attemptLogs->last()->status)->toBe('completed');
});

it('refunds the item automatically when every supplier fails', function () {
    $fixture = supplierEngineFixture([
        ['code' => 'only-failing', 'name' => 'Only Failing Supplier', 'priority' => 0, 'cost_minor' => 200, 'metadata' => ['sandbox_fail_submissions' => true]],
    ]);
    $fulfillment = app(SupplierFulfillmentService::class);
    $fulfillment->createForOrder($fixture['order']);
    $supplierOrder = driveSupplierOrder($fulfillment);
    expect($supplierOrder->status->value)->toBe('refunded')
        ->and($supplierOrder->refund_ledger_transaction_id)->not->toBeNull()
        ->and($fixture['wallet']->account()->first()->balance_minor)->toBe(10000)
        ->and($fixture['order']->fresh()->payment_status)->toBe('refunded')
        ->and($fixture['order']->items()->first()->fresh()->status)->toBe('refunded');
});

it('blocks commerce cancellation after supplier fulfillment has been created', function () {
    $fixture = supplierEngineFixture();
    app(SupplierFulfillmentService::class)->createForOrder($fixture['order']);

    expect(fn () => app(OrderService::class)->cancel($fixture['order'], $fixture['customer']))
        ->toThrow(ValidationException::class);
});
