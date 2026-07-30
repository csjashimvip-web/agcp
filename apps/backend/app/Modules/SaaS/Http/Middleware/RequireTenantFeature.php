<?php
namespace Modules\SaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\SaaS\Application\Services\EntitlementService;
use Modules\Tenancy\Application\TenantContext;
use Symfony\Component\HttpFoundation\Response;

final class RequireTenantFeature
{
    public function __construct(private readonly TenantContext $tenant, private readonly EntitlementService $entitlements) {}
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($this->entitlements->enabled($this->tenant->requireId(), $feature), 403, 'This feature is not included in the current subscription.');
        return $next($request);
    }
}
