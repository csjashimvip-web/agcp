<?php
namespace Modules\Reporting\Infrastructure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Reporting\Application\Listeners\GenerateInvoiceForPlacedOrder;
use Modules\Reporting\Infrastructure\Console\GenerateMissingInvoices;
use Modules\Reporting\Infrastructure\Console\RunDueReports;
use Modules\Reporting\Infrastructure\Console\PurgeExpiredExports;
use Modules\Shared\Infrastructure\Events\OutboxMessagePublished;
final class ReportingServiceProvider extends ServiceProvider
{
    public function boot():void
    {
        Event::listen(OutboxMessagePublished::class,GenerateInvoiceForPlacedOrder::class);
        if($this->app->runningInConsole())$this->commands([GenerateMissingInvoices::class,RunDueReports::class,PurgeExpiredExports::class]);
    }
}
