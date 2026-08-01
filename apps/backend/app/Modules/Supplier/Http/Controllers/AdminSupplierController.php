<?php

namespace App\Modules\Supplier\Http\Controllers;

use App\Modules\Reliability\Application\AdminAuditService;
use App\Modules\Supplier\Application\Contracts\SupplierProviderFactory;
use App\Modules\Supplier\Domain\Models\Supplier;
use App\Modules\Tenancy\Application\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

final class AdminSupplierController
{
    public function store(
        Request $request,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('suppliers', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenant->id())),
            ],
            'driver' => ['required', Rule::in(['dhru-fusion'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'priority' => ['required', 'integer', 'min:1', 'max:10000'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'max_retries' => ['required', 'integer', 'min:0', 'max:10'],
            'base_url' => ['required', 'url', 'max:2048'],
            'username' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:2048'],
        ]);

        $secretPayload = Crypt::encryptString(json_encode([
            'base_url' => rtrim($validated['base_url'], '/'),
            'username' => $validated['username'],
            'api_key' => $validated['api_key'],
        ], JSON_THROW_ON_ERROR));

        $supplier = Supplier::query()->create([
            'tenant_id' => $tenant->id(),
            'name' => $validated['name'],
            'code' => $validated['code'],
            'driver' => $validated['driver'],
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'timeout_seconds' => $validated['timeout_seconds'],
            'max_retries' => $validated['max_retries'],
            'secret_payload' => $secretPayload,
            'settings' => [
                'base_url_display' => parse_url($validated['base_url'], PHP_URL_HOST),
            ],
        ]);

        $audit->record(
            $request,
            $tenant->id(),
            'supplier.created',
            'supplier',
            $supplier->id,
            [
                'name' => $supplier->name,
                'code' => $supplier->code,
                'driver' => $supplier->driver,
            ],
        );

        return response()->json([
            'data' => $this->safeSupplier($supplier),
        ], 201);
    }

    public function update(
        Request $request,
        int $supplierId,
        TenantContext $tenant,
        AdminAuditService $audit,
    ): JsonResponse {
        $supplier = Supplier::query()
            ->where('tenant_id', $tenant->id())
            ->findOrFail($supplierId);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'priority' => ['sometimes', 'required', 'integer', 'min:1', 'max:10000'],
            'timeout_seconds' => ['sometimes', 'required', 'integer', 'min:5', 'max:300'],
            'max_retries' => ['sometimes', 'required', 'integer', 'min:0', 'max:10'],
            'base_url' => ['sometimes', 'required', 'url', 'max:2048'],
            'username' => ['sometimes', 'required', 'string', 'max:255'],
            'api_key' => ['sometimes', 'required', 'string', 'max:2048'],
        ]);

        $public = collect($validated)
            ->only(['name', 'status', 'priority', 'timeout_seconds', 'max_retries'])
            ->all();

        if ($public !== []) {
            $supplier->fill($public);
        }

        if (isset($validated['base_url'], $validated['username'], $validated['api_key'])) {
            $supplier->secret_payload = Crypt::encryptString(json_encode([
                'base_url' => rtrim($validated['base_url'], '/'),
                'username' => $validated['username'],
                'api_key' => $validated['api_key'],
            ], JSON_THROW_ON_ERROR));

            $supplier->settings = array_merge(
                $supplier->settings ?? [],
                ['base_url_display' => parse_url($validated['base_url'], PHP_URL_HOST)]
            );
        }

        $supplier->save();

        $audit->record(
            $request,
            $tenant->id(),
            'supplier.updated',
            'supplier',
            $supplier->id,
            ['fields' => array_keys($validated)],
        );

        return response()->json([
            'data' => $this->safeSupplier($supplier->fresh()),
        ]);
    }

    public function testConnection(
        Request $request,
        int $supplierId,
        TenantContext $tenant,
        SupplierProviderFactory $providers,
        AdminAuditService $audit,
    ): JsonResponse {
        $supplier = Supplier::query()
            ->where('tenant_id', $tenant->id())
            ->findOrFail($supplierId);

        $provider = $providers->make($supplier);

        $balance = $provider->balance();
        $services = $provider->services();

        $supplier->forceFill(['last_healthcheck_at' => now()])->save();

        $audit->record(
            $request,
            $tenant->id(),
            'supplier.connection_tested',
            'supplier',
            $supplier->id,
            ['success' => true],
        );

        return response()->json([
            'data' => [
                'connected' => true,
                'balance' => $balance,
                'service_payload_received' => $services !== [],
                'last_healthcheck_at' => $supplier->last_healthcheck_at,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function safeSupplier(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'code' => $supplier->code,
            'driver' => $supplier->driver,
            'status' => $supplier->status,
            'priority' => $supplier->priority,
            'timeout_seconds' => $supplier->timeout_seconds,
            'max_retries' => $supplier->max_retries,
            'credentials_configured' => (bool) $supplier->secret_payload,
            'settings' => $supplier->settings,
            'last_healthcheck_at' => $supplier->last_healthcheck_at,
        ];
    }
}