<?php

namespace App\Modules\Reliability\Http\Controllers;

use App\Modules\Reliability\Application\ReadinessService;
use App\Modules\Reliability\Application\SloEvaluator;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminReliabilityController
{
    public function index(
        TenantContext $tenant,
        ReadinessService $readiness,
        SloEvaluator $slo,
    ): JsonResponse {
        $snapshot = $readiness->probe($tenant->id(), true);

        return response()->json([
            'data' => [
                'snapshot' => $snapshot,
                'slos' => $slo->evaluate($tenant->id()),
                'recent_snapshots' => DB::table('reliability_snapshots')
                    ->where(function ($query) use ($tenant): void {
                        $query->whereNull('tenant_id')
                            ->orWhere('tenant_id', $tenant->id());
                    })
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(),
                'backups' => DB::table('backup_catalogs')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(),
                'restore_drills' => DB::table('restore_drills')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(),
            ],
        ]);
    }

    public function createSlo(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'metric_key' => ['required', 'string', 'max:96'],
            'target_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'window_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
        ]);

        $id = DB::table('service_level_objectives')->insertGetId([
            'tenant_id' => $tenant->id(),
            'name' => $validated['name'],
            'metric_key' => $validated['metric_key'],
            'target_bps' => $validated['target_bps'],
            'window_minutes' => $validated['window_minutes'],
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => DB::table('service_level_objectives')
                ->where('id', $id)
                ->first(),
        ], 201);
    }
}