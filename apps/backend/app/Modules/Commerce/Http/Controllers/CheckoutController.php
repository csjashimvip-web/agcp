<?php
namespace Modules\Commerce\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Commerce\Application\Services\CheckoutService;
use Modules\Commerce\Http\Resources\OrderResource;
use Modules\Tenancy\Application\TenantContext;
final class CheckoutController extends Controller
{
    public function store(Request $request,TenantContext $tenant,CheckoutService $checkout): OrderResource
    {
        $data=$request->validate(['cart_id'=>['required','uuid'],'wallet_id'=>['required','uuid']]);
        $order=$checkout->checkout($request->user(),$tenant->requireId(),$data['cart_id'],$data['wallet_id'],$request->header('Idempotency-Key'),['ip'=>$request->ip(),'user_agent'=>$request->userAgent()]);
        return new OrderResource($order);
    }
}
