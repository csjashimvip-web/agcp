<?php
namespace Modules\Commerce\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
final class CatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'name'=>$this->name,'slug'=>$this->slug,'sku'=>$this->sku,
            'type'=>$this->type->value,'summary'=>$this->summary,'description'=>$this->description,
            'status'=>$this->status,'fulfillment_mode'=>$this->fulfillment_mode,
            'inventory_tracking'=>(bool)$this->inventory_tracking,'allow_backorder'=>(bool)$this->allow_backorder,
            'service_schema'=>$this->service_schema,'category'=>$this->whenLoaded('category', fn()=> $this->category ? ['id'=>$this->category->id,'name'=>$this->category->name,'slug'=>$this->category->slug] : null),
            'variants'=>$this->whenLoaded('variants', fn()=> $this->variants->map(function($variant){
                $price=$variant->prices->filter(fn($p)=>$p->priceList?->customer_segment===null && (int)$p->min_quantity===1)->sortBy(fn($p)=>$p->priceList?->priority ?? 999)->first();
                $available=(int)$variant->inventoryLevels->sum(fn($level)=>$level->available());
                return [
                    'id'=>$variant->id,'name'=>$variant->name,'sku'=>$variant->sku,'attributes'=>$variant->attributes,'status'=>$variant->status,'is_default'=>(bool)$variant->is_default,
                    'price'=>$price ? ['amount_minor'=>(int)$price->amount_minor,'amount'=>number_format($price->amount_minor/100,2,'.',''),'currency'=>$price->priceList?->currency,'compare_at_minor'=>$price->compare_at_minor] : null,
                    'available_quantity'=>$available,
                ];
            })),
            'published_at'=>$this->published_at?->toIso8601String(),
        ];
    }
}
