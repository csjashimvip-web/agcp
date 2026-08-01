<?php

namespace App\Modules\Reliability\Http\Controllers;

use App\Modules\Reliability\Application\ProductionCutoverService;
use App\Modules\Reliability\Application\SecurityAuditService;
use App\Modules\Reliability\Application\StagingAcceptanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminRc1StabilizationController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'security_audits' => DB::table('security_audit_runs')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(),
                'dependency_audits' => DB::table('dependency_audit_snapshots')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(),
                'staging_runs' => DB::table('staging_acceptance_runs')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(),
                'cutover_runs' => DB::table('production_cutover_runs')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(),
                'cutover_checks' => DB::table('production_cutover_checks')
                    ->orderByDesc('id')
                    ->limit(500)
                    ->get(),
            ],
        ]);
    }

    public function security(
        Request $request,
        SecurityAuditService $security,
    ): JsonResponse {
        $validated = $request->validate([
            'environment' => ['required', 'string', 'max:64'],
            'git_commit' => ['required', 'string', 'max:64'],
        ]);

        return response()->json([
            'data' => $security->run(
                $validated['environment'],
                $validated['git_commit'],
            ),
        ], 201);
    }

    public function staging(
        Request $request,
        StagingAcceptanceService $staging,
    ): JsonResponse {
        $validated = $request->validate([
            'git_commit' => ['required', 'string', 'max:64'],
            'environment' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json([
            'data' => $staging->run(
                $validated['git_commit'],
                $validated['environment'] ?? 'staging',
            ),
        ], 201);
    }

    public function createCutover(
        Request $request,
        ProductionCutoverService $cutover,
    ): JsonResponse {
        $validated = $request->validate([
            'git_commit' => ['required', 'string', 'max:64'],
        ]);

        return response()->json([
            'data' => $cutover->create(
                $validated['git_commit'],
                'production',
            ),
        ], 201);
    }

    public function completeCheck(
        Request $request,
        int $runId,
        ProductionCutoverService $cutover,
    ): JsonResponse {
        $validated = $request->validate([
            'check_key' => ['required', 'string', 'max:128'],
            'passed' => ['required', 'boolean'],
            'evidence' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        return response()->json([
            'data' => $cutover->completeManualCheck(
                $runId,
                $validated['check_key'],
                $validated['passed'],
                $validated['evidence'],
            ),
        ]);
    }

    public function openTraffic(
        int $runId,
        ProductionCutoverService $cutover,
    ): JsonResponse {
        return response()->json([
            'data' => $cutover->openTraffic($runId),
        ]);
    }
}