<?php
namespace Modules\Rules\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class RuleExecution extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['matched'=>'boolean','input_snapshot'=>'array','result_snapshot'=>'array','executed_at'=>'immutable_datetime']; }
    public function rule(): BelongsTo { return $this->belongsTo(Rule::class); }
    public function version(): BelongsTo { return $this->belongsTo(RuleVersion::class,'rule_version_id'); }
}
