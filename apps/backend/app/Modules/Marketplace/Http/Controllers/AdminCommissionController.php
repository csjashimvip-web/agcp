<?php

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Marketplace\Application\CommissionSettlementService;
use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminCommissionController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'accruals' => DB::table('commission_accruals')
                    ->join(
                        'marketplace_sellers',
                        'marketplace_sellers.id',
                        '=',
                        'commission_accruals.marketplace_seller_id'
                    )
                    ->where('commission_accruals.tenant_id', $tenant->id())
                    ->orderByDesc('commission_accruals.id')
                    ->limit(300)
                    ->get([
                        'commission_accruals.id',
                        'commission_accruals.marketplace_seller_id',
                        'commission_accruals.amount_minor',
                        'commission_accruals.currency',
                        'commission_accruals.status',
                        'marketplace_sellers.display_name as seller_name',
                    ]),
                'settlements' => DB::table('commission_settlements')
                    ->join(
                        'marketplace_sellers',
                        'marketplace_sellers.id',
                        '=',
                        'commission_settlements.marketplace_seller_id'
                    )
                    ->where('commission_settlements.tenant_id', $tenant->id())
                    ->orderByDesc('commission_settlements.id')
                    ->limit(200)
                    ->get([
                        'commission_settlements.id',
                        'commission_settlements.amount_minor',
                        'commission_settlements.currency',
                        'commission_settlements.status',
                        'commission_settlements.settled_at',
                        'marketplace_sellers.display_name as seller_name',
                    ]),
            ],
        ]);
    }

    public function settle(
        Request $request,
        int $sellerId,
        TenantContext $tenant,
        CommissionSettlementService $settlements,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3'],
        ]);

        $row = $settlements->settleSeller(
            $tenant->id(),
            $sellerId,
            strtoupper($validated['currency']),
        );

        $audit->record(
            $request,
            $tenant->id(),
            'marketplace.commission.settled',
            'commission_settlement',
            $row->id,
            [
                'seller_id' => $sellerId,
                'amount_minor' => $row->amount_minor,
                'currency' => $row->currency,
            ],
        );

        return response()->json(['data' => $row], 201);
    }
}