<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Plugins\Domain\Enums\PluginInstallationStatus;
use Modules\Plugins\Infrastructure\Models\Plugin;
use Modules\Plugins\Infrastructure\Models\PluginInstallation;
use Modules\SaaS\Domain\Enums\SubscriptionStatus;
use Modules\SaaS\Infrastructure\Models\SubscriptionPlan;
use Modules\SaaS\Infrastructure\Models\TenantBrandingProfile;
use Modules\SaaS\Infrastructure\Models\TenantSubscription;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Tenancy\Infrastructure\Models\TenantDomain;

final class SaasPluginSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'Starter', 'slug' => 'starter', 'price_monthly_minor' => 2900, 'price_yearly_minor' => 29000,
                'features' => ['wallet' => true, 'commerce' => true, 'suppliers' => false, 'rules' => false, 'fraud' => false, 'analytics' => ['dashboard' => true, 'forecasting' => false, 'ai_insights' => false], 'plugins' => ['marketplace' => false], 'custom_domains' => false],
                'limits' => ['users' => 5, 'orders_monthly' => 500, 'products' => 100, 'api_requests_monthly' => 10000]],
            ['name' => 'Growth', 'slug' => 'growth', 'price_monthly_minor' => 9900, 'price_yearly_minor' => 99000,
                'features' => ['wallet' => true, 'commerce' => true, 'suppliers' => true, 'rules' => true, 'fraud' => true, 'analytics' => ['dashboard' => true, 'forecasting' => true, 'ai_insights' => true], 'plugins' => ['marketplace' => true], 'custom_domains' => true],
                'limits' => ['users' => 50, 'orders_monthly' => 10000, 'products' => 5000, 'api_requests_monthly' => 500000]],
            ['name' => 'Enterprise', 'slug' => 'enterprise', 'price_monthly_minor' => 0, 'price_yearly_minor' => 0,
                'features' => ['wallet' => true, 'commerce' => true, 'suppliers' => true, 'rules' => true, 'fraud' => true, 'analytics' => ['dashboard' => true, 'forecasting' => true, 'ai_insights' => true, 'supplier_recommendations' => true], 'plugins' => ['marketplace' => true], 'custom_domains' => true, 'white_label' => true, 'advanced_audit' => true],
                'limits' => ['users' => 100000, 'orders_monthly' => 10000000, 'products' => 1000000, 'api_requests_monthly' => 100000000]],
        ];
        foreach ($plans as $index => $definition) {
            SubscriptionPlan::query()->updateOrCreate(['slug' => $definition['slug']], array_merge([
                'status' => 'active', 'currency' => 'USD', 'trial_days' => $definition['slug'] === 'starter' ? 14 : 0,
                'is_public' => $definition['slug'] !== 'enterprise', 'sort_order' => ($index + 1) * 100,
            ], $definition));
        }

        $tenant = Tenant::query()->where('slug', 'araabi-global')->first();
        if ($tenant) {
            $enterprise = SubscriptionPlan::query()->where('slug', (string) config('saas.default_plan', 'enterprise'))->firstOrFail();
            if (! TenantSubscription::query()->where('tenant_id', $tenant->id)->whereIn('status', ['active', 'trialing'])->exists()) {
                TenantSubscription::query()->create(['tenant_id' => $tenant->id, 'subscription_plan_id' => $enterprise->id,
                    'status' => SubscriptionStatus::Active, 'billing_cycle' => 'monthly', 'started_at' => now(),
                    'current_period_start' => now(), 'current_period_end' => now()->addYears(10)]);
            }
            TenantBrandingProfile::query()->updateOrCreate(['tenant_id' => $tenant->id], [
                'display_name' => 'Araabi Global', 'legal_name' => 'Araabi Global Commerce Platform',
                'primary_color' => '#2563eb', 'secondary_color' => '#0f172a', 'support_email' => 'support@localhost.test', 'locale' => 'en',
            ]);
            TenantDomain::query()->where('tenant_id', $tenant->id)->where('verified', true)->update(['verification_status' => 'verified', 'ssl_status' => 'managed']);
        }

        $definitions = [
            ['slug' => 'sandbox-supplier', 'name' => 'Sandbox Supplier Adapter', 'version' => '1.0.0', 'category' => 'supplier', 'provider_type' => 'supplier', 'provider_key' => 'sandbox', 'is_core' => true,
                'description' => 'Built-in offline adapter for supplier routing verification.', 'capabilities' => ['health_check', 'submit', 'poll'], 'config_schema' => ['required' => [], 'properties' => []], 'requested_permissions' => ['supplier.orders.manage']],
            ['slug' => 'bkash-payment', 'name' => 'bKash Payment Gateway', 'version' => '1.0.0', 'category' => 'payment', 'provider_type' => 'payment', 'provider_key' => 'bkash', 'is_core' => false,
                'description' => 'Manifest-ready bKash payment provider. Live credentials and provider code are not bundled.', 'capabilities' => ['checkout', 'webhook', 'refund'], 'config_schema' => ['required' => ['app_key', 'app_secret'], 'properties' => ['app_key' => ['type' => 'string', 'secret' => true], 'app_secret' => ['type' => 'string', 'secret' => true]]], 'requested_permissions' => ['wallet.deposit.create']],
            ['slug' => 'stripe-payment', 'name' => 'Stripe Payment Gateway', 'version' => '1.0.0', 'category' => 'payment', 'provider_type' => 'payment', 'provider_key' => 'stripe', 'is_core' => false,
                'description' => 'Manifest-ready Stripe provider with encrypted tenant configuration.', 'capabilities' => ['checkout', 'webhook', 'refund'], 'config_schema' => ['required' => ['secret_key', 'webhook_secret'], 'properties' => ['secret_key' => ['type' => 'string', 'secret' => true], 'webhook_secret' => ['type' => 'string', 'secret' => true]]], 'requested_permissions' => ['wallet.deposit.create']],
            ['slug' => 'whatsapp-notifications', 'name' => 'WhatsApp Notifications', 'version' => '1.0.0', 'category' => 'notification', 'provider_type' => 'notification', 'provider_key' => 'whatsapp', 'is_core' => false,
                'description' => 'Manifest-ready notification provider for transactional messages.', 'capabilities' => ['transactional_messages'], 'config_schema' => ['required' => ['access_token'], 'properties' => ['access_token' => ['type' => 'string', 'secret' => true], 'phone_number_id' => ['type' => 'string']]], 'requested_permissions' => ['profile.read']],
        ];
        foreach ($definitions as $definition) {
            $manifest = ['slug' => $definition['slug'], 'version' => $definition['version'], 'provider_type' => $definition['provider_type'], 'provider_key' => $definition['provider_key'], 'capabilities' => $definition['capabilities']];
            $plugin = Plugin::query()->updateOrCreate(['slug' => $definition['slug']], array_merge($definition, [
                'status' => 'available', 'manifest' => $manifest, 'checksum' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR)),
            ]));
            if ($tenant && $plugin->is_core) {
                PluginInstallation::query()->updateOrCreate(['tenant_id' => $tenant->id, 'plugin_id' => $plugin->id], [
                    'status' => PluginInstallationStatus::Enabled, 'installed_version' => $plugin->version, 'enabled' => true,
                    'configuration' => [], 'installed_at' => now(), 'enabled_at' => now(),
                ]);
            }
        }
    }
}
