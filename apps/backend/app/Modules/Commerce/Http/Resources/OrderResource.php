<?php
namespace Modules\Commerce\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'number'=>$this->number,'status'=>$this->status->value,'payment_status'=>$this->payment_status,'fulfillment_status'=>$this->fulfillment_status,
            'currency'=>$this->currency,'subtotal_minor'=>(int)$this->subtotal_minor,'discount_minor'=>(int)$this->discount_minor,'total_minor'=>(int)$this->total_minor,
            'total'=>number_format($this->total_minor/100,2,'.',''),'placed_at'=>$this->placed_at?->toIso8601String(),'canceled_at'=>$this->canceled_at?->toIso8601String(),
            'user'=>$this->whenLoaded('user', fn()=>['id'=>$this->user->id,'name'=>$this->user->name,'email'=>$this->user->email]),
            'items'=>$this->whenLoaded('items', fn()=> $this->items->map(fn($item)=>[
                'id'=>$item->id,'item_name'=>$item->item_name,'variant_name'=>$item->variant_name,'sku'=>$item->sku,'item_type'=>$item->item_type,
                'quantity'=>(int)$item->quantity,'unit_price_minor'=>(int)$item->unit_price_minor,'total_minor'=>(int)$item->total_minor,'status'=>$item->status,'configuration'=>$item->configuration,
            ])),
            'history'=>$this->whenLoaded('statusHistory', fn()=> $this->statusHistory->map(fn($row)=>['from'=>$row->from_status,'to'=>$row->to_status,'note'=>$row->note,'created_at'=>$row->created_at?->toIso8601String()])),
        ];
    }
}
