<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Application\CatalogImportService;
use App\Modules\Platform\Application\PrivacyRequestService;
use App\Modules\Platform\Application\TenantDataExportService;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PrivacyDataOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_request_export_and_catalog_dry_run_are_safe(): void
    {
        Storage::fake('local');

        $tenant = Tenant::query()->create([
            'name' => 'Data Tenant',
            'slug' => 'data-tenant',
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

        $privacy = app(PrivacyRequestService::class)->create(
            $tenant->id,
            $user->id,
            'access_export',
            'Please provide my data.'
        );

        $this->assertSame('submitted', $privacy->status);

        $export = app(TenantDataExportService::class)->create(
            $tenant->id,
            $user->id,
        );

        $this->assertSame('completed', $export->status);
        Storage::disk('local')->assertExists($export->file_path);

        $import = app(CatalogImportService::class)->run(
            tenantId: $tenant->id,
            requestedByUserId: $user->id,
            rows: [[
                'sku' => 'IMPORT-001',
                'name' => 'Imported Service',
                'price_minor' => 1500,
                'currency' => 'USD',
            ]],
            dryRun: true,
            sourceName: 'test',
        );

        $this->assertSame('validated', $import->status);

        $this->assertDatabaseMissing('products', [
            'tenant_id' => $tenant->id,
            'sku' => 'IMPORT-001',
        ]);
    }
}