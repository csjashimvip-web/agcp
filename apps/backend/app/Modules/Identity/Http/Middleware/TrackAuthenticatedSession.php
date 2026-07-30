<?php

namespace Modules\Identity\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Application\Services\DeviceDescriptor;
use Modules\Identity\Infrastructure\Models\AuthSession;
use Modules\Tenancy\Application\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class TrackAuthenticatedSession
{
    public function __construct(
        private readonly DeviceDescriptor $devices,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $sessionHash = hash_hmac('sha256', $request->session()->getId(), (string) config('app.key'));
        $session = AuthSession::query()
            ->where('user_id', $user->id)
            ->where('session_hash', $sessionHash)
            ->first();

        if ($session?->revoked_at !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'This session has been revoked.', 'code' => 'SESSION_REVOKED'], 401);
        }

        $device = $this->devices->touch($user, $request);

        $session = AuthSession::query()->updateOrCreate(
            ['user_id' => $user->id, 'session_hash' => $sessionHash],
            [
                'tenant_id' => $this->tenantContext->id(),
                'user_device_id' => $device->id,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'authenticated_at' => $session?->authenticated_at ?? now(),
                'last_active_at' => now(),
                'revoked_at' => null,
            ],
        );

        $request->attributes->set('auth_session_id', $session->id);

        return $next($request);
    }
}
