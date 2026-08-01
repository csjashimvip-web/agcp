<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Application\CatalogImportService;
use App\Modules\Platform\Application\TenantDataExportService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminDataOperationsController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'exports' => DB::table('data_export_jobs')
                    ->where('tenant_id', $tenant->id())
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get(),
                'imports' => DB::table('data_import_jobs')
                    ->where('tenant_id', $tenant->id())
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get(),
                'retention_policies' => DB::table('data_retention_policies')
                    ->where(function ($query) use ($tenant): void {
                        $query->whereNull('tenant_id')
                            ->orWhere('tenant_id', $tenant->id());
                    })
                    ->orderBy('dataset')
                    ->get(),
            ],
        ]);
    }

    public function export(
        Request $request,
        TenantContext $tenant,
        TenantDataExportService $exports,
    ): JsonResponse {
        $row = $exports->create(
            $tenant->id(),
            (int) $request->user()->id,
        );

        return response()->json(['data' => $row], 201);
    }

    public function importCatalog(
        Request $request,
        TenantContext $tenant,
        CatalogImportService $imports,
    ): JsonResponse {
        $validated = $request->validate([
            'dry_run' => ['required', 'boolean'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'rows' => ['required', 'array', 'min:1', 'max:5000'],
            'rows.*' => ['required', 'array'],
        ]);

        $row = $imports->run(
            tenantId: $tenant->id(),
            requestedByUserId: (int) $request->user()->id,
            rows: $validated['rows'],
            dryRun: $validated['dry_run'],
            sourceName: $validated['source_name'] ?? 'admin-api',
        );

        return response()->json(['data' => $row], 201);
    }

    public function saveRetention(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $validated = $request->validate([
            'dataset' => ['required', 'string', 'max:96'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:36500'],
            'mode' => ['required', 'in:review,purge'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::table('data_retention_policies')->updateOrInsert(
            [
                'tenant_id' => $tenant->id(),
                'dataset' => $validated['dataset'],
            ],
            [
                'retention_days' => $validated['retention_days'],
                'mode' => $validated['mode'],
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json(['data' => ['saved' => true]]);
    }
}