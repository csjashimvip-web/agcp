<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Plugins\Application\Services\PluginManager;
use Modules\Plugins\Infrastructure\Models\Plugin;
use Modules\SaaS\Application\Services\EntitlementService;
use Modules\SaaS\Application\Services\TenantProvisioningService;
use Modules\SaaS\Application\Services\UsageQuotaService;
use Modules\SaaS\Domain\Enums\SubscriptionStatus;
use Modules\SaaS\Infrastructure\Models\SubscriptionPlan;
use Modules\SaaS\Infrastructure\Models\TenantSubscription;
use Modules\Tenancy\Infrastructure\Models\Tenant;

uses(RefreshDatabase::class);

function phase7Plan(array $features = ['plugins' => ['marketplace' => true]], array $limits = ['orders_monthly' => 2]): SubscriptionPlan
{
    return SubscriptionPlan::query()->create(['name' => 'Test Plan', 'slug' => 'test-plan-'.uniqid(), 'status' => 'active', 'currency' => 'USD',
        'price_monthly_minor' => 1000, 'price_yearly_minor' => 10000, 'trial_days' => 0, 'features' => $features, 'limits' => $limits, 'is_public' => true]);
}

function phase7Tenant(SubscriptionPlan $plan): Tenant
{
    $tenant = Tenant::query()->create(['name' => 'SaaS Tenant', 'slug' => 'saas-'.uniqid(), 'status' => 'active', 'default_currency' => 'USD', 'timezone' => 'UTC']);
    TenantSubscription::query()->create(['tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id, 'status' => SubscriptionStatus::Active,
        'billing_cycle' => 'monthly', 'started_at' => now(), 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
    return $tenant;
}

it('resolves plan entitlements and limits for a tenant', function () {
    $plan = phase7Plan(['plugins' => ['marketplace' => true], 'custom_domains' => true], ['users' => 10]);
    $tenant = phase7Tenant($plan);
    $service = app(EntitlementService::class);
    expect($service->enabled($tenant->id, 'plugins.marketplace'))->toBeTrue()
        ->and($service->enabled($tenant->id, 'custom_domains'))->toBeTrue()
        ->and($service->limit($tenant->id, 'users'))->toBe(10);
});

it('enforces a locked monthly quota', function () {
    $tenant = phase7Tenant(phase7Plan(limits: ['orders_monthly' => 2]));
    $usage = app(UsageQuotaService::class);
    $usage->consume($tenant->id, 'orders_monthly');
    $counter = $usage->consume($tenant->id, 'orders_monthly');
    expect($counter->quantity)->toBe(2);
    expect(fn () => $usage->consume($tenant->id, 'orders_monthly'))->toThrow(ValidationException::class);
});

it('encrypts plugin secrets and records installation lifecycle', function () {
    $tenant = phase7Tenant(phase7Plan());
    $actor = User::query()->create(['name' => 'Plugin Admin', 'email' => 'plugin-admin@example.test', 'password' => 'Secret123!', 'status' => 'active']);
    $manifest = ['slug' => 'secret-plugin', 'version' => '1.0.0'];
    $plugin = Plugin::query()->create(['slug' => 'secret-plugin', 'name' => 'Secret Plugin', 'version' => '1.0.0', 'category' => 'payment',
        'provider_type' => 'payment', 'provider_key' => 'secret', 'status' => 'available', 'is_core' => false,
        'capabilities' => [], 'config_schema' => ['required' => ['api_key'], 'properties' => ['api_key' => ['type' => 'string', 'secret' => true]]],
        'requested_permissions' => [], 'manifest' => $manifest, 'checksum' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR))]);
    $manager = app(PluginManager::class);
    $installation = $manager->install($tenant->id, $plugin, $actor, ['api_key' => 'top-secret-key']);
    $manager->enable($installation->load('plugin'), $actor);
    $raw = DB::table('plugin_installations')->where('id', $installation->id)->value('configuration');
    expect($raw)->not->toContain('top-secret-key')
        ->and($installation->fresh()->enabled)->toBeTrue()
        ->and($installation->events()->count())->toBe(2);
});

it('provisions an isolated tenant with subscription and owner membership', function () {
    $actor = User::query()->create(['name' => 'Platform Admin', 'email' => 'platform@example.test', 'password' => 'Secret123!', 'status' => 'active']);
    $owner = User::query()->create(['name' => 'Tenant Owner', 'email' => 'owner@example.test', 'password' => 'Secret123!', 'status' => 'active']);
    $plan = phase7Plan();
    $tenant = app(TenantProvisioningService::class)->create($actor, ['name' => 'New Company', 'slug' => 'new-company',
        'subscription_plan_id' => $plan->id, 'owner_user_id' => $owner->id, 'domain' => 'new-company.localhost']);
    expect($tenant->domains)->toHaveCount(1)
        ->and(TenantSubscription::query()->where('tenant_id', $tenant->id)->exists())->toBeTrue()
        ->and($owner->hasRole('tenant-admin', $tenant->id))->toBeTrue();
});
