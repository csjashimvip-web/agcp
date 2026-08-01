<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $membershipId = (int) $request->attributes->get('agcp_membership_id');

        if ($membershipId <= 0) {
            return new JsonResponse(['message' => 'Tenant membership has not been resolved.'], 403);
        }

        $hasSuperAdminRole = DB::table('membership_role')
            ->join('roles', 'roles.id', '=', 'membership_role.role_id')
            ->where('membership_role.tenant_membership_id', $membershipId)
            ->where('roles.slug', 'platform-super-admin')
            ->exists();

        if ($hasSuperAdminRole) {
            return $next($request);
        }

        $allowed = DB::table('membership_role')
            ->join('permission_role', 'permission_role.role_id', '=', 'membership_role.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('membership_role.tenant_membership_id', $membershipId)
            ->where('permissions.slug', $permission)
            ->exists();

        if (! $allowed) {
            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}