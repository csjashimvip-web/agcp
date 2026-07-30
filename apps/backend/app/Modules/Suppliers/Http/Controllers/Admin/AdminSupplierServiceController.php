<?php
namespace Modules\Suppliers\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Suppliers\Http\Resources\SupplierAccountResource;
use Modules\Suppliers\Infrastructure\Models\SupplierAccount;
use Modules\Suppliers\Infrastructure\Models\SupplierService;
use Modules\Tenancy\Application\TenantContext;

final class AdminSupplierServiceController extends Controller
{
    public function store(Request $request, SupplierAccount $supplier, TenantContext $tenant): SupplierAccountResource
    {
        $tenantId = $tenant->requireId();
        abort_unless($supplier->tenant_id === $tenantId, 404);
        $data = $request->validate([
            'catalog_variant_id' => ['required', 'uuid', Rule::exists('catalog_variants', 'id')],
            'supplier_service_code' => ['required', 'string', 'max:160'],
            'cost_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'estimated_seconds' => ['nullable', 'integer', 'min:1', 'max:604800'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'enabled' => ['nullable', 'boolean'],
            'max_retries' => ['nullable', 'integer', 'min:1', 'max:10'],
            'field_map' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);
        $variant = CatalogVariant::query()->whereKey($data['catalog_variant_id'])->whereHas('item', fn ($query) => $query->where('tenant_id', $tenantId))->firstOrFail();
        SupplierService::query()->updateOrCreate(
            ['supplier_account_id' => $supplier->id, 'catalog_variant_id' => $variant->id],
            array_merge([
                'tenant_id' => $tenantId,
                'estimated_seconds' => 60,
                'priority' => 100,
                'enabled' => true,
            ], $data, ['currency' => strtoupper($data['currency'])]),
        );
        return new SupplierAccountResource($supplier->fresh(['services.variant.item']));
    }

    public function update(Request $request, SupplierService $service, TenantContext $tenant): SupplierAccountResource
    {
        abort_unless($service->tenant_id === $tenant->requireId(), 404);
        $data = $request->validate([
            'supplier_service_code' => ['sometimes', 'string', 'max:160'],
            'cost_minor' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'estimated_seconds' => ['sometimes', 'integer', 'min:1', 'max:604800'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'enabled' => ['sometimes', 'boolean'],
            'max_retries' => ['nullable', 'integer', 'min:1', 'max:10'],
            'field_map' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);
        if (isset($data['currency'])) $data['currency'] = strtoupper($data['currency']);
        $service->fill($data)->save();
        return new SupplierAccountResource($service->supplier->load(['services.variant.item']));
    }
}
