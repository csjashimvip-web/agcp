<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdminOperationsDhruTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_tenant_scoped_product(): void
    {
        [$tenant, $user] = $this->adminFixture();

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/admin/products', [
                'sku' => 'AGCP-TEST-001',
                'name' => 'AGCP Test Service',
                'type' => 'service',
                'status' => 'active',
                'currency' => 'USD',
                'price_minor' => 1500,
                'cost_minor' => 700,
            ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'AGCP-TEST-001');

        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'sku' => 'AGCP-TEST-001',
        ]);

        $this->assertDatabaseHas('admin_audit_events', [
            'tenant_id' => $tenant->id,
            'action' => 'catalog.product.created',
        ]);
    }

    public function test_dhru_supplier_secrets_are_encrypted_and_not_returned(): void
    {
        [$tenant, $user] = $this->adminFixture();

        $response = $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/admin/suppliers', [
                'name' => 'Demo Dhru',
                'code' => 'demo-dhru',
                'driver' => 'dhru-fusion',
                'status' => 'active',
                'priority' => 10,
                'timeout_seconds' => 30,
                'max_retries' => 2,
                'base_url' => 'https://supplier.example',
                'username' => 'api-user',
                'api_key' => 'super-secret-key',
            ])
            ->assertCreated()
            ->assertJsonPath('data.credentials_configured', true);

        $response->assertJsonMissing(['api_key' => 'super-secret-key']);

        $secret = (string) DB::table('suppliers')
            ->where('code', 'demo-dhru')
            ->value('secret_payload');

        $this->assertNotEmpty($secret);
        $this->assertNotSame('super-secret-key', $secret);
        $this->assertStringNotContainsString('super-secret-key', $secret);
    }

    /**
     * @return array{Tenant,User}
     */
    private function adminFixture(): array
    {
        $this->seed();

        $tenant = Tenant::query()->create([
            'name' => 'Admin Ops Tenant',
            'slug' => 'admin-ops-'.uniqid(),
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $user = User::factory()->create();

        $membershipId = DB::table('tenant_memberships')->insertGetId([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->where('slug', 'platform-super-admin')->value('id');

        DB::table('membership_role')->insert([
            'tenant_membership_id' => $membershipId,
            'role_id' => $roleId,
        ]);

        return [$tenant, $user];
    }
}