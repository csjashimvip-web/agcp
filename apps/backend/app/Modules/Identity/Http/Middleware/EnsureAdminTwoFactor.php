<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->currentAccessToken() !== null) {
            return response()->json([
                'message' => 'Administrative access requires an interactive browser session.',
                'code' => 'ADMIN_BROWSER_SESSION_REQUIRED',
            ], 403);
        }

        if ($user->two_factor_confirmed_at === null) {
            return response()->json([
                'message' => 'Two-factor authentication is required for administrative access.',
                'code' => 'ADMIN_TWO_FACTOR_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
