<?php
namespace Modules\Reporting\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
final class InvoiceResource extends JsonResource
{
    public function toArray(Request $request):array{return ['id'=>$this->id,'number'=>$this->number,'order_id'=>$this->order_id,'order_number'=>$this->whenLoaded('order',fn()=>$this->order?->number),'status'=>$this->status->value,'currency'=>$this->currency,'subtotal_minor'=>(int)$this->subtotal_minor,'discount_minor'=>(int)$this->discount_minor,'surcharge_minor'=>(int)$this->surcharge_minor,'tax_minor'=>(int)$this->tax_minor,'total_minor'=>(int)$this->total_minor,'amount_paid_minor'=>(int)$this->amount_paid_minor,'amount_due_minor'=>(int)$this->amount_due_minor,'seller_snapshot'=>$this->seller_snapshot,'buyer_snapshot'=>$this->buyer_snapshot,'tax_snapshot'=>$this->tax_snapshot,'content_hash'=>$this->content_hash,'issued_at'=>$this->issued_at?->toAtomString(),'due_at'=>$this->due_at?->toAtomString(),'paid_at'=>$this->paid_at?->toAtomString(),'voided_at'=>$this->voided_at?->toAtomString(),'lines'=>$this->whenLoaded('lines',fn()=>$this->lines->map(fn($line)=>['id'=>$line->id,'sequence'=>$line->sequence,'description'=>$line->description,'sku'=>$line->sku,'item_type'=>$line->item_type,'quantity'=>$line->quantity,'unit_price_minor'=>$line->unit_price_minor,'net_minor'=>$line->net_minor,'tax_rate_basis_points'=>$line->tax_rate_basis_points,'tax_minor'=>$line->tax_minor,'gross_minor'=>$line->gross_minor,'tax_snapshot'=>$line->tax_snapshot]))];}
}
