<?php

namespace Tests\Feature;

use App\Modules\Licensing\Application\EntitlementService;
use App\Modules\Licensing\Application\LicenseService;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SaaSLicensingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_entitlement_resolves_and_license_secret_is_hashed(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Licensed Tenant',
            'slug' => 'licensed-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $definitionId = DB::table('entitlement_definitions')->insertGetId([
            'key' => 'marketplace.enabled',
            'name' => 'Marketplace',
            'module' => 'marketplace',
            'value_type' => 'boolean',
            'default_value' => json_encode(false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $planId = DB::table('saas_plans')->insertGetId([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'billing_period' => 'monthly',
            'price_minor' => 9900,
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('plan_entitlements')->insert([
            'saas_plan_id' => $planId,
            'entitlement_definition_id' => $definitionId,
            'value' => json_encode(true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tenant_subscriptions')->insert([
            'tenant_id' => $tenant->id,
            'saas_plan_id' => $planId,
            'subscription_uuid' => 'sub-test-001',
            'mode' => 'self_hosted',
            'status' => 'active',
            'starts_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(
            app(EntitlementService::class)
                ->allows($tenant->id, 'marketplace.enabled')
        );

        $issued = app(LicenseService::class)->issue(
            tenantId: $tenant->id,
            edition: 'enterprise-self-hosted',
            domain: 'example.test',
        );

        $this->assertStringStartsWith(
            'agcp_license_',
            $issued['token']
        );

        $stored = DB::table('license_keys')
            ->where('id', $issued['record']->id)
            ->first();

        $this->assertNotEmpty($stored->secret_hash);
        $this->assertStringNotContainsString(
            substr($issued['token'], -20),
            $stored->secret_hash
        );

        $validated = app(LicenseService::class)->validate(
            $issued['token'],
            'example.test'
        );

        $this->assertSame($stored->id, $validated->id);
    }
}