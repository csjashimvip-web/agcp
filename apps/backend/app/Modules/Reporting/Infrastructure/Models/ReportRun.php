<?php
namespace Modules\Reporting\Infrastructure\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class ReportRun extends Model
{
    use HasUuids;
    protected $guarded=[];
    protected function casts():array{return ['metrics'=>'array','period_start'=>'immutable_datetime','period_end'=>'immutable_datetime','started_at'=>'immutable_datetime','completed_at'=>'immutable_datetime'];}
    public function schedule():BelongsTo{return $this->belongsTo(ReportSchedule::class,'report_schedule_id');}
    public function export():BelongsTo{return $this->belongsTo(DataExport::class,'data_export_id');}
}
