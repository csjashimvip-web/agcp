<?php
namespace Modules\Commerce\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Commerce\Application\Services\CheckoutService;
use Modules\Commerce\Http\Resources\OrderResource;
use Modules\Tenancy\Application\TenantContext;
final class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout){}
    public function store(Request $request,TenantContext $tenant):OrderResource
    {
        $data=$request->validate(['cart_id'=>['required','uuid'],'wallet_id'=>['required','uuid']]);
        return new OrderResource($this->checkout->checkout($request->user(),$tenant->requireId(),$data['cart_id'],$data['wallet_id'],$request->header('Idempotency-Key')));
    }
}
