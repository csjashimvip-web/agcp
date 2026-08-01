<?php

namespace App\Modules\Notifications\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NotificationChannelRouter
{
    /**
     * @param array<string,mixed> $metadata
     */
    public function queue(
        int $tenantId,
        ?int $userId,
        string $channelType,
        ?string $subject,
        string $body,
        array $metadata = [],
    ): int {
        $channel = DB::table('notification_channels')
            ->where('tenant_id', $tenantId)
            ->where('channel_type', $channelType)
            ->where('status', 'enabled')
            ->orderBy('id')
            ->first();

        $status = $channelType === 'in_app'
            ? 'queued'
            : (($channel?->external_delivery_enabled ?? false)
                ? 'queued'
                : 'blocked_external_disabled');

        return DB::table('notification_channel_deliveries')->insertGetId([
            'tenant_id' => $tenantId,
            'notification_channel_id' => $channel?->id,
            'user_id' => $userId,
            'delivery_uuid' => (string) Str::uuid(),
            'channel_type' => $channelType,
            'status' => $status,
            'subject' => $subject,
            'body' => $body,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}