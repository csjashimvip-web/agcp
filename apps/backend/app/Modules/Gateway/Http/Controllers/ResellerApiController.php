<?php

namespace App\Modules\Gateway\Http\Controllers;

use App\Modules\Checkout\Application\CheckoutService;
use App\Modules\Catalog\Domain\Models\Product;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResellerApiController
{
    public function services(TenantContext $tenant): JsonResponse
    {
        $rows = Product::query()
            ->where('tenant_id', $tenant->id())
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(1000)
            ->get([
                'id',
                'sku',
                'name',
                'type',
                'currency',
                'price_minor',
                'service_schema',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function balance(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $rows = Wallet::query()
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->get([
                'id',
                'currency',
                'available_balance_minor',
                'held_balance_minor',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function placeOrder(
        Request $request,
        TenantContext $tenant,
        CheckoutService $checkout,
    ): JsonResponse {
        $validated = $request->validate([
            'external_reference' => ['required', 'string', 'max:160'],
            'sku' => ['required', 'string', 'max:96'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'service_input' => ['nullable', 'array'],
        ]);

        $product = Product::query()
            ->where('tenant_id', $tenant->id())
            ->where('sku', $validated['sku'])
            ->where('status', 'active')
            ->firstOrFail();

        $wallet = Wallet::query()
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $request->user()->id)
            ->where('currency', $product->currency)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if (! $wallet) {
            throw ValidationException::withMessages([
                'wallet' => [
                    "No active {$product->currency} wallet is available for this API client.",
                ],
            ]);
        }

        $client = $request->attributes->get('agcp_api_client');

        $order = $checkout->checkout(
            tenantId: $tenant->id(),
            userId: (int) $request->user()->id,
            wallet: $wallet,
            items: [[
                'product_id' => $product->id,
                'quantity' => $validated['quantity'] ?? 1,
                'service_input' => $validated['service_input'] ?? null,
            ]],
            idempotencyKey: 'reseller:'.$client->id.':'.$validated['external_reference'],
        );

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'external_reference' => $validated['external_reference'],
                'status' => $order->status,
                'currency' => $order->currency,
                'total_minor' => $order->total_minor,
                'created_at' => $order->created_at,
            ],
        ], 201);
    }

    public function orders(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $rows = DB::table('orders')
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'id',
                'order_number',
                'status',
                'currency',
                'total_minor',
                'confirmed_at',
                'completed_at',
                'cancelled_at',
                'created_at',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function order(
        Request $request,
        int $orderId,
        TenantContext $tenant,
    ): JsonResponse {
        $order = DB::table('orders')
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $request->user()->id)
            ->where('id', $orderId)
            ->first();

        abort_unless($order, 404);

        $items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->get([
                'id',
                'sku',
                'name',
                'quantity',
                'line_total_minor',
                'fulfillment_status',
            ]);

        return response()->json([
            'data' => [
                'order' => $order,
                'items' => $items,
            ],
        ]);
    }
}