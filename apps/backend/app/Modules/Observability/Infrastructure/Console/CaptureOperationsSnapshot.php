<?php
namespace Modules\Observability\Infrastructure\Console;
use Illuminate\Console\Command;
use Modules\Observability\Application\Services\OperationsSnapshotService;
use Modules\Tenancy\Infrastructure\Models\Tenant;
final class CaptureOperationsSnapshot extends Command
{
    protected $signature='ops:snapshot {--tenant=}'; protected $description='Capture operational health and synchronize incidents.';
    public function handle(OperationsSnapshotService $service):int{$slug=(string)$this->option('tenant');if($slug!==''){$tenant=Tenant::query()->where('slug',$slug)->firstOrFail();$service->capture($tenant->id);$this->info('Captured tenant operations snapshot.');return self::SUCCESS;}$service->capture(null);Tenant::query()->where('status','active')->each(fn(Tenant $tenant)=>$service->capture($tenant->id));$this->info('Captured platform and tenant operations snapshots.');return self::SUCCESS;}
}
