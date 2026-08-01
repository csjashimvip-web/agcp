<?php

namespace App\Modules\Gateway\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireResellerApiAbility
{
    public function handle(
        Request $request,
        Closure $next,
        string $ability,
    ): Response {
        $client = $request->attributes->get('agcp_api_client');

        if (! $client) {
            return new JsonResponse(['message' => 'API client not resolved.'], 401);
        }

        $abilities = json_decode((string) ($client->abilities ?? '[]'), true);

        if (! is_array($abilities)) {
            $abilities = [];
        }

        if (! in_array('*', $abilities, true)
            && ! in_array($ability, $abilities, true)) {
            return new JsonResponse(['message' => 'API ability denied.'], 403);
        }

        return $next($request);
    }
}