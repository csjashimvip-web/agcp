<?php

namespace Modules\Identity\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Http\Resources\SessionResource;
use Modules\Identity\Infrastructure\Models\AuthSession;

class SessionController
{
    public function index(Request $request): JsonResponse
    {
        $sessions = $request->user()->authSessions()
            ->with('device')
            ->whereNull('revoked_at')
            ->latest('last_active_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => SessionResource::collection($sessions)]);
    }

    public function destroy(Request $request, AuthSession $session): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 404);

        $current = $request->attributes->get('auth_session_id') === $session->id;
        $session->forceFill(['revoked_at' => now()])->save();

        if ($current) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Session revoked.']);
    }

    public function destroyOthers(Request $request): JsonResponse
    {
        $currentId = $request->attributes->get('auth_session_id');

        $request->user()->authSessions()
            ->whereNull('revoked_at')
            ->when($currentId, fn ($query) => $query->whereKeyNot($currentId))
            ->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Other sessions revoked.']);
    }
}
