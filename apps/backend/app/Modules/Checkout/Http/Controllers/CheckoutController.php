<?php

namespace App\Modules\Checkout\Http\Controllers;

use App\Modules\Checkout\Application\CheckoutService;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Wallet\Domain\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CheckoutController
{
    public function __invoke(
        Request $request,
        CheckoutService $checkout,
        TenantContext $tenantContext,
    ): JsonResponse {
        $validated = $request->validate([
            'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
            'idempotency_key' => ['required', 'string', 'max:160'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.service_input' => ['sometimes', 'array'],
        ]);

        $user = $request->user();
        $wallet = Wallet::query()->findOrFail($validated['wallet_id']);

        $order = $checkout->checkout(
            tenantId: $tenantContext->id(),
            userId: (int) $user->id,
            wallet: $wallet,
            items: $validated['items'],
            idempotencyKey: $validated['idempotency_key'],
        );

        return response()->json([
            'data' => $order->load('items'),
        ], 201);
    }
}