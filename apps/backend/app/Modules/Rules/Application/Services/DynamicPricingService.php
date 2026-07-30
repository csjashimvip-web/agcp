<?php
namespace Modules\Rules\Application\Services;
use App\Models\User;
use Modules\Commerce\Application\Services\PricingService;
use Modules\Commerce\Infrastructure\Models\CatalogVariant;
use Modules\Rules\Application\ValueObjects\DynamicPriceQuote;
use Modules\Rules\Domain\Enums\RuleScope;
use Modules\Rules\Infrastructure\Models\PriceQuote;
final class DynamicPricingService
{
    public function __construct(private readonly PricingService $basePricing, private readonly RuleEngine $rules) {}
    public function quote(CatalogVariant $variant,string $tenantId,string $currency,int $quantity=1,?User $user=null,array $extraContext=[],bool $persist=false): DynamicPriceQuote
    {
        $base=$this->basePricing->resolve($variant,$tenantId,$currency,$quantity,$extraContext['segment'] ?? null);
        $context=array_replace_recursive([
            'pricing'=>['base_amount_minor'=>(int)$base->amount_minor,'currency'=>strtoupper($currency),'quantity'=>$quantity],
            'customer'=>['id'=>$user?->id,'status'=>$user?->status,'email_verified'=>$user?->email_verified_at!==null],
            'product'=>['variant_id'=>$variant->id,'sku'=>$variant->sku,'item_type'=>$variant->item?->type?->value],
            'time'=>['hour'=>(int)now()->format('G'),'weekday'=>(int)now()->format('N')],
        ],$extraContext);
        $evaluation=$this->rules->evaluate($tenantId,RuleScope::Pricing,$context,CatalogVariant::class,$variant->id);
        $baseMinor=(int)$base->amount_minor; $final=$baseMinor; $breakdown=[];
        foreach($evaluation->actions as $action) {
            $type=(string)($action['type'] ?? ''); $value=(float)($action['value'] ?? 0); $before=$final;
            $final=match($type) {
                'discount_percent' => max(0,$final-(int)round($final*min(max($value,0),100)/100)),
                'surcharge_percent' => max(0,$final+(int)round($final*max($value,0)/100)),
                'discount_fixed' => max(0,$final-(int)round($value)),
                'surcharge_fixed' => max(0,$final+(int)round($value)),
                'set_price' => max(0,(int)round($value)),
                default => $final,
            };
            if($before!==$final) $breakdown[]=['rule_id'=>$action['rule_id'],'rule_name'=>$action['rule_name'],'type'=>$type,'value'=>$value,'before_minor'=>$before,'after_minor'=>$final];
        }
        $quoteId=null; $expiresAt=null;
        if($persist) {
            $expires=now()->addSeconds((int)config('risk.quote_ttl_seconds',300));
            $row=PriceQuote::query()->create([
                'tenant_id'=>$tenantId,'user_id'=>$user?->id,'catalog_variant_id'=>$variant->id,'currency'=>strtoupper($currency),'quantity'=>$quantity,
                'base_amount_minor'=>$baseMinor,'adjustment_minor'=>$final-$baseMinor,'final_amount_minor'=>$final,
                'matched_rule_ids'=>$evaluation->matchedRuleIds,'breakdown'=>$breakdown,
                'context_hash'=>hash('sha256',json_encode($context,JSON_THROW_ON_ERROR)),'expires_at'=>$expires,
            ]);
            $quoteId=$row->id; $expiresAt=$expires->toIso8601String();
        }
        return new DynamicPriceQuote(strtoupper($currency),$quantity,$baseMinor,$final-$baseMinor,$final,$base->price_list_id,$evaluation->matchedRuleIds,$breakdown,$quoteId,$expiresAt);
    }
}
