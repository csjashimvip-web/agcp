<?php
namespace Modules\Rules\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Rules\Application\Services\DynamicPricingService;
use Modules\Tenancy\Application\TenantContext;
final class PricingQuoteController extends Controller
{
    public function __invoke(Request $request,TenantContext $tenant,DynamicPricingService $pricing)
    {
        $data=$request->validate(['variant_id'=>['required','uuid'],'currency'=>['required','string','size:3'],'quantity'=>['nullable','integer','min:1','max:1000']]);
        $variant=CatalogVariant::query()->with('item')->whereKey($data['variant_id'])->whereHas('item',fn($q)=>$q->where('tenant_id',$tenant->requireId())->where('status','active'))->firstOrFail();
        $quote=$pricing->quote($variant,$tenant->requireId(),$data['currency'],(int)($data['quantity'] ?? 1),$request->user(),[],true);
        return response()->json(['data'=>[
            'quote_id'=>$quote->quoteId,'variant_id'=>$variant->id,'currency'=>$quote->currency,'quantity'=>$quote->quantity,
            'base_amount_minor'=>$quote->baseAmountMinor,'adjustment_minor'=>$quote->adjustmentMinor,'final_amount_minor'=>$quote->finalAmountMinor,
            'matched_rule_ids'=>$quote->matchedRuleIds,'breakdown'=>$quote->breakdown,'expires_at'=>$quote->expiresAt,
        ]]);
    }
}
