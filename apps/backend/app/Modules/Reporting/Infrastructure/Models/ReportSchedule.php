<?php
namespace Modules\Reporting\Infrastructure\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
final class ReportSchedule extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts():array{return ['recipients'=>'array','filters'=>'array','enabled'=>'boolean','next_run_at'=>'immutable_datetime','last_run_at'=>'immutable_datetime'];}
    public function creator():BelongsTo{return $this->belongsTo(User::class,'created_by');}
    public function runs():HasMany{return $this->hasMany(ReportRun::class);}
}
