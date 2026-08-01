<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminResourceController
{
    public function products(Request $request, TenantContext $tenant): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 50), 1), 100);

        $rows = DB::table('products')
            ->where('tenant_id', $tenant->id())
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'sku',
                'name',
                'type',
                'status',
                'currency',
                'price_minor',
                'cost_minor',
                'created_at',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function orders(Request $request, TenantContext $tenant): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 50), 1), 100);

        $rows = DB::table('orders')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->where('orders.tenant_id', $tenant->id())
            ->orderByDesc('orders.id')
            ->limit($limit)
            ->get([
                'orders.id',
                'orders.order_number',
                'orders.status',
                'orders.currency',
                'orders.total_minor',
                'orders.confirmed_at',
                'orders.created_at',
                'users.name as customer_name',
                'users.email as customer_email',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function wallets(Request $request, TenantContext $tenant): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 50), 1), 100);

        $rows = DB::table('wallets')
            ->leftJoin('users', 'users.id', '=', 'wallets.user_id')
            ->where('wallets.tenant_id', $tenant->id())
            ->orderByDesc('wallets.id')
            ->limit($limit)
            ->get([
                'wallets.id',
                'wallets.currency',
                'wallets.status',
                'wallets.available_balance_minor',
                'wallets.held_balance_minor',
                'wallets.created_at',
                'users.name as owner_name',
                'users.email as owner_email',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function suppliers(Request $request, TenantContext $tenant): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 50), 1), 100);

        $rows = DB::table('suppliers')
            ->where('tenant_id', $tenant->id())
            ->orderBy('priority')
            ->orderBy('name')
            ->limit($limit)
            ->get([
                'id',
                'name',
                'code',
                'driver',
                'status',
                'priority',
                'timeout_seconds',
                'max_retries',
                'last_healthcheck_at',
                'created_at',
            ]);

        return response()->json(['data' => $rows]);
    }
}