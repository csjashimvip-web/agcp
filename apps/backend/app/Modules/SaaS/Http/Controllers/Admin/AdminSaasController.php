<?php
namespace Modules\SaaS\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\SaaS\Application\Services\EntitlementService;
use Modules\SaaS\Application\Services\TenantProvisioningService;
use Modules\SaaS\Application\Services\UsageQuotaService;
use Modules\SaaS\Domain\Enums\SubscriptionStatus;
use Modules\SaaS\Infrastructure\Models\SubscriptionPlan;
use Modules\SaaS\Infrastructure\Models\TenantBrandingProfile;
use Modules\SaaS\Infrastructure\Models\TenantSubscription;
use Modules\Tenancy\Application\TenantContext;
use Modules\Tenancy\Infrastructure\Models\Tenant;

final class AdminSaasController extends Controller
{
    public function index(Request $request, TenantContext $context, EntitlementService $entitlements, UsageQuotaService $usage): array
    {
        $tenant = $context->tenant()?->load('domains');
        $platform = $request->user()->hasPermission('saas.platform.manage');
        return ['data' => [
            'platform_admin' => $platform,
            'current_tenant' => $this->tenantPayload($tenant),
            'entitlements' => $entitlements->snapshot($context->requireId()),
            'usage' => $usage->current($context->requireId()),
            'plans' => $this->plans($platform),
            'tenants' => $platform ? Tenant::query()->with(['domains'])->orderBy('name')->get()->map(fn ($item) => $this->tenantPayload($item))->all() : [],
        ]];
    }

    public function storePlan(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'slug' => ['required', 'alpha_dash', 'max:100', 'unique:subscription_plans,slug'],
            'currency' => ['required', 'string', 'size:3'], 'price_monthly_minor' => ['required', 'integer', 'min:0'],
            'price_yearly_minor' => ['required', 'integer', 'min:0'], 'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'features' => ['required', 'array'], 'limits' => ['nullable', 'array'], 'is_public' => ['nullable', 'boolean'],
        ]);
        $plan = SubscriptionPlan::query()->create(array_merge(['status' => 'active', 'trial_days' => 0, 'is_public' => true], $data));
        return ['data' => $this->planPayload($plan)];
    }

    public function storeTenant(Request $request, TenantProvisioningService $service): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'slug' => ['required', 'alpha_dash', 'max:120', 'unique:tenants,slug'],
            'domain' => ['nullable', 'string', 'max:253', 'regex:/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)*[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/', 'unique:tenant_domains,domain'], 'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'owner_user_id' => ['nullable', 'exists:users,id'], 'default_currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'timezone'], 'billing_cycle' => ['nullable', Rule::in(['monthly', 'yearly'])],
            'legal_name' => ['nullable', 'string', 'max:200'], 'support_email' => ['nullable', 'email:rfc', 'max:254'],
        ]);
        return ['data' => $this->tenantPayload($service->create($request->user(), $data))];
    }

    public function updateSubscription(Request $request, Tenant $tenant): array
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)], 'billing_cycle' => ['nullable', Rule::in(['monthly', 'yearly'])],
        ]);
        TenantSubscription::query()->where('tenant_id', $tenant->id)->whereIn('status', ['active', 'trialing'])->update(['status' => SubscriptionStatus::Canceled->value, 'canceled_at' => now()]);
        $subscription = TenantSubscription::query()->create([
            'tenant_id' => $tenant->id, 'subscription_plan_id' => $data['subscription_plan_id'],
            'status' => $data['status'] ?? SubscriptionStatus::Active, 'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
            'started_at' => now(), 'current_period_start' => now(),
            'current_period_end' => ($data['billing_cycle'] ?? 'monthly') === 'yearly' ? now()->addYear() : now()->addMonth(),
        ]);
        return ['data' => ['id' => $subscription->id, 'tenant_id' => $tenant->id, 'status' => $subscription->status->value]];
    }

    private function plans(bool $platform): array
    {
        $query = SubscriptionPlan::query();
        if (! $platform) $query->where('is_public', true);
        return $query->orderBy('sort_order')->get()->map(fn (SubscriptionPlan $plan) => $this->planPayload($plan))->all();
    }

    private function planPayload(SubscriptionPlan $plan): array
    {
        return ['id' => $plan->id, 'name' => $plan->name, 'slug' => $plan->slug, 'status' => $plan->status, 'currency' => $plan->currency,
            'price_monthly_minor' => $plan->price_monthly_minor, 'price_yearly_minor' => $plan->price_yearly_minor,
            'trial_days' => $plan->trial_days, 'features' => $plan->features, 'limits' => $plan->limits ?? [], 'is_public' => $plan->is_public];
    }

    private function tenantPayload(?Tenant $tenant): ?array
    {
        if ($tenant === null) return null;
        $branding = TenantBrandingProfile::query()->where('tenant_id', $tenant->id)->first();
        $subscription = TenantSubscription::query()->with('plan')->where('tenant_id', $tenant->id)->latest('started_at')->first();
        return ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug, 'status' => $tenant->status,
            'default_currency' => $tenant->default_currency, 'timezone' => $tenant->timezone,
            'branding' => $branding ? ['display_name' => $branding->display_name, 'primary_color' => $branding->primary_color, 'secondary_color' => $branding->secondary_color, 'support_email' => $branding->support_email] : null,
            'domains' => $tenant->relationLoaded('domains') ? $tenant->domains->map(fn ($domain) => ['id' => $domain->id, 'domain' => $domain->domain, 'primary' => $domain->is_primary, 'verified' => $domain->verified, 'status' => $domain->verification_status])->all() : [],
            'subscription' => $subscription ? ['id' => $subscription->id, 'status' => $subscription->status->value, 'plan' => $subscription->plan?->name, 'plan_slug' => $subscription->plan?->slug] : null];
    }
}
