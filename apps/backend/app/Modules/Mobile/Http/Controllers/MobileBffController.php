<?php

namespace App\Modules\Mobile\Http\Controllers;

use App\Modules\Pricing\Application\PricingEngine;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class MobileBffController
{
    public function bootstrap(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'tenant' => DB::table('tenants')
                    ->where('id', $tenant->id())
                    ->first(['id', 'name', 'slug', 'status', 'default_currency']),
                'wallets' => DB::table('wallets')
                    ->where('tenant_id', $tenant->id())
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->orderBy('currency')
                    ->get([
                        'id',
                        'currency',
                        'available_balance_minor',
                        'held_balance_minor',
                    ]),
                'recent_orders' => DB::table('orders')
                    ->where('tenant_id', $tenant->id())
                    ->where('user_id', $user->id)
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get([
                        'id',
                        'order_uuid',
                        'order_number',
                        'status',
                        'currency',
                        'total_minor',
                        'created_at',
                    ]),
                'unread_notifications' => DB::table('notification_deliveries')
                    ->where('tenant_id', $tenant->id())
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function catalog(
        Request $request,
        TenantContext $tenant,
        PricingEngine $pricing,
    ): JsonResponse {
        $products = DB::table('products')
            ->where('tenant_id', $tenant->id())
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(200)
            ->get([
                'id',
                'sku',
                'name',
                'slug',
                'type',
                'currency',
                'price_minor',
            ]);

        $rows = $products->map(function ($product) use (
            $pricing,
            $tenant,
            $request,
        ): array {
            $quote = $pricing->quote(
                tenantId: $tenant->id(),
                userId: (int) $request->user()->id,
                items: [[
                    'product_id' => (int) $product->id,
                    'quantity' => 1,
                ]],
                couponCode: null,
            );

            return [
                'id' => (int) $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'slug' => $product->slug,
                'type' => $product->type,
                'currency' => $product->currency,
                'unit_price_minor' => $quote['lines'][0]['unit_price_minor'],
                'pricing' => [
                    'tier_id' => $quote['tier_id'],
                    'tier_name' => $quote['tier_name'],
                ],
            ];
        });

        return response()->json(['data' => $rows->values()]);
    }

    public function orders(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        return response()->json([
            'data' => DB::table('orders')
                ->where('tenant_id', $tenant->id())
                ->where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->limit(100)
                ->get([
                    'id',
                    'order_uuid',
                    'order_number',
                    'status',
                    'currency',
                    'subtotal_minor',
                    'discount_minor',
                    'surcharge_minor',
                    'tax_minor',
                    'total_minor',
                    'created_at',
                ]),
        ]);
    }

    public function notifications(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        return response()->json([
            'data' => DB::table('notification_deliveries')
                ->where('tenant_id', $tenant->id())
                ->where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->limit(100)
                ->get([
                    'id',
                    'channel',
                    'template',
                    'status',
                    'title',
                    'message',
                    'delivered_at',
                    'read_at',
                    'created_at',
                ]),
        ]);
    }

    public function registerDevice(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $validated = $request->validate([
            'device_uuid' => ['required', 'string', 'max:128'],
            'platform' => ['required', Rule::in(['android', 'ios', 'web'])],
            'app_version' => ['nullable', 'string', 'max:64'],
            'push_token' => ['nullable', 'string', 'max:4096'],
        ]);

        DB::table('mobile_devices')->updateOrInsert(
            [
                'tenant_id' => $tenant->id(),
                'user_id' => $request->user()->id,
                'device_uuid' => $validated['device_uuid'],
            ],
            [
                'platform' => $validated['platform'],
                'app_version' => $validated['app_version'] ?? null,
                'push_token_hash' => isset($validated['push_token'])
                    ? hash('sha256', $validated['push_token'])
                    : null,
                'encrypted_push_token' => isset($validated['push_token'])
                    ? Crypt::encryptString($validated['push_token'])
                    : null,
                'status' => 'active',
                'last_seen_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'data' => ['registered' => true],
        ], 201);
    }
}