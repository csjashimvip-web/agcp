<?php

namespace App\Modules\Gateway\Application;

use App\Modules\Gateway\Application\Jobs\DeliverWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EnqueuePublishedWebhooks
{
    public function run(int $limit = 200): int
    {
        $events = DB::table('outbox_events')
            ->whereNotNull('published_at')
            ->orderBy('id')
            ->limit(max(1, min($limit, 1000)))
            ->get();

        $created = 0;

        foreach ($events as $event) {
            $subscriptions = DB::table('webhook_subscriptions')
                ->where('tenant_id', $event->tenant_id)
                ->where('status', 'active')
                ->get();

            foreach ($subscriptions as $subscription) {
                $types = json_decode(
                    (string) $subscription->event_types,
                    true
                );

                if (! is_array($types)
                    || ! in_array($event->event_type, $types, true)) {
                    continue;
                }

                $existing = DB::table('webhook_deliveries')
                    ->where(
                        'webhook_subscription_id',
                        $subscription->id
                    )
                    ->where('event_id', $event->event_id)
                    ->exists();

                if ($existing) {
                    continue;
                }

                $deliveryId = DB::table('webhook_deliveries')->insertGetId([
                    'tenant_id' => $event->tenant_id,
                    'webhook_subscription_id' => $subscription->id,
                    'delivery_uuid' => (string) Str::uuid(),
                    'event_id' => $event->event_id,
                    'event_type' => $event->event_type,
                    'payload' => $event->payload,
                    'status' => 'pending',
                    'next_attempt_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DeliverWebhook::dispatch($deliveryId)
                    ->onQueue('external-delivery');

                $created++;
            }
        }

        return $created;
    }
}