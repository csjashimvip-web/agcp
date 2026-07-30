<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Application\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasPermission($permission, $this->tenantContext->id())) {
            abort(403, 'You do not have permission to perform this action.');
        }

        if ($user->currentAccessToken() !== null && ! $user->tokenCan($permission)) {
            abort(403, 'This API token does not have the required ability.');
        }

        return $next($request);
    }
}
