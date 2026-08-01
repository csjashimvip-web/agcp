<?php

namespace App\Modules\Supplier\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Supplier\Application\DhruServiceSync;
use App\Modules\Supplier\Domain\Models\Supplier;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminSupplierSyncController
{
    public function sync(
        Request $request,
        int $supplierId,
        TenantContext $tenant,
        DhruServiceSync $sync,
        AdminAuditService $audit,
    ): JsonResponse {
        $supplier = Supplier::query()
            ->where('tenant_id', $tenant->id())
            ->findOrFail($supplierId);

        $result = $sync->sync($supplier);

        $audit->record(
            $request,
            $tenant->id(),
            'supplier.services.synced',
            'supplier',
            $supplier->id,
            $result,
        );

        return response()->json(['data' => $result]);
    }

    public function inbox(
        int $supplierId,
        TenantContext $tenant,
    ): JsonResponse {
        $supplier = Supplier::query()
            ->where('tenant_id', $tenant->id())
            ->findOrFail($supplierId);

        $rows = DB::table('supplier_service_inbox')
            ->where('supplier_id', $supplier->id)
            ->orderBy('external_name')
            ->limit(500)
            ->get();

        return response()->json(['data' => $rows]);
    }
}