<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AdminCommandCenterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_list_contains_only_active_memberships_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $allowed = Tenant::query()->create([
            'name' => 'Allowed',
            'slug' => 'allowed',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $hidden = Tenant::query()->create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        DB::table('tenant_memberships')->insert([
            [
                'tenant_id' => $allowed->id,
                'user_id' => $user->id,
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $hidden->id,
                'user_id' => $other->id,
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this
            ->actingAs($user)
            ->getJson('/api/v1/tenants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $allowed->id);
    }

    public function test_admin_products_endpoint_is_tenant_scoped_and_permission_protected(): void
    {
        $this->seed();

        $tenant = Tenant::query()->create([
            'name' => 'Commerce',
            'slug' => 'commerce',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $otherTenant = Tenant::query()->create([
            'name' => 'Other',
            'slug' => 'other',
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

        DB::table('products')->insert([
            [
                'tenant_id' => $tenant->id,
                'sku' => 'VISIBLE-001',
                'name' => 'Visible Product',
                'slug' => 'visible-product',
                'type' => 'service',
                'status' => 'active',
                'currency' => 'USD',
                'price_minor' => 1000,
                'cost_minor' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $otherTenant->id,
                'sku' => 'HIDDEN-001',
                'name' => 'Hidden Product',
                'slug' => 'hidden-product',
                'type' => 'service',
                'status' => 'active',
                'currency' => 'USD',
                'price_minor' => 1000,
                'cost_minor' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->getJson('/api/v1/admin/products');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'VISIBLE-001');
    }
}