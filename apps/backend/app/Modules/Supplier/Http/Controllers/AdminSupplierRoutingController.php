<?php

namespace App\Modules\Supplier\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminSupplierRoutingController
{
    public function map(
        Request $request,
        int $inboxId,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->id())),
            ],
            'priority' => ['required', 'integer', 'min:1', 'max:10000'],
            'weight' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $inbox = DB::table('supplier_service_inbox')
            ->where('tenant_id', $tenant->id())
            ->where('id', $inboxId)
            ->first();

        abort_unless($inbox, 404);

        $service = DB::table('supplier_services')
            ->where('supplier_id', $inbox->supplier_id)
            ->where('product_id', $validated['product_id'])
            ->where('external_service_id', $inbox->external_service_id)
            ->first();

        if ($service) {
            DB::table('supplier_services')->where('id', $service->id)->update([
                'external_name' => $inbox->external_name,
                'cost_minor' => $inbox->cost_minor,
                'currency' => $inbox->currency,
                'status' => 'active',
                'metadata' => $inbox->metadata,
                'updated_at' => now(),
            ]);
            $supplierServiceId = $service->id;
        } else {
            $supplierServiceId = DB::table('supplier_services')->insertGetId([
                'supplier_id' => $inbox->supplier_id,
                'product_id' => $validated['product_id'],
                'external_service_id' => $inbox->external_service_id,
                'external_name' => $inbox->external_name,
                'cost_minor' => $inbox->cost_minor,
                'currency' => $inbox->currency,
                'status' => 'active',
                'metadata' => $inbox->metadata,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('supplier_routes')->updateOrInsert(
            [
                'product_id' => $validated['product_id'],
                'supplier_service_id' => $supplierServiceId,
            ],
            [
                'tenant_id' => $tenant->id(),
                'priority' => $validated['priority'],
                'weight' => $validated['weight'],
                'enabled' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table('supplier_service_inbox')->where('id', $inboxId)->update([
            'status' => 'mapped',
            'mapped_product_id' => $validated['product_id'],
            'updated_at' => now(),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'supplier.service.mapped',
            'supplier_service',
            $supplierServiceId,
            [
                'product_id' => $validated['product_id'],
                'priority' => $validated['priority'],
                'weight' => $validated['weight'],
            ],
        );

        return response()->json([
            'data' => [
                'supplier_service_id' => $supplierServiceId,
                'mapped' => true,
            ],
        ]);
    }
}