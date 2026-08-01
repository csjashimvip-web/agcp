<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SupportTicketingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_and_reply_to_tenant_ticket(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Support Tenant',
            'slug' => 'support-tenant',
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

        $ticketId = (int) $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson('/api/v1/customer/support', [
                'subject' => 'Order question',
                'category' => 'general',
                'priority' => 'normal',
                'message' => 'Please check my request.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        $this
            ->actingAs($user)
            ->withHeader('X-AGCP-Tenant', (string) $tenant->id)
            ->postJson(
                "/api/v1/customer/support/{$ticketId}/messages",
                ['message' => 'Adding more information.']
            )
            ->assertCreated();

        $this->assertDatabaseCount('support_messages', 2);
    }
}