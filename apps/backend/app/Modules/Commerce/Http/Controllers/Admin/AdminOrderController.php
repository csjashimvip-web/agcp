<?php
namespace Modules\Commerce\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Commerce\Application\Services\OrderService;
use Modules\Commerce\Domain\Enums\OrderStatus;
use Modules\Commerce\Http\Resources\OrderResource;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Tenancy\Application\TenantContext;
final class AdminOrderController extends Controller
{
    public function __construct(private readonly OrderService $orders){}
    public function index(Request $request,TenantContext $tenant)
    {
        $query=Order::query()->with(['user','items'])->where('tenant_id',$tenant->requireId());
        if($request->filled('status'))$query->where('status',$request->string('status'));
        return OrderResource::collection($query->latest()->paginate(30));
    }
    public function show(Order $order,TenantContext $tenant):OrderResource
    { abort_unless($order->tenant_id===$tenant->requireId(),404); return new OrderResource($order->load(['user','items','statusHistory'])); }
    public function transition(Request $request,Order $order,TenantContext $tenant):OrderResource
    {
        abort_unless($order->tenant_id===$tenant->requireId(),404);
        $data=$request->validate(['status'=>['required',Rule::enum(OrderStatus::class)],'note'=>['nullable','string','max:500']]);
        return new OrderResource($this->orders->transition($order,$request->user(),OrderStatus::from($data['status']),$data['note']??null));
    }
}
