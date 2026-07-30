<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class LivenessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'agcp-api',
            'version' => config('app.version'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
