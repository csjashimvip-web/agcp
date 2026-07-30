<?php
namespace Modules\Suppliers\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Suppliers\Application\Services\SupplierHealthService;
use Modules\Suppliers\Application\Services\SupplierProviderRegistry;
use Modules\Suppliers\Domain\Enums\SupplierAccountStatus;
use Modules\Suppliers\Http\Resources\SupplierAccountResource;
use Modules\Suppliers\Infrastructure\Models\SupplierAccount;
use Modules\Tenancy\Application\TenantContext;

final class AdminSupplierController extends Controller
{
    public function __construct(
        private readonly SupplierProviderRegistry $providers,
        private readonly SupplierHealthService $health,
    ) {}

    public function index(TenantContext $tenant)
    {
        return SupplierAccountResource::collection(
            SupplierAccount::query()->with(['services.variant.item'])->where('tenant_id', $tenant->requireId())->orderBy('priority')->paginate(50),
        );
    }

    public function providers(): array
    {
        return ['data' => $this->providers->codes()];
    }

    public function store(Request $request, TenantContext $tenant): SupplierAccountResource
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'alpha_dash', 'max:100', Rule::unique('supplier_accounts', 'code')->where('tenant_id', $tenant->requireId())],
            'provider' => ['required', 'string', Rule::in($this->providers->codes())],
            'status' => ['nullable', Rule::enum(SupplierAccountStatus::class)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'max_retries' => ['nullable', 'integer', 'min:1', 'max:10'],
            'country_codes' => ['nullable', 'array'],
            'country_codes.*' => ['string', 'size:2'],
            'credentials' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);
        $supplier = SupplierAccount::query()->create(array_merge([
            'tenant_id' => $tenant->requireId(),
            'status' => SupplierAccountStatus::Active,
            'priority' => 100,
            'timeout_seconds' => 30,
            'max_retries' => 3,
            'health_status' => 'unknown',
            'health_score' => 100,
            'success_rate' => 100,
        ], $data));
        return new SupplierAccountResource($supplier);
    }

    public function update(Request $request, SupplierAccount $supplier, TenantContext $tenant): SupplierAccountResource
    {
        abort_unless($supplier->tenant_id === $tenant->requireId(), 404);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'provider' => ['sometimes', 'string', Rule::in($this->providers->codes())],
            'status' => ['sometimes', Rule::enum(SupplierAccountStatus::class)],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:300'],
            'max_retries' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'country_codes' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);
        $supplier->fill($data)->save();
        return new SupplierAccountResource($supplier->fresh('services'));
    }

    public function check(SupplierAccount $supplier, TenantContext $tenant): SupplierAccountResource
    {
        abort_unless($supplier->tenant_id === $tenant->requireId(), 404);
        return new SupplierAccountResource($this->health->check($supplier));
    }
}
