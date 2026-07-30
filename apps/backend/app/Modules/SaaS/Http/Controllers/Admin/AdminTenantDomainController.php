<?php
namespace Modules\SaaS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\SaaS\Application\Services\DomainManagementService;
use Modules\Tenancy\Application\TenantContext;
use Modules\Tenancy\Infrastructure\Models\TenantDomain;

final class AdminTenantDomainController extends Controller
{
    public function index(TenantContext $context): array
    {
        return ['data' => TenantDomain::query()->where('tenant_id', $context->requireId())->orderByDesc('is_primary')->get()->map(fn (TenantDomain $domain) => $this->payload($domain))->all()];
    }
    public function store(Request $request, TenantContext $context, DomainManagementService $service): array
    {
        $data = $request->validate(['domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)*[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/', Rule::unique('tenant_domains', 'domain')]]);
        return ['data' => $this->payload($service->create($context->requireId(), $data['domain']))];
    }
    public function verify(Request $request, TenantDomain $domain, TenantContext $context, DomainManagementService $service): array
    {
        abort_unless($domain->tenant_id === $context->requireId(), 404);
        $data = $request->validate(['token' => ['required', 'string', 'max:96']]);
        return ['data' => $this->payload($service->verify($domain, $data['token']))];
    }
    public function primary(TenantDomain $domain, TenantContext $context, DomainManagementService $service): array
    {
        abort_unless($domain->tenant_id === $context->requireId(), 404);
        return ['data' => $this->payload($service->makePrimary($domain))];
    }
    private function payload(TenantDomain $domain): array
    {
        return ['id' => $domain->id, 'domain' => $domain->domain, 'primary' => (bool) $domain->is_primary,
            'verified' => (bool) $domain->verified, 'status' => $domain->verification_status,
            'ssl_status' => $domain->ssl_status, 'verification_token' => $domain->verified ? null : $domain->verification_token,
            'verified_at' => $domain->verified_at?->toIso8601String()];
    }
}
