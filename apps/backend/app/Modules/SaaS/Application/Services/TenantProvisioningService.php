<?php
namespace Modules\SaaS\Application\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Application\AuditLogger;
use Modules\Identity\Infrastructure\Models\Permission;
use Modules\Identity\Infrastructure\Models\Role;
use Modules\Identity\Infrastructure\Models\TenantMembership;
use Modules\SaaS\Domain\Enums\SubscriptionStatus;
use Modules\SaaS\Infrastructure\Models\SubscriptionPlan;
use Modules\SaaS\Infrastructure\Models\TenantBrandingProfile;
use Modules\SaaS\Infrastructure\Models\TenantSubscription;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Tenancy\Infrastructure\Models\TenantDomain;

final class TenantProvisioningService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(User $actor, array $data): Tenant
    {
        return DB::transaction(function () use ($actor, $data): Tenant {
            $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);
            $tenant = Tenant::query()->create([
                'name' => $data['name'], 'slug' => $data['slug'], 'status' => 'active',
                'default_currency' => $data['default_currency'] ?? 'USD', 'timezone' => $data['timezone'] ?? 'UTC',
                'activated_at' => now(), 'settings' => [],
            ]);
            TenantBrandingProfile::query()->create([
                'tenant_id' => $tenant->id, 'display_name' => $data['name'], 'legal_name' => $data['legal_name'] ?? null,
                'support_email' => $data['support_email'] ?? null,
            ]);
            if (! empty($data['domain'])) {
                TenantDomain::query()->create([
                    'tenant_id' => $tenant->id, 'domain' => strtolower($data['domain']), 'is_primary' => true,
                    'verified' => false, 'verification_token' => bin2hex(random_bytes(24)),
                    'verification_method' => 'manual', 'verification_status' => 'pending', 'ssl_status' => 'pending',
                ]);
            }
            TenantSubscription::query()->create([
                'tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id,
                'status' => $plan->trial_days > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'billing_cycle' => $data['billing_cycle'] ?? 'monthly', 'started_at' => now(),
                'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
                'current_period_start' => now(),
                'current_period_end' => ($data['billing_cycle'] ?? 'monthly') === 'yearly' ? now()->addYear() : now()->addMonth(),
            ]);
            [$tenantAdmin] = $this->createTenantRoles($tenant);
            if (! empty($data['owner_user_id'])) $this->attachOwner($tenant, (string) $data['owner_user_id'], $tenantAdmin);
            $this->audit->record('saas.tenant.provisioned', Tenant::class, $tenant->id, ['plan' => $plan->slug], [], $tenant->id, User::class, $actor->id);
            return $tenant->fresh(['domains']);
        });
    }

    private function createTenantRoles(Tenant $tenant): array
    {
        $tenantAdmin = Role::query()->firstOrCreate(['tenant_id' => $tenant->id, 'slug' => 'tenant-admin'], [
            'name' => 'Tenant Administrator', 'description' => 'Administrative access inside the tenant.', 'is_system' => true,
        ]);
        $tenantAdmin->permissions()->sync(Permission::query()->whereNotIn('slug', [
            'platform.admin.access', 'saas.platform.manage', 'saas.plans.manage', 'saas.subscriptions.manage',
        ])->pluck('id')->all());

        $customer = Role::query()->firstOrCreate(['tenant_id' => $tenant->id, 'slug' => 'customer'], [
            'name' => 'Customer', 'description' => 'Default customer access.', 'is_system' => true,
        ]);
        $customer->permissions()->sync(Permission::query()->whereIn('slug', [
            'profile.read', 'profile.update', 'identity.sessions.manage', 'api.tokens.manage',
            'wallet.view', 'wallet.deposit.create', 'commerce.catalog.view', 'commerce.cart.manage',
            'commerce.checkout', 'commerce.orders.view',
        ])->pluck('id')->all());

        return [$tenantAdmin, $customer];
    }

    private function attachOwner(Tenant $tenant, string $userId, Role $tenantAdmin): void
    {
        $owner = User::query()->findOrFail($userId);
        TenantMembership::query()->firstOrCreate(['tenant_id' => $tenant->id, 'user_id' => $owner->id], ['status' => 'active', 'joined_at' => now()]);
        $owner->roles()->syncWithoutDetaching([$tenantAdmin->id]);
    }
}
