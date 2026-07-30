<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Modules\Tenancy\Application\TenantContext;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Tenancy\Infrastructure\Models\TenantDomain;
use Symfony\Component\HttpFoundation\Response;
class ResolveTenantContext
{
    public function __construct(private readonly TenantContext $context) {}
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolve($request);
        if ($tenant === null && config('tenancy.mode') !== 'single') abort(404, 'Tenant not found.');
        $this->context->set($tenant);
        try { return $next($request); } finally { $this->context->clear(); }
    }
    private function resolve(Request $request): ?Tenant
    {
        $header = (string) config('tenancy.header', 'X-Tenant-ID');
        $id = trim((string) $request->headers->get($header, ''));
        if ($id !== '') return Tenant::query()->whereKey($id)->where('status', 'active')->first();
        $domain = TenantDomain::query()->with('tenant')->where('domain', strtolower($request->getHost()))->where('verified', true)->first();
        if ($domain?->tenant?->status === 'active') return $domain->tenant;
        return config('tenancy.mode') === 'single' ? Tenant::query()->where('status', 'active')->oldest()->first() : null;
    }
}
