<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Wallet\Application\PayoutRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class CustomerPayoutController
{
    public function index(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        return response()->json([
            'data' => DB::table('payout_requests')
                ->where('tenant_id', $tenant->id())
                ->where('user_id', $request->user()->id)
                ->orderByDesc('id')
                ->limit(100)
                ->get([
                    'id',
                    'amount_minor',
                    'currency',
                    'method',
                    'destination_label',
                    'status',
                    'review_note',
                    'created_at',
                ]),
        ]);
    }

    public function store(
        Request $request,
        TenantContext $tenant,
        PayoutRequestService $payouts,
    ): JsonResponse {
        $validated = $request->validate([
            'wallet_id' => [
                'required',
                'integer',
                Rule::exists('wallets', 'id')
                    ->where(
                        fn ($q) => $q
                            ->where('tenant_id', $tenant->id())
                            ->where('user_id', $request->user()->id)
                    ),
            ],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'max:64'],
            'destination_label' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'array', 'min:1'],
        ]);

        $row = $payouts->request(
            tenantId: $tenant->id(),
            userId: (int) $request->user()->id,
            walletId: $validated['wallet_id'],
            amountMinor: $validated['amount_minor'],
            method: $validated['method'],
            destinationLabel: $validated['destination_label'],
            destination: $validated['destination'],
        );

        return response()->json(['data' => $row], 201);
    }
}