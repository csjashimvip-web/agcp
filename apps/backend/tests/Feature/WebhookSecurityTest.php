<?php

namespace Tests\Feature;

use App\Modules\Gateway\Application\WebhookSubscriptionService;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_secret_is_encrypted_and_private_destination_is_rejected(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Webhook Tenant',
            'slug' => 'webhook-tenant',
            'status' => 'active',
            'default_currency' => 'USD',
        ]);

        $service = app(WebhookSubscriptionService::class);

        $created = $service->create(
            tenantId: $tenant->id,
            name: 'Example Hook',
            endpointUrl: 'https://example.com/agcp-webhook',
            events: ['commerce.order.confirmed.v1'],
            externalDeliveryEnabled: false,
        );

        $stored = DB::table('webhook_subscriptions')
            ->where('id', $created['record']->id)
            ->first();

        $this->assertSame(
            hash('sha256', $created['secret']),
            $stored->secret_hash
        );

        $this->assertSame(
            $created['secret'],
            Crypt::decryptString($stored->encrypted_secret)
        );

        $this->expectException(RuntimeException::class);

        $service->create(
            tenantId: $tenant->id,
            name: 'Bad Hook',
            endpointUrl: 'https://127.0.0.1/webhook',
            events: ['commerce.order.confirmed.v1'],
        );
    }
}