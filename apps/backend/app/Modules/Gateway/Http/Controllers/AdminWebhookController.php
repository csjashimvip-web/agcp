<?php

namespace App\Modules\Gateway\Http\Controllers;

use App\Modules\Gateway\Application\WebhookSubscriptionService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminWebhookController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'subscriptions' => DB::table('webhook_subscriptions')
                    ->where('tenant_id', $tenant->id())
                    ->orderByDesc('id')
                    ->get([
                        'id',
                        'name',
                        'endpoint_url',
                        'event_types',
                        'external_delivery_enabled',
                        'status',
                        'consecutive_failures',
                        'last_success_at',
                        'last_failure_at',
                        'created_at',
                    ]),
                'deliveries' => DB::table('webhook_deliveries')
                    ->where('tenant_id', $tenant->id())
                    ->orderByDesc('id')
                    ->limit(200)
                    ->get([
                        'id',
                        'event_type',
                        'status',
                        'attempts',
                        'response_code',
                        'delivered_at',
                        'created_at',
                    ]),
            ],
        ]);
    }

    public function store(
        Request $request,
        TenantContext $tenant,
        WebhookSubscriptionService $webhooks,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'endpoint_url' => ['required', 'url:https', 'max:2048'],
            'event_types' => ['required', 'array', 'min:1', 'max:50'],
            'event_types.*' => ['required', 'string', 'max:160'],
            'external_delivery_enabled' => ['nullable', 'boolean'],
        ]);

        $created = $webhooks->create(
            tenantId: $tenant->id(),
            name: $validated['name'],
            endpointUrl: $validated['endpoint_url'],
            events: $validated['event_types'],
            externalDeliveryEnabled:
                $validated['external_delivery_enabled'] ?? false,
        );

        return response()->json([
            'data' => [
                'subscription' => [
                    'id' => $created['record']->id,
                    'name' => $created['record']->name,
                    'endpoint_url' => $created['record']->endpoint_url,
                ],
                'signing_secret' => $created['secret'],
                'warning' => 'Signing secret is shown only once.',
            ],
        ], 201);
    }

    public function toggle(
        Request $request,
        int $subscriptionId,
        TenantContext $tenant,
    ): JsonResponse {
        $subscription = DB::table('webhook_subscriptions')
            ->where('tenant_id', $tenant->id())
            ->where('id', $subscriptionId)
            ->first();

        abort_unless($subscription, 404);

        $next = ! (bool) $subscription->external_delivery_enabled;

        DB::table('webhook_subscriptions')
            ->where('id', $subscriptionId)
            ->update([
                'external_delivery_enabled' => $next,
                'updated_at' => now(),
            ]);

        return response()->json([
            'data' => ['external_delivery_enabled' => $next],
        ]);
    }
}