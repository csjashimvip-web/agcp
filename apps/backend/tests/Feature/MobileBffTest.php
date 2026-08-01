<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MobileBffTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_bootstrap_is_tenant_and_user_scoped(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Mobile Tenant',
            'slug' => 'mobile-tenant',
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
            ->getJson('/api/mobile/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.tenant.id', $tenant->id);

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/mobile/v1/devices', [
                'device_uuid' => 'device-test-001',
                'platform' => 'android',
                'app_version' => '1.0.0',
                'push_token' => 'secret-push-token',
            ])
            ->assertCreated();

        $device = DB::table('mobile_devices')->first();

        $this->assertNotSame(
            'secret-push-token',
            $device->encrypted_push_token
        );

        $this->assertSame(
            hash('sha256', 'secret-push-token'),
            $device->push_token_hash
        );
    }
}