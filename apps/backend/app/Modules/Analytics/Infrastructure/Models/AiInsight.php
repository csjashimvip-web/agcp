<?php
namespace Modules\Analytics\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Analytics\Domain\Enums\InsightSeverity;
use Modules\Analytics\Domain\Enums\InsightType;
final class AiInsight extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['type'=>InsightType::class,'severity'=>InsightSeverity::class,'recommendations'=>'array','evidence'=>'array','generated_at'=>'immutable_datetime','expires_at'=>'immutable_datetime']; }
}
