<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->status !== 'active' || $user->locked_until?->isFuture()) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $user->tokens()->delete();

            return response()->json([
                'message' => 'This account is not currently permitted to sign in.',
                'code' => 'ACCOUNT_NOT_ACTIVE',
            ], 403);
        }

        return $next($request);
    }
}
