<?php

namespace App\Modules\Gateway\Application;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WebhookSubscriptionService
{
    public function __construct(
        private readonly WebhookDestinationPolicy $destinations,
    ) {
    }

    /**
     * @param array<int,string> $events
     * @return array{record:object,secret:string}
     */
    public function create(
        int $tenantId,
        string $name,
        string $endpointUrl,
        array $events,
        bool $externalDeliveryEnabled = false,
    ): array {
        $this->destinations->assertAllowed($endpointUrl);

        $secret = Str::random(64);

        $id = DB::table('webhook_subscriptions')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $name,
            'endpoint_url' => $endpointUrl,
            'event_types' => json_encode(
                array_values(array_unique($events)),
                JSON_THROW_ON_ERROR
            ),
            'secret_hash' => hash('sha256', $secret),
            'encrypted_secret' => Crypt::encryptString($secret),
            'external_delivery_enabled' => $externalDeliveryEnabled,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'record' => DB::table('webhook_subscriptions')
                ->where('id', $id)
                ->first(),
            'secret' => $secret,
        ];
    }
}