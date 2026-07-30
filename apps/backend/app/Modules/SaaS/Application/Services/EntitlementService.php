<?php
namespace Modules\SaaS\Application\Services;

use Illuminate\Support\Arr;
use Modules\SaaS\Infrastructure\Models\TenantFeatureOverride;
use Modules\SaaS\Infrastructure\Models\TenantSubscription;

final class EntitlementService
{
    public function subscription(string $tenantId): ?TenantSubscription
    {
        return TenantSubscription::query()->with('plan')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($query): void {
                $query->whereNull('current_period_end')->orWhere('current_period_end', '>', now());
            })
            ->latest('started_at')->first();
    }

    public function features(string $tenantId): array
    {
        $features = $this->subscription($tenantId)?->plan?->features ?? [];
        $overrides = TenantFeatureOverride::query()->where('tenant_id', $tenantId)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get();
        foreach ($overrides as $override) {
            if ($override->enabled !== null) Arr::set($features, $override->feature_key, $override->enabled);
            elseif ($override->value !== null) Arr::set($features, $override->feature_key, $override->value);
        }
        return $features;
    }

    public function enabled(string $tenantId, string $feature, bool $default = false): bool
    {
        return (bool) Arr::get($this->features($tenantId), $feature, $default);
    }

    public function limit(string $tenantId, string $metric): ?int
    {
        $override = TenantFeatureOverride::query()->where('tenant_id', $tenantId)
            ->where('feature_key', 'limits.'.$metric)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first();
        if ($override?->value !== null) return (int) ($override->value['value'] ?? 0);
        $value = Arr::get($this->subscription($tenantId)?->plan?->limits ?? [], $metric);
        return $value === null ? null : (int) $value;
    }

    public function snapshot(string $tenantId): array
    {
        $subscription = $this->subscription($tenantId);
        return [
            'subscription' => $subscription ? [
                'id' => $subscription->id, 'status' => $subscription->status->value,
                'billing_cycle' => $subscription->billing_cycle,
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            ] : null,
            'plan' => $subscription?->plan ? [
                'id' => $subscription->plan->id, 'name' => $subscription->plan->name, 'slug' => $subscription->plan->slug,
            ] : null,
            'features' => $this->features($tenantId),
            'limits' => $subscription?->plan?->limits ?? [],
        ];
    }
}
