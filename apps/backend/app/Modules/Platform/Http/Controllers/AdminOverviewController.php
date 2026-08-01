<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class AdminOverviewController
{
    public function __invoke(TenantContext $tenant): JsonResponse
    {
        $tenantId = $tenant->id();

        return response()->json([
            'data' => [
                'tenant_id' => $tenantId,
                'users' => DB::table('tenant_memberships')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->count(),
                'products' => DB::table('products')
                    ->where('tenant_id', $tenantId)
                    ->count(),
                'orders' => DB::table('orders')
                    ->where('tenant_id', $tenantId)
                    ->count(),
                'suppliers' => DB::table('suppliers')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->count(),
                'wallet_liability_minor' => (int) DB::table('wallets')
                    ->where('tenant_id', $tenantId)
                    ->sum('available_balance_minor'),
                'pending_supplier_orders' => DB::table('supplier_orders')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['queued', 'submitted', 'processing', 'pending'])
                    ->count(),
                'failed_supplier_orders' => DB::table('supplier_orders')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'failed')
                    ->count(),
                'pending_deposits' => DB::table('deposits')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->count(),
                'unmapped_supplier_services' => DB::table('supplier_service_inbox')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'unmapped')
                    ->count(),
                'pending_outbox_events' => DB::table('outbox_events')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('published_at')
                    ->count(),
            ],
        ]);
    }
}