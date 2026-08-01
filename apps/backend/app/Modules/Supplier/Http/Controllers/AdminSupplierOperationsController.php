<?php

namespace App\Modules\Supplier\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Supplier\Application\Jobs\ReconcileSupplierOrder;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminSupplierOperationsController
{
    public function orders(TenantContext $tenant): JsonResponse
    {
        $rows = DB::table('supplier_orders')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_orders.supplier_id')
            ->join('order_items', 'order_items.id', '=', 'supplier_orders.order_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('supplier_orders.tenant_id', $tenant->id())
            ->orderByDesc('supplier_orders.id')
            ->limit(200)
            ->get([
                'supplier_orders.id',
                'supplier_orders.supplier_order_uuid',
                'supplier_orders.external_order_id',
                'supplier_orders.status',
                'supplier_orders.attempt',
                'supplier_orders.cost_minor',
                'supplier_orders.currency',
                'supplier_orders.failure_reason',
                'supplier_orders.submitted_at',
                'supplier_orders.completed_at',
                'supplier_orders.updated_at',
                'suppliers.name as supplier_name',
                'order_items.sku',
                'order_items.name as item_name',
                'orders.order_number',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function reconcile(
        Request $request,
        int $supplierOrderId,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $exists = DB::table('supplier_orders')
            ->where('tenant_id', $tenant->id())
            ->where('id', $supplierOrderId)
            ->exists();

        abort_unless($exists, 404);

        ReconcileSupplierOrder::dispatch($supplierOrderId)
            ->onQueue('supplier');

        $audit->record(
            $request,
            $tenant->id(),
            'supplier.order.reconciliation_queued',
            'supplier_order',
            $supplierOrderId,
        );

        return response()->json([
            'data' => [
                'queued' => true,
                'supplier_order_id' => $supplierOrderId,
            ],
        ], 202);
    }

    public function inbox(TenantContext $tenant): JsonResponse
    {
        $rows = DB::table('supplier_service_inbox')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_service_inbox.supplier_id')
            ->where('supplier_service_inbox.tenant_id', $tenant->id())
            ->orderBy('supplier_service_inbox.status')
            ->orderBy('supplier_service_inbox.external_name')
            ->limit(500)
            ->get([
                'supplier_service_inbox.id',
                'supplier_service_inbox.supplier_id',
                'supplier_service_inbox.external_service_id',
                'supplier_service_inbox.external_name',
                'supplier_service_inbox.cost_minor',
                'supplier_service_inbox.currency',
                'supplier_service_inbox.status',
                'supplier_service_inbox.mapped_product_id',
                'suppliers.name as supplier_name',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function routing(TenantContext $tenant): JsonResponse
    {
        $rows = DB::table('supplier_routes')
            ->join(
                'supplier_services',
                'supplier_services.id',
                '=',
                'supplier_routes.supplier_service_id'
            )
            ->join(
                'suppliers',
                'suppliers.id',
                '=',
                'supplier_services.supplier_id'
            )
            ->join(
                'products',
                'products.id',
                '=',
                'supplier_routes.product_id'
            )
            ->where('supplier_routes.tenant_id', $tenant->id())
            ->orderBy('products.name')
            ->orderBy('supplier_routes.priority')
            ->get([
                'supplier_routes.id',
                'supplier_routes.priority',
                'supplier_routes.weight',
                'supplier_routes.enabled',
                'products.id as product_id',
                'products.sku',
                'products.name as product_name',
                'supplier_services.external_service_id',
                'supplier_services.external_name',
                'supplier_services.cost_minor',
                'supplier_services.currency',
                'suppliers.name as supplier_name',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function updateRoute(
        Request $request,
        int $routeId,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'priority' => ['sometimes', 'required', 'integer', 'min:1', 'max:10000'],
            'weight' => ['sometimes', 'required', 'integer', 'min:1', 'max:10000'],
            'enabled' => ['sometimes', 'required', 'boolean'],
        ]);

        $route = DB::table('supplier_routes')
            ->where('tenant_id', $tenant->id())
            ->where('id', $routeId)
            ->first();

        abort_unless($route, 404);

        DB::table('supplier_routes')
            ->where('id', $routeId)
            ->update([
                ...$validated,
                'updated_at' => now(),
            ]);

        $audit->record(
            $request,
            $tenant->id(),
            'supplier.route.updated',
            'supplier_route',
            $routeId,
            $validated,
        );

        return response()->json([
            'data' => DB::table('supplier_routes')
                ->where('id', $routeId)
                ->first(),
        ]);
    }
}