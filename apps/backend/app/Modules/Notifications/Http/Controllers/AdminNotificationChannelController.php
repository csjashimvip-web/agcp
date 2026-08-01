<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class AdminNotificationChannelController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'channels' => DB::table('notification_channels')
                    ->where('tenant_id', $tenant->id())
                    ->orderBy('channel_type')
                    ->get([
                        'id',
                        'name',
                        'channel_type',
                        'status',
                        'external_delivery_enabled',
                        'created_at',
                    ]),
                'deliveries' => DB::table('notification_channel_deliveries')
                    ->where('tenant_id', $tenant->id())
                    ->orderByDesc('id')
                    ->limit(200)
                    ->get([
                        'id',
                        'channel_type',
                        'status',
                        'subject',
                        'delivered_at',
                        'created_at',
                    ]),
            ],
        ]);
    }

    public function store(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel_type' => ['required', 'in:in_app,email,webhook'],
            'config' => ['nullable', 'array'],
            'external_delivery_enabled' => ['nullable', 'boolean'],
        ]);

        $external = (bool) ($validated['external_delivery_enabled'] ?? false);

        if ($validated['channel_type'] === 'in_app') {
            $external = false;
        }

        $id = DB::table('notification_channels')->insertGetId([
            'tenant_id' => $tenant->id(),
            'name' => $validated['name'],
            'channel_type' => $validated['channel_type'],
            'status' => 'enabled',
            'encrypted_config' => empty($validated['config'])
                ? null
                : Crypt::encryptString(
                    json_encode($validated['config'], JSON_THROW_ON_ERROR)
                ),
            'external_delivery_enabled' => $external,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'notifications.channel.created',
            'notification_channel',
            $id,
            [
                'channel_type' => $validated['channel_type'],
                'external_delivery_enabled' => $external,
            ],
        );

        return response()->json([
            'data' => DB::table('notification_channels')
                ->where('id', $id)
                ->first([
                    'id',
                    'name',
                    'channel_type',
                    'status',
                    'external_delivery_enabled',
                ]),
        ], 201);
    }
}