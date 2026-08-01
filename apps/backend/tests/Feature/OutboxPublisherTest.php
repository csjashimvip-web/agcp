<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Reliability\Application\OutboxPublisher;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OutboxPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_publication_is_idempotent_and_creates_in_app_notification(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Outbox Tenant',
            'slug' => 'outbox-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $user = User::factory()->create();

        $orderId = DB::table('orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'order_uuid' => (string) Str::uuid(),
            'order_number' => 'OUTBOX-001',
            'status' => 'confirmed',
            'currency' => 'USD',
            'subtotal_minor' => 1000,
            'discount_minor' => 0,
            'surcharge_minor' => 0,
            'total_minor' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventId = (string) Str::uuid();

        DB::table('outbox_events')->insert([
            'tenant_id' => $tenant->id,
            'event_id' => $eventId,
            'event_type' => 'commerce.order.confirmed.v1',
            'aggregate_type' => 'order',
            'aggregate_id' => (string) $orderId,
            'payload' => json_encode([
                'order_id' => $orderId,
            ], JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $publisher = app(OutboxPublisher::class);

        $first = $publisher->publish();
        $second = $publisher->publish();

        $this->assertSame(1, $first['published']);
        $this->assertSame(0, $second['published']);

        $this->assertDatabaseCount('event_publications', 1);

        $this->assertDatabaseHas('notification_deliveries', [
            'event_id' => $eventId,
            'user_id' => $user->id,
            'channel' => 'in_app',
        ]);

        $this->assertNotNull(
            DB::table('outbox_events')
                ->where('event_id', $eventId)
                ->value('published_at')
        );
    }
}