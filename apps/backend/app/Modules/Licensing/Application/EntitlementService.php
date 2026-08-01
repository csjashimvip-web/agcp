<?php

namespace App\Modules\Licensing\Application;

use Illuminate\Support\Facades\DB;

final class EntitlementService
{
    /**
     * @return array<string,mixed>
     */
    public function resolveForTenant(int $tenantId): array
    {
        $definitions = DB::table('entitlement_definitions')
            ->orderBy('key')
            ->get();

        $subscription = DB::table('tenant_subscriptions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('id')
            ->first();

        $planValues = collect();

        if ($subscription?->saas_plan_id) {
            $planValues = DB::table('plan_entitlements')
                ->where('saas_plan_id', $subscription->saas_plan_id)
                ->pluck('value', 'entitlement_definition_id');
        }

        $overrides = DB::table('tenant_entitlements')
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->get()
            ->keyBy('entitlement_definition_id');

        $resolved = [];

        foreach ($definitions as $definition) {
            $raw = $definition->default_value;

            if ($planValues->has($definition->id)) {
                $raw = $planValues->get($definition->id);
            }

            if ($overrides->has($definition->id)) {
                $raw = $overrides->get($definition->id)->value;
            }

            $resolved[$definition->key] = $this->decodeValue($raw);
        }

        return $resolved;
    }

    public function allows(
        int $tenantId,
        string $key,
        mixed $expected = true,
    ): bool {
        $all = $this->resolveForTenant($tenantId);

        return array_key_exists($key, $all)
            && $all[$key] === $expected;
    }

    private function decodeValue(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        if (is_array($raw) || is_bool($raw) || is_int($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return json_last_error() === JSON_ERROR_NONE
            ? $decoded
            : $raw;
    }
}