<?php
namespace Modules\Reporting\Infrastructure\Console;
use Illuminate\Console\Command;
use Modules\Reporting\Application\Services\ReportScheduleService;
use Modules\Tenancy\Infrastructure\Models\Tenant;
final class RunDueReports extends Command
{
    protected $signature='reports:run-due {--tenant=} {--limit=50}';
    protected $description='Run due scheduled business reports and produce durable CSV exports.';
    public function handle(ReportScheduleService $service):int{$slug=(string)$this->option('tenant');$tenantId=$slug!==''?Tenant::query()->where('slug',$slug)->value('id'):null;$count=$service->runDue($tenantId,(int)$this->option('limit'));$this->info("Completed {$count} scheduled report(s).");return self::SUCCESS;}
}
