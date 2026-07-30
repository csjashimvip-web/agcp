<?php
namespace Modules\Commerce\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Commerce\Application\Services\CartService;
use Modules\Commerce\Http\Resources\CartResource;
use Modules\Commerce\Infrastructure\Models\CartItem;
use Modules\Tenancy\Application\TenantContext;
final class CartController extends Controller
{
    public function __construct(private readonly CartService $carts){}
    public function show(Request $request,TenantContext $tenant):CartResource
    { return new CartResource($this->carts->current($request->user(),$tenant->requireId(),strtoupper((string)$request->string('currency','USD')))); }
    public function store(Request $request,TenantContext $tenant):CartResource
    {
        $data=$request->validate(['variant_id'=>['required','uuid'],'quantity'=>['required','integer','min:1','max:1000'],'configuration'=>['sometimes','array'],'currency'=>['sometimes','string','size:3']]);
        return new CartResource($this->carts->add($request->user(),$tenant->requireId(),$data['variant_id'],$data['quantity'],$data['configuration']??[],strtoupper($data['currency']??'USD')));
    }
    public function update(Request $request,CartItem $cartItem,TenantContext $tenant):CartResource
    { $data=$request->validate(['quantity'=>['required','integer','min:0','max:1000']]); return new CartResource($this->carts->update($request->user(),$tenant->requireId(),$cartItem,$data['quantity'])); }
    public function destroy(Request $request,CartItem $cartItem,TenantContext $tenant):CartResource
    { return new CartResource($this->carts->update($request->user(),$tenant->requireId(),$cartItem,0)); }
}
