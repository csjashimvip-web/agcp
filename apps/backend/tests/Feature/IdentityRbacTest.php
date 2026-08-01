<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class IdentityRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_overview_requires_permission_inside_the_active_tenant(): void
    {
        $this->seed();

        $tenant = Tenant::query()->create([
            'name' => 'Admin Tenant',
            'slug' => 'admin-tenant',
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

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->getJson('/api/v1/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->id);
    }

    public function test_admin_overview_rejects_a_member_without_permission(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Restricted Tenant',
            'slug' => 'restricted-tenant',
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

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->getJson('/api/v1/admin/overview')
            ->assertForbidden();
    }
}