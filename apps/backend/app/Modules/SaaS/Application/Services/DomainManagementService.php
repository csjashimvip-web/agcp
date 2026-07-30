<?php
namespace Modules\SaaS\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Tenancy\Infrastructure\Models\TenantDomain;

final class DomainManagementService
{
    public function create(string $tenantId, string $domain): TenantDomain
    {
        $domain = strtolower(trim($domain));
        return TenantDomain::query()->create([
            'tenant_id' => $tenantId, 'domain' => $domain, 'is_primary' => false, 'verified' => false,
            'verification_token' => bin2hex(random_bytes(24)), 'verification_method' => (string) config('saas.domain_verification_mode', 'manual'),
            'verification_status' => 'pending', 'ssl_status' => 'pending',
        ]);
    }

    public function verify(TenantDomain $domain, string $token): TenantDomain
    {
        if (! hash_equals((string) $domain->verification_token, $token)) {
            throw ValidationException::withMessages(['token' => 'The domain verification token is invalid.']);
        }
        $domain->forceFill(['verified' => true, 'verification_status' => 'verified', 'verified_at' => now(), 'last_checked_at' => now(), 'ssl_status' => 'managed'])->save();
        return $domain->fresh();
    }

    public function makePrimary(TenantDomain $domain): TenantDomain
    {
        abort_unless($domain->verified, 422, 'Only a verified domain can become primary.');
        return DB::transaction(function () use ($domain): TenantDomain {
            TenantDomain::query()->where('tenant_id', $domain->tenant_id)->update(['is_primary' => false]);
            $domain->forceFill(['is_primary' => true])->save();
            return $domain->fresh();
        });
    }
}
