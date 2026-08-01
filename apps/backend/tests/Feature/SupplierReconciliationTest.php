<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Supplier\Application\Contracts\SupplierProviderFactory;
use App\Modules\Supplier\Application\Jobs\ReconcileSupplierOrder;
use App\Modules\Supplier\Domain\Contracts\SupplierProvider;
use App\Modules\Supplier\Domain\Models\Supplier;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SupplierReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_supplier_status_completes_item_and_parent_order(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Reconciliation Tenant',
            'slug' => 'reconciliation-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $user = User::factory()->create();

        $productId = DB::table('products')->insertGetId([
            'tenant_id' => $tenant->id,
            'sku' => 'REC-001',
            'name' => 'Reconciliation Service',
            'slug' => 'reconciliation-service',
            'type' => 'service',
            'status' => 'active',
            'currency' => 'USD',
            'price_minor' => 1000,
            'cost_minor' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'order_uuid' => 'rec-order-uuid',
            'order_number' => 'REC-ORDER-001',
            'status' => 'processing',
            'currency' => 'USD',
            'subtotal_minor' => 1000,
            'discount_minor' => 0,
            'surcharge_minor' => 0,
            'total_minor' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'product_id' => $productId,
            'sku' => 'REC-001',
            'name' => 'Reconciliation Service',
            'quantity' => 1,
            'unit_price_minor' => 1000,
            'unit_cost_minor' => 500,
            'line_total_minor' => 1000,
            'fulfillment_status' => 'submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supplier = Supplier::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Fake Supplier',
            'code' => 'fake-supplier',
            'driver' => 'dhru-fusion',
            'status' => 'active',
            'priority' => 10,
            'timeout_seconds' => 30,
            'max_retries' => 2,
        ]);

        $supplierOrderId = DB::table('supplier_orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'order_item_id' => $itemId,
            'supplier_id' => $supplier->id,
            'supplier_order_uuid' => 'supplier-order-uuid',
            'submission_key' => 'reconcile-test-key',
            'external_order_id' => 'EXT-001',
            'status' => 'submitted',
            'attempt' => 1,
            'cost_minor' => 500,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->app->bind(
            SupplierProviderFactory::class,
            fn () => new class implements SupplierProviderFactory {
                public function make(Supplier $supplier): SupplierProvider
                {
                    return new class implements SupplierProvider {
                        public function code(): string
                        {
                            return 'fake';
                        }

                        public function services(): array
                        {
                            return [];
                        }

                        public function balance(): ?array
                        {
                            return null;
                        }

                        public function submit(array $payload): array
                        {
                            return [
                                'external_order_id' => 'EXT-001',
                                'status' => 'submitted',
                            ];
                        }

                        public function status(string $externalOrderId): array
                        {
                            return [
                                'status' => 'completed',
                                'result' => 'OK',
                                'raw' => ['STATUS' => 'Completed'],
                            ];
                        }
                    };
                }
            }
        );

        (new ReconcileSupplierOrder($supplierOrderId))
            ->handle($this->app->make(SupplierProviderFactory::class));

        $this->assertDatabaseHas('supplier_orders', [
            'id' => $supplierOrderId,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $itemId,
            'fulfillment_status' => 'completed',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'supplier.order.status_changed.v1',
            'aggregate_id' => (string) $supplierOrderId,
        ]);
    }
}