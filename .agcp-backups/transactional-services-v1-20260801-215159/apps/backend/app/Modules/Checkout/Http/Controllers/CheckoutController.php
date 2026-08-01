<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Modules\Checkout\Application\CheckoutService;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CheckoutController
{
    public function __invoke(Request $request, CheckoutService $checkout): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'idempotency_key' => ['required', 'string', 'max:160'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.service_input' => ['sometimes', 'array'],
        ]);

        $wallet = Wallet::query()->findOrFail($validated['wallet_id']);

        $order = $checkout->checkout(
            tenantId: $validated['tenant_id'],
            userId: $validated['user_id'],
            wallet: $wallet,
            items: $validated['items'],
            idempotencyKey: $validated['idempotency_key'],
        );

        return response()->json([
            'data' => $order->load('items'),
        ], 201);
    }
}