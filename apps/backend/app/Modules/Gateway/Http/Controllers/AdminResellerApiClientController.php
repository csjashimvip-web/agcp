<?php

namespace App\Modules\Gateway\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

final class AdminResellerApiClientController
{
    private const ALLOWED_ABILITIES = [
        'services:read',
        'wallet:read',
        'orders:create',
        'orders:read',
    ];

    public function index(TenantContext $tenant): JsonResponse
    {
        $rows = DB::table('reseller_api_clients')
            ->join('users', 'users.id', '=', 'reseller_api_clients.user_id')
            ->where('reseller_api_clients.tenant_id', $tenant->id())
            ->orderByDesc('reseller_api_clients.id')
            ->get([
                'reseller_api_clients.id',
                'reseller_api_clients.public_id',
                'reseller_api_clients.name',
                'reseller_api_clients.abilities',
                'reseller_api_clients.status',
                'reseller_api_clients.rate_limit_per_minute',
                'reseller_api_clients.last_used_at',
                'reseller_api_clients.revoked_at',
                'reseller_api_clients.created_at',
                'users.name as user_name',
                'users.email as user_email',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function store(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_email' => ['required', 'email'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [
                'required',
                'string',
                Rule::in(self::ALLOWED_ABILITIES),
            ],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:3000'],
        ]);

        $user = DB::table('users')
            ->where('email', $validated['user_email'])
            ->first();

        if (! $user) {
            throw new RuntimeException('User was not found.');
        }

        $hasMembership = DB::table('tenant_memberships')
            ->where('tenant_id', $tenant->id())
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $hasMembership) {
            throw new RuntimeException(
                'The API user must have an active membership in this tenant.'
            );
        }

        $publicId = (string) Str::ulid();
        $secret = Str::random(64);

        $clientId = DB::table('reseller_api_clients')->insertGetId([
            'tenant_id' => $tenant->id(),
            'user_id' => $user->id,
            'public_id' => $publicId,
            'name' => $validated['name'],
            'secret_hash' => hash('sha256', $secret),
            'abilities' => json_encode(
                array_values(array_unique($validated['abilities'])),
                JSON_THROW_ON_ERROR
            ),
            'status' => 'active',
            'rate_limit_per_minute' => $validated['rate_limit_per_minute'] ?? 120,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'gateway.reseller_api_client.created',
            'reseller_api_client',
            $clientId,
            [
                'public_id' => $publicId,
                'user_email' => $validated['user_email'],
                'abilities' => $validated['abilities'],
            ],
        );

        return response()->json([
            'data' => [
                'id' => $clientId,
                'public_id' => $publicId,
                'name' => $validated['name'],
                'token' => 'agcp_'.$publicId.'.'.$secret,
                'abilities' => $validated['abilities'],
                'warning' => 'This token is shown only once. Store it securely.',
            ],
        ], 201);
    }

    public function revoke(
        Request $request,
        int $clientId,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $client = DB::table('reseller_api_clients')
            ->where('tenant_id', $tenant->id())
            ->where('id', $clientId)
            ->first();

        abort_unless($client, 404);

        DB::table('reseller_api_clients')
            ->where('id', $clientId)
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        $audit->record(
            $request,
            $tenant->id(),
            'gateway.reseller_api_client.revoked',
            'reseller_api_client',
            $clientId,
        );

        return response()->json([
            'data' => [
                'revoked' => true,
                'client_id' => $clientId,
            ],
        ]);
    }
}