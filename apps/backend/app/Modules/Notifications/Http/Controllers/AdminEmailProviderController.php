<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class AdminEmailProviderController
{
    public function index(TenantContext $tenant): JsonResponse
    {
        return response()->json([
            'data' => [
                'providers' => DB::table('email_provider_configs')
                    ->where('tenant_id', $tenant->id())
                    ->orderByDesc('id')
                    ->get([
                        'id',
                        'name',
                        'driver',
                        'external_delivery_enabled',
                        'status',
                        'created_at',
                    ]),
                'attempts' => DB::table('email_delivery_attempts')
                    ->where('tenant_id', $tenant->id())
                    ->orderByDesc('id')
                    ->limit(200)
                    ->get([
                        'id',
                        'recipient',
                        'subject',
                        'status',
                        'last_error',
                        'delivered_at',
                        'created_at',
                    ]),
            ],
        ]);
    }

    public function store(
        Request $request,
        TenantContext $tenant,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'in:laravel_mail,null'],
            'config' => ['nullable', 'array'],
            'external_delivery_enabled' => ['nullable', 'boolean'],
        ]);

        $external = (bool) (
            $validated['external_delivery_enabled'] ?? false
        );

        if ($validated['driver'] === 'null') {
            $external = false;
        }

        $id = DB::table('email_provider_configs')->insertGetId([
            'tenant_id' => $tenant->id(),
            'name' => $validated['name'],
            'driver' => $validated['driver'],
            'encrypted_config' => empty($validated['config'])
                ? null
                : Crypt::encryptString(
                    json_encode(
                        $validated['config'],
                        JSON_THROW_ON_ERROR
                    )
                ),
            'external_delivery_enabled' => $external,
            'status' => 'enabled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => DB::table('email_provider_configs')
                ->where('id', $id)
                ->first([
                    'id',
                    'name',
                    'driver',
                    'external_delivery_enabled',
                    'status',
                ]),
        ], 201);
    }
}