<?php

namespace App\Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class PlatformController
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'name' => 'Araabi Global Commerce Platform',
                'version' => config('agcp.version'),
                'architecture' => config('agcp.architecture'),
                'transactional_core' => config('agcp.transactional_core'),
            ],
        ]);
    }
}