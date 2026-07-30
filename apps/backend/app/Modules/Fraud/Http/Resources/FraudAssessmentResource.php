<?php
namespace Modules\Fraud\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
final class FraudAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'user_id'=>$this->user_id,'order_id'=>$this->order_id,
            'subject_type'=>$this->subject_type,'subject_id'=>$this->subject_id,
            'score'=>(int)$this->score,'level'=>$this->level->value,'decision'=>$this->decision->value,
            'status'=>$this->status,'context'=>$this->context,'reviewed_at'=>$this->reviewed_at?->toIso8601String(),
            'review_note'=>$this->review_note,
            'user'=>$this->when($this->relationLoaded('user'), fn()=> $this->user ? ['id'=>$this->user->id,'name'=>$this->user->name,'email'=>$this->user->email] : null),
            'order'=>$this->when($this->relationLoaded('order'), fn()=> $this->order ? ['id'=>$this->order->id,'number'=>$this->order->number,'total_minor'=>(int)$this->order->total_minor,'currency'=>$this->order->currency,'fulfillment_status'=>$this->order->fulfillment_status] : null),
            'signals'=>$this->when($this->relationLoaded('signals'), fn()=> $this->signals->map(fn($signal)=>['id'=>$signal->id,'code'=>$signal->code,'score'=>(int)$signal->score,'severity'=>$signal->severity,'message'=>$signal->message,'evidence'=>$signal->evidence])),
            'created_at'=>$this->created_at?->toIso8601String(),
        ];
    }
}
