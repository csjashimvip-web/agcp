<?php

namespace App\Modules\Reliability\Application;

use Illuminate\Support\Facades\DB;
use Throwable;

final class OutboxPublisher
{
    /**
     * @return array{published:int,failed:int}
     */
    public function publish(int $limit = 100): array
    {
        $ids = DB::table('outbox_events')
            ->whereNull('published_at')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        $published = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $didPublish = DB::transaction(function () use ($id): bool {
                    $event = DB::table('outbox_events')
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->first();

                    if (! $event || $event->published_at !== null) {
                        return false;
                    }

                    $payload = json_decode((string) $event->payload, true);

                    if (! is_array($payload)) {
                        $payload = [];
                    }

                    DB::table('event_publications')->insert([
                        'outbox_event_id' => $event->id,
                        'event_id' => $event->event_id,
                        'event_type' => $event->event_type,
                        'transport' => 'internal',
                        'status' => 'published',
                        'published_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->createInAppNotification(
                        tenantId: $event->tenant_id !== null
                            ? (int) $event->tenant_id
                            : null,
                        eventId: (string) $event->event_id,
                        eventType: (string) $event->event_type,
                        payload: $payload,
                    );

                    DB::table('outbox_events')
                        ->where('id', $event->id)
                        ->update([
                            'published_at' => now(),
                            'attempts' => DB::raw('attempts + 1'),
                            'last_error' => null,
                            'updated_at' => now(),
                        ]);

                    return true;
                }, 3);

                if ($didPublish) {
                    $published++;
                }
            } catch (Throwable $e) {
                $failed++;

                DB::table('outbox_events')
                    ->where('id', $id)
                    ->update([
                        'attempts' => DB::raw('attempts + 1'),
                        'last_error' => mb_substr($e->getMessage(), 0, 65000),
                        'updated_at' => now(),
                    ]);
            }
        }

        return [
            'published' => $published,
            'failed' => $failed,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createInAppNotification(
        ?int $tenantId,
        string $eventId,
        string $eventType,
        array $payload,
    ): void {
        $orderId = isset($payload['order_id'])
            ? (int) $payload['order_id']
            : null;

        if (! $orderId) {
            return;
        }

        $order = DB::table('orders')->where('id', $orderId)->first();

        if (! $order || ! $order->user_id) {
            return;
        }

        [$title, $message] = $this->notificationCopy(
            $eventType,
            (string) $order->order_number
        );

        if ($title === null) {
            return;
        }

        DB::table('notification_deliveries')->updateOrInsert(
            [
                'event_id' => $eventId,
                'channel' => 'in_app',
                'user_id' => $order->user_id,
            ],
            [
                'tenant_id' => $tenantId,
                'template' => $eventType,
                'status' => 'delivered',
                'title' => $title,
                'message' => $message,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'delivered_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function notificationCopy(
        string $eventType,
        string $orderNumber,
    ): array {
        return match ($eventType) {
            'commerce.order.confirmed.v1' => [
                'Order confirmed',
                "Your order {$orderNumber} has been confirmed.",
            ],
            'supplier.order.submitted.v1' => [
                'Order processing',
                "Your order {$orderNumber} has been sent for fulfillment.",
            ],
            'supplier.order.status_changed.v1' => [
                'Order updated',
                "The fulfillment status of {$orderNumber} has changed.",
            ],
            'commerce.order.cancelled.v1' => [
                'Order cancelled',
                "Your order {$orderNumber} has been cancelled and compensated.",
            ],
            default => [null, null],
        };
    }
}