<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Application\DeploymentReadinessService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminDeploymentController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'releases' => DB::table('deployment_releases')
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get(),
                'checks' => DB::table('deployment_checks')
                    ->orderByDesc('id')
                    ->limit(500)
                    ->get(),
            ],
        ]);
    }

    public function record(
        Request $request,
        DeploymentReadinessService $deployments,
    ): JsonResponse {
        $validated = $request->validate([
            'environment' => ['required', 'string', 'max:64'],
            'git_commit' => ['required', 'string', 'max:64'],
        ]);

        $row = $deployments->record(
            $validated['environment'],
            $validated['git_commit'],
            (int) $request->user()->id,
        );

        return response()->json(['data' => $row], 201);
    }
}