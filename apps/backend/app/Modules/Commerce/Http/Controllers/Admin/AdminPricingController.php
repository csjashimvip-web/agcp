<?php
namespace Modules\Commerce\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Commerce\Infrastructure\Models\CatalogPrice;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Commerce\Infrastructure\Models\PriceList;
use Modules\Tenancy\Application\TenantContext;
final class AdminPricingController extends Controller
{
    public function index(TenantContext $tenant)
    { return response()->json(['data'=>PriceList::query()->with('prices.variant.item')->where('tenant_id',$tenant->requireId())->orderBy('priority')->get()]); }
    public function upsert(Request $request,TenantContext $tenant)
    {
        $tenantId=$tenant->requireId();
        $data=$request->validate(['variant_id'=>['required','uuid'],'currency'=>['required','string','size:3'],'amount_minor'=>['required','integer','min:0'],'compare_at_minor'=>['nullable','integer','min:0'],'customer_segment'=>['nullable','string','max:80'],'min_quantity'=>['sometimes','integer','min:1']]);
        $variant=CatalogVariant::query()->whereKey($data['variant_id'])->whereHas('item',fn($q)=>$q->where('tenant_id',$tenantId))->firstOrFail();
        $currency=strtoupper($data['currency']); $segment=$data['customer_segment']??null;
        $slug=$segment?'segment-'.Str::slug($segment):'retail';
        $list=PriceList::query()->firstOrCreate(['tenant_id'=>$tenantId,'slug'=>$slug,'currency'=>$currency],[
            'name'=>$segment?ucwords($segment).' pricing':'Retail pricing','customer_segment'=>$segment,'priority'=>$segment?50:100,'status'=>'active',
        ]);
        $price=CatalogPrice::query()->updateOrCreate(['price_list_id'=>$list->id,'catalog_variant_id'=>$variant->id,'min_quantity'=>$data['min_quantity']??1],[
            'amount_minor'=>$data['amount_minor'],'compare_at_minor'=>$data['compare_at_minor']??null,
        ]);
        return response()->json(['data'=>$price->load('priceList','variant.item')]);
    }
}
