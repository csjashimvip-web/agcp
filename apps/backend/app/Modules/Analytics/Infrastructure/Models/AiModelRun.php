<?php
namespace Modules\Analytics\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Analytics\Domain\Enums\ModelRunStatus;
final class AiModelRun extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['status'=>ModelRunStatus::class,'input_window_start'=>'immutable_date','input_window_end'=>'immutable_date','started_at'=>'immutable_datetime','completed_at'=>'immutable_datetime','records_processed'=>'integer','metrics'=>'array']; }
}
