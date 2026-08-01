<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Wallet\Application\ApproveDepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminDepositController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        $rows = DB::table('deposits')
            ->leftJoin('users', 'users.id', '=', 'deposits.user_id')
            ->where('deposits.tenant_id', $tenant->id())
            ->orderByDesc('deposits.id')
            ->limit(100)
            ->get([
                'deposits.id',
                'deposits.deposit_uuid',
                'deposits.amount_minor',
                'deposits.currency',
                'deposits.method',
                'deposits.status',
                'deposits.approved_at',
                'deposits.created_at',
                'users.name as customer_name',
                'users.email as customer_email',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function approve(
        Request $request,
        int $depositId,
        TenantContext $tenant,
        ApproveDepositService $service,
        AdminAuditService $audit,
    ): JsonResponse {
        $deposit = $service->approve($tenant->id(), $depositId);

        $audit->record(
            $request,
            $tenant->id(),
            'wallet.deposit.approved',
            'deposit',
            $depositId,
            ['amount_minor' => $deposit->amount_minor, 'currency' => $deposit->currency],
        );

        return response()->json(['data' => $deposit]);
    }
}