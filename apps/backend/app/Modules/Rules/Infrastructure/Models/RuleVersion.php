<?php
namespace Modules\Rules\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class RuleVersion extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['version'=>'integer','conditions'=>'array','actions'=>'array','published_at'=>'immutable_datetime']; }
    public function rule(): BelongsTo { return $this->belongsTo(Rule::class); }
}
