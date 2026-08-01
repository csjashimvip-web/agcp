<?php

namespace App\Modules\Reliability\Http\Controllers;

use App\Modules\Gateway\Application\ApiContractAuditService;
use App\Modules\Reliability\Application\PerformanceBaselineService;
use App\Modules\Reliability\Application\ReleaseCandidateAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminReleaseCandidateController
{
    public function index(
        TenantContext $tenant,
        ApiContractAuditService $contracts,
    ): JsonResponse {
        return response()->json([
            'data' => [
                'contracts' => $contracts->audit(true),
                'performance' => DB::table('performance_baselines')
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get(),
                'audits' => DB::table('release_candidate_audits')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(),
                'findings' => DB::table('release_candidate_findings')
                    ->orderByDesc('id')
                    ->limit(300)
                    ->get(),
            ],
        ]);
    }

    public function performance(
        Request $request,
        PerformanceBaselineService $performance,
    ): JsonResponse {
        $validated = $request->validate([
            'environment' => ['required', 'string', 'max:64'],
            'samples' => ['nullable', 'integer', 'min:5', 'max:200'],
        ]);

        return response()->json([
            'data' => $performance->capture(
                $validated['environment'],
                $validated['samples'] ?? 25,
            ),
        ], 201);
    }

    public function audit(
        Request $request,
        ReleaseCandidateAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'environment' => ['required', 'string', 'max:64'],
            'git_commit' => ['required', 'string', 'max:64'],
        ]);

        $row = $audit->run(
            $validated['environment'],
            $validated['git_commit'],
        );

        return response()->json(['data' => $row], 201);
    }
}