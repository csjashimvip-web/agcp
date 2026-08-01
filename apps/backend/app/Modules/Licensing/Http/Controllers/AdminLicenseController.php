<?php

namespace App\Modules\Licensing\Http\Controllers;

use App\Modules\Licensing\Application\EntitlementService;
use App\Modules\Licensing\Application\LicenseService;
use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminLicenseController
{
    public function index(
        TenantContext $tenant,
        EntitlementService $entitlements,
    ): JsonResponse {
        return response()->json([
            'data' => [
                'entitlements' => $entitlements->resolveForTenant($tenant->id()),
                'licenses' => DB::table('license_keys')
                    ->where('tenant_id', $tenant->id())
                    ->orderByDesc('id')
                    ->get([
                        'id',
                        'license_uuid',
                        'public_id',
                        'edition',
                        'status',
                        'bound_domain',
                        'bound_server_fingerprint',
                        'issued_at',
                        'expires_at',
                        'last_checked_at',
                    ]),
            ],
        ]);
    }

    public function issue(
        Request $request,
        TenantContext $tenant,
        LicenseService $licenses,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'edition' => ['required', 'string', 'max:64'],
            'bound_domain' => ['nullable', 'string', 'max:255'],
            'bound_server_fingerprint' => ['nullable', 'string', 'max:128'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $issued = $licenses->issue(
            tenantId: $tenant->id(),
            edition: $validated['edition'],
            domain: $validated['bound_domain'] ?? null,
            serverFingerprint: $validated['bound_server_fingerprint'] ?? null,
            expiresAt: $validated['expires_at'] ?? null,
        );

        $audit->record(
            $request,
            $tenant->id(),
            'licensing.license.issued',
            'license_key',
            $issued['record']->id,
            ['public_id' => $issued['record']->public_id],
        );

        return response()->json([
            'data' => [
                'license' => [
                    'id' => $issued['record']->id,
                    'public_id' => $issued['record']->public_id,
                    'edition' => $issued['record']->edition,
                ],
                'token' => $issued['token'],
                'warning' => 'This license token is shown only once.',
            ],
        ], 201);
    }

    public function revoke(
        Request $request,
        int $licenseId,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $license = DB::table('license_keys')
            ->where('tenant_id', $tenant->id())
            ->where('id', $licenseId)
            ->first();

        abort_unless($license, 404);

        DB::table('license_keys')
            ->where('id', $licenseId)
            ->update([
                'status' => 'revoked',
                'updated_at' => now(),
            ]);

        $audit->record(
            $request,
            $tenant->id(),
            'licensing.license.revoked',
            'license_key',
            $licenseId,
        );

        return response()->json(['data' => ['revoked' => true]]);
    }
}