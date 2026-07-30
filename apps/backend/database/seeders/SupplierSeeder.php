<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Suppliers\Infrastructure\Models\SupplierAccount;
use Modules\Suppliers\Infrastructure\Models\SupplierRoutingProfile;
use Modules\Suppliers\Infrastructure\Models\SupplierService;
use Modules\Tenancy\Infrastructure\Models\Tenant;

final class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'araabi-global')->firstOrFail();
        $variant = CatalogVariant::query()->where('sku', 'SRV-IMEI-CHECK-STD')->first();
        if (! $variant) {
            $this->command?->warn('Supplier demo mappings were skipped because the IMEI service variant does not exist.');
            return;
        }

        DB::transaction(function () use ($tenant, $variant): void {
            SupplierRoutingProfile::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'default'],
                [
                    'name' => 'Balanced default routing',
                    'strategy' => 'balanced',
                    'is_default' => true,
                    'status' => 'active',
                    'weights' => ['cost' => 0.30, 'success' => 0.30, 'latency' => 0.15, 'health' => 0.15, 'priority' => 0.10],
                ],
            );

            $fast = SupplierAccount::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'sandbox-fast'],
                [
                    'name' => 'Sandbox Fast Supplier', 'provider' => 'sandbox', 'status' => 'active', 'priority' => 20,
                    'timeout_seconds' => 20, 'max_retries' => 3, 'health_status' => 'healthy', 'health_score' => 100,
                    'success_rate' => 99, 'average_latency_ms' => 120, 'metadata' => ['sandbox_latency_ms' => 120],
                ],
            );
            $economy = SupplierAccount::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'sandbox-economy'],
                [
                    'name' => 'Sandbox Economy Supplier', 'provider' => 'sandbox', 'status' => 'active', 'priority' => 40,
                    'timeout_seconds' => 30, 'max_retries' => 3, 'health_status' => 'healthy', 'health_score' => 96,
                    'success_rate' => 96, 'average_latency_ms' => 400, 'metadata' => ['sandbox_latency_ms' => 400],
                ],
            );

            SupplierService::query()->updateOrCreate(
                ['supplier_account_id' => $fast->id, 'catalog_variant_id' => $variant->id],
                [
                    'tenant_id' => $tenant->id, 'supplier_service_code' => 'IMEI_FAST', 'cost_minor' => 320,
                    'currency' => 'USD', 'estimated_seconds' => 5, 'priority' => 20, 'enabled' => true,
                ],
            );
            SupplierService::query()->updateOrCreate(
                ['supplier_account_id' => $economy->id, 'catalog_variant_id' => $variant->id],
                [
                    'tenant_id' => $tenant->id, 'supplier_service_code' => 'IMEI_ECONOMY', 'cost_minor' => 250,
                    'currency' => 'USD', 'estimated_seconds' => 15, 'priority' => 40, 'enabled' => true,
                ],
            );
        }, 5);
    }
}
