<?php
namespace Modules\Reporting\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Reporting\Domain\Enums\ExportStatus;
final class DataExport extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts():array{return ['status'=>ExportStatus::class,'filters'=>'array','row_count'=>'integer','file_size'=>'integer','period_start'=>'immutable_datetime','period_end'=>'immutable_datetime','started_at'=>'immutable_datetime','completed_at'=>'immutable_datetime','expires_at'=>'immutable_datetime'];}
    public function requester():BelongsTo{return $this->belongsTo(User::class,'requested_by');}
}
