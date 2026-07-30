<?php
namespace Modules\Reporting\Application\Services;
use App\Models\User;
use Carbon\CarbonImmutable;
use Modules\Reporting\Infrastructure\Models\ReportRun;
use Modules\Reporting\Infrastructure\Models\ReportSchedule;
use Throwable;
final class ReportScheduleService
{
    public function __construct(private readonly DataExportService $exports,private readonly ReportingDashboardService $dashboard){}
    public function run(ReportSchedule $schedule,?User $actor=null):ReportRun
    {
        $now=CarbonImmutable::now($schedule->timezone?:'UTC');[$start,$end]=$this->period($schedule->frequency,$now);
        $run=ReportRun::query()->create(['tenant_id'=>$schedule->tenant_id,'report_schedule_id'=>$schedule->id,'report_type'=>$schedule->report_type,'status'=>'running','period_start'=>$start->utc(),'period_end'=>$end->utc(),'started_at'=>now()]);
        try{$export=$this->exports->create($schedule->tenant_id,$schedule->report_type,$actor,$start->utc()->toAtomString(),$end->utc()->toAtomString(),$schedule->filters??[]);$metrics=$this->dashboard->build($schedule->tenant_id,$start->utc(),$end->utc())['metrics'];$run->update(['data_export_id'=>$export->id,'status'=>'completed','metrics'=>$metrics,'completed_at'=>now()]);$schedule->update(['last_run_at'=>now(),'next_run_at'=>$this->next($schedule->frequency,$now)->utc()]);}catch(Throwable $e){$run->update(['status'=>'failed','error_message'=>mb_substr($e->getMessage(),0,2000),'completed_at'=>now()]);throw $e;}return $run->fresh(['export','schedule']);
    }
    public function runDue(?string $tenantId=null,int $limit=50):int
    {
        $query=ReportSchedule::query()->where('enabled',true)->where(fn($q)=>$q->whereNull('next_run_at')->orWhere('next_run_at','<=',now()));if($tenantId)$query->where('tenant_id',$tenantId);$count=0;foreach($query->orderBy('next_run_at')->limit($limit)->get() as $schedule){try{$this->run($schedule);$count++;}catch(Throwable){continue;}}return $count;
    }
    public function next(string $frequency,CarbonImmutable $from):CarbonImmutable{return match($frequency){'daily'=>$from->addDay()->startOfDay()->addHours(2),'weekly'=>$from->addWeek()->startOfWeek()->addHours(2),'monthly'=>$from->addMonthNoOverflow()->startOfMonth()->addHours(2),default=>$from->addMonthNoOverflow()->startOfMonth()->addHours(2)};}
    private function period(string $frequency,CarbonImmutable $now):array{return match($frequency){'daily'=>[$now->subDay()->startOfDay(),$now->subDay()->endOfDay()],'weekly'=>[$now->subWeek()->startOfWeek(),$now->subWeek()->endOfWeek()],'monthly'=>[$now->subMonthNoOverflow()->startOfMonth(),$now->subMonthNoOverflow()->endOfMonth()],default=>[$now->subMonthNoOverflow()->startOfMonth(),$now->subMonthNoOverflow()->endOfMonth()]};}
}
