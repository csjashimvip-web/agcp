<?php

namespace App\Modules\Notifications\Application\Jobs;

use App\Modules\Notifications\Application\EmailProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class DeliverEmailNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $channelDeliveryId,
    ) {
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(EmailProviderFactory $factory): void
    {
        $delivery = DB::table('notification_channel_deliveries')
            ->where('id', $this->channelDeliveryId)
            ->where('channel_type', 'email')
            ->first();

        if (! $delivery || $delivery->status === 'delivered') {
            return;
        }

        $user = $delivery->user_id
            ? DB::table('users')->where('id', $delivery->user_id)->first()
            : null;

        if (! $user?->email) {
            DB::table('notification_channel_deliveries')
                ->where('id', $delivery->id)
                ->update([
                    'status' => 'failed_no_recipient',
                    'updated_at' => now(),
                ]);

            return;
        }

        $provider = DB::table('email_provider_configs')
            ->where('tenant_id', $delivery->tenant_id)
            ->where('status', 'enabled')
            ->orderBy('id')
            ->first();

        if (! $provider
            || ! config('agcp.external_delivery_enabled', false)
            || ! $provider->external_delivery_enabled) {
            DB::table('notification_channel_deliveries')
                ->where('id', $delivery->id)
                ->update([
                    'status' => 'blocked_external_disabled',
                    'updated_at' => now(),
                ]);

            return;
        }

        $config = $provider->encrypted_config
            ? json_decode(
                Crypt::decryptString($provider->encrypted_config),
                true
            )
            : [];

        if (! is_array($config)) {
            $config = [];
        }

        $attemptId = DB::table('email_delivery_attempts')->insertGetId([
            'tenant_id' => $delivery->tenant_id,
            'notification_channel_delivery_id' => $delivery->id,
            'email_provider_config_id' => $provider->id,
            'attempt_uuid' => (string) Str::uuid(),
            'recipient' => $user->email,
            'subject' => $delivery->subject,
            'status' => 'sending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $factory->make($provider->driver)->send(
                $user->email,
                $delivery->subject,
                (string) $delivery->body,
                $config,
            );

            DB::transaction(function () use (
                $attemptId,
                $delivery,
            ): void {
                DB::table('email_delivery_attempts')
                    ->where('id', $attemptId)
                    ->update([
                        'status' => 'delivered',
                        'delivered_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('notification_channel_deliveries')
                    ->where('id', $delivery->id)
                    ->update([
                        'status' => 'delivered',
                        'delivered_at' => now(),
                        'updated_at' => now(),
                    ]);
            });
        } catch (\Throwable $e) {
            DB::table('email_delivery_attempts')
                ->where('id', $attemptId)
                ->update([
                    'status' => 'failed',
                    'last_error' => mb_substr(
                        $e->getMessage(),
                        0,
                        5000
                    ),
                    'updated_at' => now(),
                ]);

            throw new RuntimeException(
                'Email delivery failed: '.$e->getMessage(),
                previous: $e
            );
        }
    }
}