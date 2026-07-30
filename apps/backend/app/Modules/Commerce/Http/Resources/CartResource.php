<?php
namespace Modules\Commerce\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
final class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subtotal=(int)$this->items->sum(fn($line)=>$line->unit_price_minor*$line->quantity);
        return [
            'id'=>$this->id,'currency'=>$this->currency,'status'=>$this->status,'subtotal_minor'=>$subtotal,'subtotal'=>number_format($subtotal/100,2,'.',''),
            'items'=>$this->items->map(fn($line)=>[
                'id'=>$line->id,'quantity'=>(int)$line->quantity,'unit_price_minor'=>(int)$line->unit_price_minor,
                'unit_price'=>number_format($line->unit_price_minor/100,2,'.',''),'total_minor'=>(int)$line->unit_price_minor*(int)$line->quantity,
                'configuration'=>$line->configuration,'variant'=>[
                    'id'=>$line->variant->id,'name'=>$line->variant->name,'sku'=>$line->variant->sku,
                    'item'=>['id'=>$line->variant->item->id,'name'=>$line->variant->item->name,'slug'=>$line->variant->item->slug,'type'=>$line->variant->item->type->value],
                ],
            ]),'expires_at'=>$this->expires_at?->toIso8601String(),
        ];
    }
}
