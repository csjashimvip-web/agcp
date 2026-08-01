<?php

namespace App\Modules\Plugins\Application;

use App\Modules\Licensing\Application\EntitlementService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PluginRegistry
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {
    }

    /**
     * @param array<string,mixed> $config
     */
    public function enable(
        int $tenantId,
        int $manifestId,
        array $config = [],
    ): object {
        $manifest = DB::table('plugin_manifests')
            ->where('id', $manifestId)
            ->where('status', 'approved')
            ->first();

        if (! $manifest) {
            throw new RuntimeException('Approved plugin manifest was not found.');
        }

        $required = json_decode(
            (string) ($manifest->required_entitlements ?? '[]'),
            true
        );

        if (! is_array($required)) {
            $required = [];
        }

        $resolved = $this->entitlements->resolveForTenant($tenantId);

        foreach ($required as $key) {
            if (($resolved[$key] ?? false) !== true) {
                throw new RuntimeException(
                    "Required entitlement is missing: {$key}"
                );
            }
        }

        DB::table('tenant_plugins')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'plugin_manifest_id' => $manifestId,
            ],
            [
                'status' => 'enabled',
                'encrypted_config' => $config === []
                    ? null
                    : Crypt::encryptString(
                        json_encode($config, JSON_THROW_ON_ERROR)
                    ),
                'enabled_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return DB::table('tenant_plugins')
            ->where('tenant_id', $tenantId)
            ->where('plugin_manifest_id', $manifestId)
            ->first();
    }

    public function disable(int $tenantId, int $manifestId): void
    {
        DB::table('tenant_plugins')
            ->where('tenant_id', $tenantId)
            ->where('plugin_manifest_id', $manifestId)
            ->update([
                'status' => 'disabled',
                'updated_at' => now(),
            ]);
    }
}