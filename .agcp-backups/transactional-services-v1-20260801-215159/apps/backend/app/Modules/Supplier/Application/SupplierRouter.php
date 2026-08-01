<?php

namespace App\Modules\Supplier\Application;

use App\Modules\Supplier\Domain\Models\SupplierService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SupplierRouter
{
    /**
     * @return Collection<int, SupplierService>
     */
    public function candidates(int $tenantId, int $productId): Collection
    {
        return SupplierService::query()
            ->select('supplier_services.*')
            ->join('supplier_routes', 'supplier_routes.supplier_service_id', '=', 'supplier_services.id')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_services.supplier_id')
            ->where('supplier_routes.tenant_id', $tenantId)
            ->where('supplier_routes.product_id', $productId)
            ->where('supplier_routes.enabled', true)
            ->where('supplier_services.status', 'active')
            ->where('suppliers.status', 'active')
            ->orderBy('supplier_routes.priority')
            ->orderBy('suppliers.priority')
            ->orderBy('supplier_services.cost_minor')
            ->get();
    }

    public function markAttempt(int $supplierOrderId, string $status, ?string $reason = null): void
    {
        DB::table('supplier_orders')
            ->where('id', $supplierOrderId)
            ->update([
                'status' => $status,
                'failure_reason' => $reason,
                'updated_at' => now(),
            ]);
    }
}