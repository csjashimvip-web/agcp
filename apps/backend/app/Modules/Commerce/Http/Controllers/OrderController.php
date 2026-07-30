<?php
namespace Modules\Commerce\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Commerce\Application\Services\OrderService;
use Modules\Commerce\Http\Resources\OrderResource;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Tenancy\Application\TenantContext;
final class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders){}
    public function index(Request $request,TenantContext $tenant)
    { return OrderResource::collection(Order::query()->with('items.supplierOrder')->where(['tenant_id'=>$tenant->requireId(),'user_id'=>$request->user()->id])->latest()->paginate(20)); }
    public function show(Request $request,Order $order,TenantContext $tenant):OrderResource
    { abort_unless($order->tenant_id===$tenant->requireId()&&$order->user_id===$request->user()->id,404); return new OrderResource($order->load(['items.supplierOrder','statusHistory'])); }
    public function cancel(Request $request,Order $order,TenantContext $tenant):OrderResource
    { abort_unless($order->tenant_id===$tenant->requireId()&&$order->user_id===$request->user()->id,404); $data=$request->validate(['note'=>['sometimes','string','max:500']]); return new OrderResource($this->orders->cancel($order,$request->user(),$data['note']??'Customer canceled order.')); }
}
