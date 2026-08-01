<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Application\TenantContext;
use App\Modules\Tenancy\Domain\Models\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $context,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401);
        }

        $selector = trim((string) $request->header('X-AGCP-Tenant'));

        if ($selector === '') {
            return new JsonResponse(['message' => 'X-AGCP-Tenant header is required.'], 422);
        }

        $tenant = is_numeric($selector)
            ? Tenant::query()->whereKey((int) $selector)->first()
            : Tenant::query()->where('slug', $selector)->first();

        if (! $tenant || $tenant->status !== 'active') {
            return new JsonResponse(['message' => 'Tenant was not found or is inactive.'], 404);
        }

        $membershipId = DB::table('tenant_memberships')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('id');

        if (! $membershipId) {
            return new JsonResponse(['message' => 'You do not have an active membership in this tenant.'], 403);
        }

        $this->context->set($tenant);

        $request->attributes->set('agcp_tenant', $tenant);
        $request->attributes->set('agcp_membership_id', (int) $membershipId);

        return $next($request);
    }
}