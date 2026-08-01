<?php

namespace App\Modules\Gateway\Application\Jobs;

use App\Modules\Gateway\Application\WebhookDestinationPolicy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly int $deliveryId,
    ) {
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(WebhookDestinationPolicy $destinations): void
    {
        $delivery = DB::table('webhook_deliveries')
            ->where('id', $this->deliveryId)
            ->first();

        if (! $delivery || $delivery->status === 'delivered') {
            return;
        }

        $subscription = DB::table('webhook_subscriptions')
            ->where('id', $delivery->webhook_subscription_id)
            ->first();

        if (! $subscription || $subscription->status !== 'active') {
            DB::table('webhook_deliveries')
                ->where('id', $this->deliveryId)
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now(),
                ]);

            return;
        }

        if (! config('agcp.external_delivery_enabled', false)
            || ! $subscription->external_delivery_enabled) {
            DB::table('webhook_deliveries')
                ->where('id', $this->deliveryId)
                ->update([
                    'status' => 'blocked_external_disabled',
                    'updated_at' => now(),
                ]);

            return;
        }

        $destinations->assertAllowed($subscription->endpoint_url);

        $secret = Crypt::decryptString($subscription->encrypted_secret);
        $rawPayload = is_string($delivery->payload)
            ? $delivery->payload
            : json_encode($delivery->payload, JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $rawPayload, $secret);

        DB::table('webhook_deliveries')
            ->where('id', $this->deliveryId)
            ->increment('attempts');

        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-AGCP-Event' => $delivery->event_type,
                    'X-AGCP-Event-Id' => $delivery->event_id,
                    'X-AGCP-Delivery' => $delivery->delivery_uuid,
                    'X-AGCP-Signature' => 'sha256='.$signature,
                ])
                ->withBody($rawPayload, 'application/json')
                ->post($subscription->endpoint_url);

            if (! $response->successful()) {
                throw new RuntimeException(
                    'Webhook returned HTTP '.$response->status()
                );
            }

            DB::transaction(function () use (
                $delivery,
                $subscription,
                $response,
            ): void {
                DB::table('webhook_deliveries')
                    ->where('id', $delivery->id)
                    ->update([
                        'status' => 'delivered',
                        'response_code' => $response->status(),
                        'response_excerpt' => mb_substr(
                            $response->body(),
                            0,
                            2000
                        ),
                        'last_error' => null,
                        'delivered_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('webhook_subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'consecutive_failures' => 0,
                        'last_success_at' => now(),
                        'updated_at' => now(),
                    ]);
            });
        } catch (\Throwable $e) {
            DB::transaction(function () use (
                $delivery,
                $subscription,
                $e,
            ): void {
                DB::table('webhook_deliveries')
                    ->where('id', $delivery->id)
                    ->update([
                        'status' => 'retrying',
                        'last_error' => mb_substr(
                            $e->getMessage(),
                            0,
                            5000
                        ),
                        'next_attempt_at' => now()->addMinutes(5),
                        'updated_at' => now(),
                    ]);

                DB::table('webhook_subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'consecutive_failures' => DB::raw(
                            'consecutive_failures + 1'
                        ),
                        'last_failure_at' => now(),
                        'updated_at' => now(),
                    ]);
            });

            throw $e;
        }
    }
}