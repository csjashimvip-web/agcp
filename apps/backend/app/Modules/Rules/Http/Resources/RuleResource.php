<?php
namespace Modules\Rules\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
final class RuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latest = $this->relationLoaded('versions') ? $this->versions->sortByDesc('version')->first() : null;
        return [
            'id'=>$this->id,'name'=>$this->name,'slug'=>$this->slug,'scope'=>$this->scope->value,
            'status'=>$this->status,'priority'=>(int)$this->priority,'stop_on_match'=>(bool)$this->stop_on_match,
            'published_version'=>$this->published_version,'latest_version'=>$latest?->version,
            'condition_mode'=>$latest?->condition_mode,'conditions'=>$latest?->conditions,'actions'=>$latest?->actions,
            'created_at'=>$this->created_at?->toIso8601String(),
        ];
    }
}
