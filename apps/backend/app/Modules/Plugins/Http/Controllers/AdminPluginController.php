<?php

namespace App\Modules\Plugins\Http\Controllers;

use App\Modules\Plugins\Application\PluginRegistry;
use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminPluginController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => DB::table('plugin_manifests')
                ->leftJoin('tenant_plugins', function ($join) use ($tenant): void {
                    $join->on(
                        'tenant_plugins.plugin_manifest_id',
                        '=',
                        'plugin_manifests.id'
                    )->where('tenant_plugins.tenant_id', '=', $tenant->id());
                })
                ->orderBy('plugin_manifests.name')
                ->get([
                    'plugin_manifests.id',
                    'plugin_manifests.plugin_key',
                    'plugin_manifests.name',
                    'plugin_manifests.version',
                    'plugin_manifests.vendor',
                    'plugin_manifests.capabilities',
                    'plugin_manifests.required_entitlements',
                    'plugin_manifests.status as manifest_status',
                    'tenant_plugins.status as tenant_status',
                    'tenant_plugins.enabled_at',
                ]),
        ]);
    }

    public function registerManifest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plugin_key' => ['required', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:64'],
            'vendor' => ['required', 'string', 'max:128'],
            'capabilities' => ['nullable', 'array'],
            'required_entitlements' => ['nullable', 'array'],
        ]);

        abort_if(
            DB::table('plugin_manifests')
                ->where('plugin_key', $validated['plugin_key'])
                ->exists(),
            422,
            'Plugin key already exists.'
        );

        $id = DB::table('plugin_manifests')->insertGetId([
            'plugin_key' => $validated['plugin_key'],
            'name' => $validated['name'],
            'version' => $validated['version'],
            'vendor' => $validated['vendor'],
            'capabilities' => json_encode(
                $validated['capabilities'] ?? [],
                JSON_THROW_ON_ERROR
            ),
            'required_entitlements' => json_encode(
                $validated['required_entitlements'] ?? [],
                JSON_THROW_ON_ERROR
            ),
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => DB::table('plugin_manifests')->where('id', $id)->first(),
        ], 201);
    }

    public function enable(
        Request $request,
        int $manifestId,
        TenantContext $tenant,
        PluginRegistry $plugins,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'config' => ['nullable', 'array'],
        ]);

        $row = $plugins->enable(
            $tenant->id(),
            $manifestId,
            $validated['config'] ?? [],
        );

        $audit->record(
            $request,
            $tenant->id(),
            'plugins.plugin.enabled',
            'plugin_manifest',
            $manifestId,
        );

        return response()->json(['data' => $row]);
    }

    public function disable(
        Request $request,
        int $manifestId,
        TenantContext $tenant,
        PluginRegistry $plugins,
        AdminAuditService $audit,
    ): JsonResponse {
        $plugins->disable($tenant->id(), $manifestId);

        $audit->record(
            $request,
            $tenant->id(),
            'plugins.plugin.disabled',
            'plugin_manifest',
            $manifestId,
        );

        return response()->json(['data' => ['disabled' => true]]);
    }
}