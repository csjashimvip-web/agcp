<?php
namespace Modules\Rules\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Rules\Domain\Enums\RuleScope;
final class Rule extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['scope'=>RuleScope::class,'stop_on_match'=>'boolean','priority'=>'integer','published_version'=>'integer','metadata'=>'array']; }
    public function versions(): HasMany { return $this->hasMany(RuleVersion::class); }
    public function executions(): HasMany { return $this->hasMany(RuleExecution::class); }
    public function publishedVersion(): ?RuleVersion { return $this->published_version ? $this->versions()->where('version',$this->published_version)->first() : null; }
}
