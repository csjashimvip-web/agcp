<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Wallet\Application\PayoutRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminPayoutController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => DB::table('payout_requests')
                ->join('users', 'users.id', '=', 'payout_requests.user_id')
                ->where('payout_requests.tenant_id', $tenant->id())
                ->orderByDesc('payout_requests.id')
                ->limit(300)
                ->get([
                    'payout_requests.id',
                    'payout_requests.amount_minor',
                    'payout_requests.currency',
                    'payout_requests.method',
                    'payout_requests.destination_label',
                    'payout_requests.status',
                    'payout_requests.review_note',
                    'payout_requests.created_at',
                    'users.name as user_name',
                    'users.email as user_email',
                ]),
        ]);
    }

    public function approve(
        Request $request,
        int $payoutId,
        TenantContext $tenant,
        PayoutRequestService $payouts,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = $payouts->approve(
            $tenant->id(),
            $payoutId,
            $validated['note'] ?? null,
        );

        $audit->record(
            $request,
            $tenant->id(),
            'wallet.payout.approved',
            'payout_request',
            $payoutId,
        );

        return response()->json(['data' => $row]);
    }

    public function reject(
        Request $request,
        int $payoutId,
        TenantContext $tenant,
        PayoutRequestService $payouts,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $row = $payouts->reject(
            $tenant->id(),
            $payoutId,
            $validated['note'],
        );

        $audit->record(
            $request,
            $tenant->id(),
            'wallet.payout.rejected',
            'payout_request',
            $payoutId,
            ['note' => $validated['note']],
        );

        return response()->json(['data' => $row]);
    }

    public function paid(
        Request $request,
        int $payoutId,
        TenantContext $tenant,
        PayoutRequestService $payouts,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = $payouts->markPaid(
            $tenant->id(),
            $payoutId,
            $validated['note'] ?? null,
        );

        $audit->record(
            $request,
            $tenant->id(),
            'wallet.payout.marked_paid',
            'payout_request',
            $payoutId,
        );

        return response()->json(['data' => $row]);
    }
}