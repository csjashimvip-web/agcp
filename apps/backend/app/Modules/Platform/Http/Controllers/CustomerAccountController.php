<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class CustomerAccountController
{
    public function wallets(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $rows = DB::table('wallets')
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $request->user()->id)
            ->orderBy('currency')
            ->get([
                'id',
                'currency',
                'status',
                'available_balance_minor',
                'held_balance_minor',
                'created_at',
            ]);

        return response()->json(['data' => $rows]);
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

    public function deposits(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $rows = DB::table('deposits')
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get([
                'id',
                'deposit_uuid',
                'amount_minor',
                'currency',
                'method',
                'status',
                'approved_at',
                'created_at',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function requestDeposit(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $validated = $request->validate([
            'wallet_id' => [
                'required',
                'integer',
                Rule::exists('wallets', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('tenant_id', $tenant->id())
                            ->where('user_id', $request->user()->id)
                    ),
            ],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'max:64'],
        ]);

        $wallet = DB::table('wallets')
            ->where('id', $validated['wallet_id'])
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($wallet, 404);

        $depositId = DB::table('deposits')->insertGetId([
            'tenant_id' => $tenant->id(),
            'user_id' => $request->user()->id,
            'wallet_id' => $wallet->id,
            'deposit_uuid' => (string) Str::uuid(),
            'amount_minor' => $validated['amount_minor'],
            'currency' => $wallet->currency,
            'method' => $validated['method'],
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => DB::table('deposits')->where('id', $depositId)->first(),
        ], 201);
    }
}