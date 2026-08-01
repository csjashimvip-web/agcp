<?php

namespace App\Modules\Reliability\Http\Controllers;

use App\Modules\Reliability\Application\ReadinessService;
use Illuminate\Http\JsonResponse;

final class PublicReadinessController
{
    public function __invoke(ReadinessService $readiness): JsonResponse
    {
        $probe = $readiness->probe(null, false);

        return response()->json([
            'status' => $probe['ready'] ? 'ready' : 'not_ready',
            'database' => $probe['database_ok'] ? 'ok' : 'failed',
            'cache' => $probe['cache_ok'] ? 'ok' : 'failed',
        ], $probe['ready'] ? 200 : 503);
    }
}