<?php
namespace Modules\Suppliers\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Suppliers\Application\Services\SupplierFulfillmentService;
use Modules\Suppliers\Http\Resources\SupplierOrderResource;
use Modules\Suppliers\Infrastructure\Models\SupplierOrder;
use Modules\Tenancy\Application\TenantContext;

final class AdminSupplierOrderController extends Controller
{
    public function __construct(private readonly SupplierFulfillmentService $fulfillment) {}

    public function index(Request $request, TenantContext $tenant)
    {
        $query = SupplierOrder::query()->with(['supplier', 'service', 'order', 'orderItem'])->where('tenant_id', $tenant->requireId());
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        return SupplierOrderResource::collection($query->latest()->paginate(50));
    }

    public function show(SupplierOrder $supplierOrder, TenantContext $tenant): SupplierOrderResource
    {
        abort_unless($supplierOrder->tenant_id === $tenant->requireId(), 404);
        return new SupplierOrderResource($supplierOrder->load(['supplier', 'service', 'order', 'orderItem', 'attemptLogs', 'decisions']));
    }

    public function retry(SupplierOrder $supplierOrder, TenantContext $tenant): SupplierOrderResource
    {
        abort_unless($supplierOrder->tenant_id === $tenant->requireId(), 404);
        return new SupplierOrderResource($this->fulfillment->retry($supplierOrder));
    }
}
